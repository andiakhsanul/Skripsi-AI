"""Training Manager — Singleton untuk mengelola lifecycle training.

Menyediakan:
  - Cancellation signal (threading.Event)
  - Progress tracking real-time
  - Thread-safe locking (satu training sekaligus)
  - Status reporting untuk API endpoint
"""

import logging
import threading
from datetime import datetime, timezone
from typing import Optional

logger = logging.getLogger("training_manager")

# ─── Training Steps (ordered) ──────────────────────────────────────────
TRAINING_STEPS = [
    "fetching_data",
    "encoding",
    "data_quality_check",
    "split_dataset",
    "training_catboost_eval",
    "cv_threshold_catboost",
    "training_catboost_final",
    "training_naive_bayes_eval",
    "cv_threshold_naive_bayes",
    "training_naive_bayes_final",
    "building_evaluation",
    "persisting_models",
    "done",
]

STEP_WEIGHTS = {
    "fetching_data": 3,
    "encoding": 5,
    "data_quality_check": 2,
    "split_dataset": 2,
    "training_catboost_eval": 15,
    "cv_threshold_catboost": 25,
    "training_catboost_final": 10,
    "training_naive_bayes_eval": 5,
    "cv_threshold_naive_bayes": 20,
    "training_naive_bayes_final": 3,
    "building_evaluation": 5,
    "persisting_models": 3,
    "done": 2,
}

ACTIVE_STATUSES = {"running", "cancelling"}


class TrainingManager:
    """Thread-safe singleton yang mengelola satu proses training sekaligus."""

    _instance: Optional["TrainingManager"] = None
    _init_lock = threading.Lock()

    def __new__(cls) -> "TrainingManager":
        with cls._init_lock:
            if cls._instance is None:
                instance = super().__new__(cls)
                instance._initialized = False
                cls._instance = instance
        return cls._instance

    def __init__(self) -> None:
        if self._initialized:
            return
        self._lock = threading.Lock()
        self._cancel_event = threading.Event()
        self._status = "idle"
        self._current_step: Optional[str] = None
        self._step_index: int = 0
        self._started_at: Optional[datetime] = None
        self._finished_at: Optional[datetime] = None
        self._error: Optional[str] = None
        self._result: Optional[dict] = None
        self._thread: Optional[threading.Thread] = None
        self._extra_info: dict = {}
        self._initialized = True

    # ─── Public API ────────────────────────────────────────────────────

    @property
    def is_running(self) -> bool:
        return self._status in ACTIVE_STATUSES

    @property
    def is_cancelled(self) -> bool:
        return self._cancel_event.is_set()

    def check_cancelled(self, step_name: Optional[str] = None) -> None:
        """Panggil di setiap checkpoint. Raise jika sudah di-cancel."""
        if self._cancel_event.is_set():
            cancel_step = step_name or self._current_step or "unknown"
            raise TrainingCancelled(f"Training dibatalkan pada step: {cancel_step}")

    def advance_step(self, step_name: str, extra_info: Optional[dict] = None) -> None:
        """Catat progres training ke step baru."""
        with self._lock:
            self._current_step = step_name
            if step_name in TRAINING_STEPS:
                self._step_index = TRAINING_STEPS.index(step_name)
            if extra_info:
                self._extra_info.update(extra_info)
        logger.info(f"[TRAINING] Step: {step_name} ({self.progress_percent:.0f}%)")
        self.check_cancelled(step_name)

    @property
    def progress_percent(self) -> float:
        """Estimasi progres berdasarkan bobot step."""
        total_weight = sum(STEP_WEIGHTS.get(s, 1) for s in TRAINING_STEPS)
        completed_weight = sum(
            STEP_WEIGHTS.get(s, 1) for s in TRAINING_STEPS[: self._step_index]
        )
        return min(100.0, (completed_weight / total_weight) * 100) if total_weight else 0.0

    def start(self) -> bool:
        """Coba mulai training baru. Return False jika sudah ada yang berjalan."""
        with self._lock:
            if self._status in ACTIVE_STATUSES:
                return False
            self._cancel_event.clear()
            self._status = "running"
            self._current_step = None
            self._step_index = 0
            self._started_at = datetime.now(timezone.utc)
            self._finished_at = None
            self._error = None
            self._result = None
            self._extra_info = {}
            return True

    def finish(self, result: Optional[dict] = None) -> None:
        """Tandai training selesai sukses."""
        with self._lock:
            self._status = "completed"
            self._current_step = "done"
            self._step_index = len(TRAINING_STEPS) - 1
            self._finished_at = datetime.now(timezone.utc)
            self._result = result

    def fail(self, error_message: str) -> None:
        """Tandai training gagal."""
        with self._lock:
            self._status = "failed"
            self._finished_at = datetime.now(timezone.utc)
            self._error = error_message

    def cancel(self) -> bool:
        """Kirim sinyal cancel. Return True jika training sedang berjalan."""
        with self._lock:
            if self._status not in ACTIVE_STATUSES:
                return False
            self._cancel_event.set()
            self._status = "cancelling"
        logger.info("[TRAINING] Cancel signal sent")
        return True

    def mark_cancelled(self) -> None:
        """Tandai training sebagai sudah dibatalkan (dipanggil setelah catch)."""
        with self._lock:
            self._status = "cancelled"
            self._finished_at = datetime.now(timezone.utc)

    def set_thread(self, thread: threading.Thread) -> None:
        with self._lock:
            self._thread = thread

    def clear_thread(self) -> None:
        with self._lock:
            self._thread = None

    def get_status(self) -> dict:
        """Kembalikan snapshot status saat ini."""
        elapsed_seconds = None
        if self._started_at:
            end_time = self._finished_at or datetime.now(timezone.utc)
            elapsed_seconds = round((end_time - self._started_at).total_seconds(), 1)

        return {
            "status": self._status,
            "current_step": self._current_step,
            "step_index": self._step_index,
            "total_steps": len(TRAINING_STEPS),
            "progress_percent": round(self.progress_percent, 1),
            "started_at": self._started_at.isoformat() if self._started_at else None,
            "finished_at": self._finished_at.isoformat() if self._finished_at else None,
            "elapsed_seconds": elapsed_seconds,
            "error": self._error,
            "extra_info": self._extra_info,
            "result_summary": self._build_result_summary(),
            "is_cancellable": self._status == "running",
        }

    def _build_result_summary(self) -> Optional[dict]:
        if self._result is None:
            return None
        r = self._result
        return {
            "model_version_name": r.get("model_version_name"),
            "rows_used": r.get("rows_used"),
            "catboost_accuracy": r.get("catboost_accuracy"),
            "naive_bayes_accuracy": r.get("naive_bayes_accuracy"),
            "catboost_threshold": r.get("catboost_threshold"),
            "naive_bayes_threshold": r.get("naive_bayes_threshold"),
            "catboost_metrics": r.get("catboost_metrics"),
            "naive_bayes_metrics": r.get("naive_bayes_metrics"),
            "cv_summary": r.get("cv_summary"),
        }


class TrainingCancelled(Exception):
    """Raised saat training di-cancel di checkpoint."""
    pass


# Global singleton instance
training_manager = TrainingManager()
