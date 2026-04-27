"""Pemilihan hyperparameter dan strategi training adaptif berdasarkan ukuran data.

Tujuan: ketika admin menambah data dari waktu ke waktu, pipeline otomatis
menyesuaikan diri (depth bertambah, iterations bertambah, regularisasi
mengendur, validation split mengecil) tanpa intervensi manual.
"""
from datetime import datetime, timezone
from typing import Optional

from config import CATBOOST_THREAD_COUNT


# ─── Tier Definition ────────────────────────────────────────────────────
# Threshold dipilih berdasarkan rule of thumb: depth N butuh ~2^N samples
# minimum untuk tidak overfit (common heuristic GBDT untuk dataset kecil).
TIER_SMALL_MAX = 800        # depth 5
TIER_MEDIUM_MAX = 2500      # depth 6
# > TIER_MEDIUM_MAX → tier "large", depth 7

# Auto-trigger Optuna conditions
DATA_GROWTH_TRIGGER = 0.25  # tune ulang kalau data bertambah ≥ 25% sejak tuning terakhir
METRIC_DROP_TRIGGER = 0.03  # tune ulang kalau balanced_accuracy turun ≥ 3% dari best
DAYS_SINCE_TUNE_TRIGGER = 14  # tune ulang setiap 2 minggu


def pick_dataset_tier(n_rows: int) -> str:
    if n_rows < TIER_SMALL_MAX:
        return "small"
    if n_rows < TIER_MEDIUM_MAX:
        return "medium"
    return "large"


def pick_base_catboost_params(n_rows: int, thread_count: Optional[int] = None) -> dict:
    """Base hyperparameter berdasarkan ukuran dataset.

    Ketiga tier ini didesain agar:
    - Dataset kecil: shallow + high regularization (anti-overfit)
    - Dataset medium: balanced (kondisi saat ini ~900 rows)
    - Dataset besar: deeper + more iterations + lower regularization
    """
    thread_count = thread_count or CATBOOST_THREAD_COUNT
    tier = pick_dataset_tier(n_rows)

    if tier == "small":
        params = {
            "iterations": 600,
            "depth": 5,
            "learning_rate": 0.035,
            "l2_leaf_reg": 8,
            "random_strength": 1.0,
            "bagging_temperature": 0.8,
            "border_count": 64,
            "min_data_in_leaf": 8,
            "rsm": 0.85,
            "auto_class_weights": "Balanced",
        }
    elif tier == "medium":
        params = {
            "iterations": 900,
            "depth": 6,
            "learning_rate": 0.05,
            "l2_leaf_reg": 5,
            "random_strength": 1.0,
            "bagging_temperature": 0.7,
            "border_count": 96,
            "min_data_in_leaf": 5,
            "rsm": 0.85,
            "auto_class_weights": "Balanced",
        }
    else:  # large
        params = {
            "iterations": 1200,
            "depth": 7,
            "learning_rate": 0.06,
            "l2_leaf_reg": 3,
            "random_strength": 0.8,
            "bagging_temperature": 0.6,
            "border_count": 128,
            "min_data_in_leaf": 3,
            "rsm": 0.85,
            "auto_class_weights": "Balanced",
        }

    params.update({
        "verbose": False,
        "random_seed": 42,
        "eval_metric": "AUC",
        "od_type": "Iter",
        "od_wait": 50,
        "thread_count": thread_count,
    })
    return params


def pick_optuna_trials(n_rows: int, override: Optional[int] = None) -> int:
    """Jumlah trial Optuna yang sesuai. Lebih banyak data → lebih banyak trial.

    Override bisa dipakai dari API. None → auto.
    """
    if override is not None:
        return max(10, min(int(override), 500))
    tier = pick_dataset_tier(n_rows)
    return {"small": 60, "medium": 100, "large": 150}[tier]


def pick_validation_split(n_rows: int) -> float:
    """Validation hold-out fraction. Semakin banyak data, semakin kecil persentase
    (jumlah absolut tetap memadai)."""
    tier = pick_dataset_tier(n_rows)
    return {"small": 0.20, "medium": 0.18, "large": 0.15}[tier]


def pick_conflict_strategy(n_rows: int, conflict_ratio: float) -> str:
    """Strategi resolusi konflik label adaptif.

    - Konflik rendah (< 10%): majority_vote (jangan buang data)
    - Konflik sedang (10-25%): drop_high_minority kalau data cukup
    - Konflik tinggi (> 25%): drop_high_minority untuk membersihkan noise
    - Data sangat besar (>2500): drop_high_minority by default karena bisa kehilangan
      sedikit data tanpa penalty signifikan.
    """
    if conflict_ratio >= 0.25:
        return "drop_high_minority"
    if n_rows >= TIER_MEDIUM_MAX:
        return "drop_high_minority"
    if conflict_ratio >= 0.10 and n_rows >= TIER_SMALL_MAX:
        return "drop_high_minority"
    return "majority_vote"


def should_auto_tune(
    history: list[dict],
    current_n_rows: int,
    current_balanced_accuracy: Optional[float] = None,
) -> tuple[bool, str]:
    """Putuskan apakah Optuna auto-tune harus jalan otomatis.

    Args:
        history: list dict dari model_versions terbaru (newest first), masing-masing
            mengandung minimal: trained_at, dataset_rows_total, catboost_metrics
            (yang berisi balanced_accuracy & opsional tuning_summary).
        current_n_rows: jumlah baris training saat ini.
        current_balanced_accuracy: BA model saat ini (kalau sudah dihitung).

    Returns:
        (should_tune, reason) — reason adalah label singkat untuk log/UI.
    """
    if not history:
        return True, "first_training_no_history"

    # Cari versi terakhir yang sukses di-tune via Optuna.
    last_tuned = None
    for record in history:
        metrics = record.get("catboost_metrics") or {}
        if metrics.get("tuning_summary"):
            last_tuned = record
            break

    if last_tuned is None:
        return True, "never_tuned_via_optuna"

    # 1. Pertumbuhan data ≥ 25% sejak tuning terakhir
    last_rows = int(last_tuned.get("dataset_rows_total") or 0)
    if last_rows > 0:
        growth = (current_n_rows - last_rows) / last_rows
        if growth >= DATA_GROWTH_TRIGGER:
            return True, f"data_grew_{int(growth * 100)}pct_since_last_tune"

    # 2. Sudah lama sejak tuning terakhir
    last_trained_at = last_tuned.get("trained_at")
    if isinstance(last_trained_at, datetime):
        if last_trained_at.tzinfo is None:
            last_trained_at = last_trained_at.replace(tzinfo=timezone.utc)
        days_elapsed = (datetime.now(timezone.utc) - last_trained_at).days
        if days_elapsed >= DAYS_SINCE_TUNE_TRIGGER:
            return True, f"days_since_tune_{days_elapsed}"

    # 3. Performance regression dibanding best historis
    if current_balanced_accuracy is not None:
        best_ba = 0.0
        for record in history:
            metrics = record.get("catboost_metrics") or {}
            ba = metrics.get("balanced_accuracy")
            if ba is not None and float(ba) > best_ba:
                best_ba = float(ba)
        if best_ba > 0 and current_balanced_accuracy < best_ba - METRIC_DROP_TRIGGER:
            drop = round((best_ba - current_balanced_accuracy) * 100, 1)
            return True, f"ba_dropped_{drop}pct_vs_best"

    return False, "no_trigger_recent_tune_still_valid"


def build_adaptive_decision(
    n_rows: int,
    conflict_ratio: float = 0.0,
    history: Optional[list[dict]] = None,
    current_ba: Optional[float] = None,
    user_auto_tune: Optional[bool] = None,
    user_tuning_trials: Optional[int] = None,
    user_conflict_strategy: Optional[str] = None,
    user_validation_split: Optional[float] = None,
) -> dict:
    """Satu titik keputusan untuk semua parameter adaptif.

    Argumen `user_*` adalah override eksplisit (None → auto-decide).
    Returns dict yang siap dipakai training pipeline.
    """
    tier = pick_dataset_tier(n_rows)

    # Auto-tune decision
    if user_auto_tune is None:
        should_tune, tune_reason = should_auto_tune(
            history or [], n_rows, current_ba
        )
        auto_tune = should_tune
    else:
        auto_tune = bool(user_auto_tune)
        tune_reason = "user_override"

    # Conflict strategy
    if user_conflict_strategy:
        conflict_strategy = user_conflict_strategy
    else:
        conflict_strategy = pick_conflict_strategy(n_rows, conflict_ratio)

    # Validation split
    if user_validation_split is not None:
        validation_split = max(0.10, min(float(user_validation_split), 0.40))
    else:
        validation_split = pick_validation_split(n_rows)

    # Optuna trials
    tuning_trials = pick_optuna_trials(n_rows, user_tuning_trials)

    return {
        "tier": tier,
        "auto_tune": auto_tune,
        "tune_reason": tune_reason,
        "tuning_trials": tuning_trials,
        "conflict_strategy": conflict_strategy,
        "validation_split": validation_split,
        "base_catboost_params": pick_base_catboost_params(n_rows),
    }
