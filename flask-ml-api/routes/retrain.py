from flask import Blueprint, jsonify, request
from config import FLASK_INTERNAL_TOKEN
from database import (
    fetch_training_dataframe,
    purge_model_artifacts,
    fetch_recent_model_versions,
)
from training import train_and_save_models, register_failed_retrain
from training_manager import training_manager, TrainingCancelled

retrain_bp = Blueprint("retrain", __name__)

def check_internal_token() -> bool:
    incoming_token = request.headers.get("X-Internal-Token", "")
    return incoming_token == FLASK_INTERNAL_TOKEN

@retrain_bp.route("/api/retrain", methods=["POST"])
def retrain_model():
    payload = request.get_json(silent=True) or {}
    schema_version = None
    triggered_by_user_id = None
    triggered_by_email = None
    started_training = False

    try:
        if not check_internal_token():
            return jsonify({"status": "error", "message": "Token internal tidak valid"}), 401

        if "schema_version" in payload and payload["schema_version"] is not None:
            try:
                schema_version = int(payload["schema_version"])
            except (TypeError, ValueError) as exc:
                raise ValueError("schema_version harus berupa angka integer") from exc

            if schema_version <= 0:
                raise ValueError("schema_version harus lebih besar dari 0")

        if "triggered_by_user_id" in payload and payload["triggered_by_user_id"] is not None:
            try:
                triggered_by_user_id = int(payload["triggered_by_user_id"])
            except (TypeError, ValueError) as exc:
                raise ValueError("triggered_by_user_id harus berupa angka integer") from exc

        if "triggered_by_email" in payload and payload["triggered_by_email"] is not None:
            triggered_by_email = str(payload["triggered_by_email"]).strip() or None

        purge_training = bool(payload.get("purge_training", False))

        # Auto-tune via Optuna. 3 mode:
        #   missing / null / "auto" → adaptif (training.py decide)
        #   true                    → paksa Optuna aktif
        #   false                   → paksa skip Optuna
        raw_auto_tune = payload.get("auto_tune", "auto")
        if raw_auto_tune in (None, "auto", "adaptive"):
            auto_tune_mode = None  # adaptif
        else:
            auto_tune_mode = bool(raw_auto_tune)

        # Tuning trials: missing / null / "auto" → adaptif
        raw_trials = payload.get("tuning_trials")
        if raw_trials in (None, "auto", "adaptive"):
            tuning_trials_mode = None
        else:
            try:
                tuning_trials_mode = max(10, min(int(raw_trials), 500))
            except (TypeError, ValueError) as exc:
                raise ValueError("tuning_trials harus berupa angka integer atau 'auto'") from exc

        # Conflict strategy override (opsional)
        raw_conflict = payload.get("conflict_strategy")
        conflict_strategy_mode = None
        if raw_conflict in (None, "auto", "adaptive"):
            conflict_strategy_mode = None
        elif raw_conflict in ("majority_vote", "drop_high_minority", "drop_all"):
            conflict_strategy_mode = raw_conflict
        else:
            raise ValueError(
                "conflict_strategy harus salah satu: 'auto', 'majority_vote', "
                "'drop_high_minority', 'drop_all'"
            )

        # Validation split override (opsional)
        raw_split = payload.get("validation_split")
        if raw_split in (None, "auto", "adaptive"):
            validation_split_mode = None
        else:
            try:
                validation_split_mode = max(0.10, min(float(raw_split), 0.40))
            except (TypeError, ValueError) as exc:
                raise ValueError("validation_split harus berupa float 0.10-0.40 atau 'auto'") from exc

        # Cek apakah training sedang berjalan
        if training_manager.is_running:
            return jsonify({
                "status": "error",
                "message": "Training sedang berjalan. Hentikan dulu sebelum memulai yang baru.",
                "current_training": training_manager.get_status(),
            }), 409

        import threading

        # Jika purge diminta, hapus artifact model lama secara sinkron
        purge_summary = {}
        if purge_training:
            deleted_files = purge_model_artifacts()
            purge_summary = {
                "purged_model_files": len(deleted_files),
                "purged_model_file_names": deleted_files,
            }

        if not training_manager.start():
            return jsonify({
                "status": "error",
                "message": "Training sedang berjalan. Hentikan dulu sebelum memulai yang baru.",
                "current_training": training_manager.get_status(),
            }), 409
        started_training = True

        def run_retrain_async(sv, uid, email, do_auto_tune, n_trials, conflict_mode, split_mode):
            import gc
            import logging
            logger = logging.getLogger("retrain_thread")

            try:
                training_manager.advance_step("fetching_data")
                logger.info(
                    f"[RETRAIN] Starting training with schema_version={sv} "
                    f"auto_tune={do_auto_tune} trials={n_trials} "
                    f"conflict={conflict_mode} split={split_mode}"
                )
                dataframe = fetch_training_dataframe(schema_version=sv)
                logger.info(f"[RETRAIN] Fetched {len(dataframe)} training rows")

                result = train_and_save_models(
                    dataframe,
                    schema_version=sv,
                    triggered_by_user_id=uid,
                    triggered_by_email=email,
                    auto_tune=do_auto_tune,
                    tuning_trials=n_trials,
                    conflict_strategy=conflict_mode,
                    validation_split=split_mode,
                )
                training_manager.check_cancelled("finalizing")
                training_manager.finish(result)
                logger.info(f"[RETRAIN] Training completed: {result.get('model_version_name', 'unknown')}")

            except TrainingCancelled as exc:
                training_manager.mark_cancelled()
                logger.info(f"[RETRAIN] Training cancelled: {exc}")
                register_failed_retrain(sv, uid, email, f"Dibatalkan: {exc}")

            except Exception as exc:
                training_manager.fail(str(exc))
                logger.error(f"[RETRAIN] Training failed: {exc}", exc_info=True)
                register_failed_retrain(sv, uid, email, str(exc))
            finally:
                training_manager.clear_thread()
                gc.collect()

        thread = threading.Thread(
            target=run_retrain_async,
            args=(
                schema_version,
                triggered_by_user_id,
                triggered_by_email,
                auto_tune_mode,
                tuning_trials_mode,
                conflict_strategy_mode,
                validation_split_mode,
            ),
            name="retrain-worker",
        )
        thread.daemon = True
        training_manager.set_thread(thread)
        thread.start()

        response_payload = {
            "status": "success",
            "message": "Pelatihan ulang model sedang dikerjakan di latar belakang.",
            "schema_version": schema_version,
            "purge_training": purge_training,
            "auto_tune_mode": (
                "adaptive" if auto_tune_mode is None else ("forced_on" if auto_tune_mode else "forced_off")
            ),
            "tuning_trials_mode": (
                "adaptive" if tuning_trials_mode is None else int(tuning_trials_mode)
            ),
            "conflict_strategy_mode": conflict_strategy_mode or "adaptive",
            "validation_split_mode": validation_split_mode or "adaptive",
            "training_status_url": "/api/training/status",
            "training_cancel_url": "/api/training/cancel",
            "insights_url": "/api/training/insights",
        }
        if purge_summary:
            response_payload["purge_summary"] = purge_summary

        return jsonify(response_payload), 202

    except Exception as exc:
        if started_training and training_manager.is_running:
            training_manager.fail(str(exc))
            training_manager.clear_thread()
        register_failed_retrain(schema_version, triggered_by_user_id, triggered_by_email, str(exc))
        return (
            jsonify({
                "status": "error",
                "message": "Retrain model gagal dijalankan",
                "detail": str(exc),
            }),
            500,
        )


@retrain_bp.route("/api/training/status", methods=["GET"])
def training_status():
    """Cek status training yang sedang/sudah berjalan."""
    status = training_manager.get_status()
    return jsonify({"status": "success", "training": status}), 200


@retrain_bp.route("/api/training/cancel", methods=["POST"])
def cancel_training():
    """Hentikan training yang sedang berjalan."""
    if not check_internal_token():
        return jsonify({"status": "error", "message": "Token internal tidak valid"}), 401

    if training_manager.cancel():
        return jsonify({
            "status": "success",
            "message": "Sinyal pembatalan telah dikirim. Training akan berhenti di checkpoint berikutnya.",
            "training": training_manager.get_status(),
        }), 200
    else:
        return jsonify({
            "status": "info",
            "message": "Tidak ada training yang sedang berjalan.",
            "training": training_manager.get_status(),
        }), 200


@retrain_bp.route("/api/training/insights", methods=["GET"])
def training_insights():
    """Trend metrik & data growth across N versi terakhir.

    Berguna untuk admin dashboard: pantau apakah model degrading,
    apakah Optuna perlu re-tune, dan apakah dataset bertambah signifikan.
    """
    try:
        limit = max(2, min(int(request.args.get("limit", 10)), 50))
    except (TypeError, ValueError):
        limit = 10

    try:
        history = fetch_recent_model_versions(limit=limit)
    except Exception as exc:
        return jsonify({
            "status": "error",
            "message": "Gagal memuat history model",
            "detail": str(exc),
        }), 500

    versions_summary = []
    last_tuned_record = None
    best_balanced_accuracy = None
    best_version_name = None

    for record in history:
        cb_metrics = record.get("catboost_metrics") or {}
        tuning_summary = cb_metrics.get("tuning_summary")
        adaptive = cb_metrics.get("adaptive_decision") or {}
        ba = cb_metrics.get("balanced_accuracy")
        if ba is not None:
            ba_val = float(ba)
            if best_balanced_accuracy is None or ba_val > best_balanced_accuracy:
                best_balanced_accuracy = ba_val
                best_version_name = record.get("version_name")
        if tuning_summary and last_tuned_record is None:
            last_tuned_record = record

        versions_summary.append({
            "id": record.get("id"),
            "version_name": record.get("version_name"),
            "trained_at": record.get("trained_at").isoformat() if record.get("trained_at") else None,
            "is_current": record.get("is_current"),
            "dataset_rows_total": record.get("dataset_rows_total"),
            "rows_used": record.get("rows_used"),
            "validation_rows": record.get("validation_rows"),
            "catboost_validation_accuracy": record.get("catboost_validation_accuracy"),
            "balanced_accuracy": ba,
            "f1_macro": cb_metrics.get("f1_macro"),
            "recall_indikasi": cb_metrics.get("recall_indikasi"),
            "roc_auc": cb_metrics.get("roc_auc"),
            "tier": adaptive.get("tier"),
            "auto_tune_resolved": adaptive.get("auto_tune_resolved"),
            "tune_reason": adaptive.get("tune_reason"),
            "conflict_strategy_resolved": adaptive.get("conflict_strategy_resolved"),
            "tuned_via_optuna": tuning_summary is not None,
            "tuning_best_balanced_accuracy": (
                tuning_summary.get("best_balanced_accuracy") if tuning_summary else None
            ),
        })

    # Hitung sinyal untuk dashboard
    signals = []
    if len(versions_summary) >= 2:
        latest = versions_summary[0]
        prev = versions_summary[1]
        latest_ba = latest.get("balanced_accuracy")
        prev_ba = prev.get("balanced_accuracy")
        if latest_ba is not None and prev_ba is not None:
            delta = float(latest_ba) - float(prev_ba)
            if delta < -0.03:
                signals.append({
                    "level": "warning",
                    "type": "metric_regression",
                    "message": f"Balanced accuracy turun {abs(delta):.4f} vs versi sebelumnya. Pertimbangkan auto_tune=true.",
                })
            elif delta > 0.02:
                signals.append({
                    "level": "info",
                    "type": "metric_improvement",
                    "message": f"Balanced accuracy naik {delta:.4f} vs versi sebelumnya.",
                })

    if last_tuned_record:
        last_tuned_rows = last_tuned_record.get("dataset_rows_total") or 0
        latest_rows = versions_summary[0].get("dataset_rows_total") or 0
        if last_tuned_rows > 0 and latest_rows > 0:
            growth = (latest_rows - last_tuned_rows) / last_tuned_rows
            if growth >= 0.25:
                signals.append({
                    "level": "info",
                    "type": "data_growth",
                    "message": (
                        f"Data tumbuh {int(growth * 100)}% sejak Optuna tuning terakhir. "
                        f"Auto-trigger tuning akan aktif pada retrain berikutnya."
                    ),
                })

    return jsonify({
        "status": "success",
        "best_balanced_accuracy_seen": best_balanced_accuracy,
        "best_version_name": best_version_name,
        "last_optuna_tuned_version": (
            last_tuned_record.get("version_name") if last_tuned_record else None
        ),
        "last_optuna_tuned_at": (
            last_tuned_record.get("trained_at").isoformat()
            if last_tuned_record and last_tuned_record.get("trained_at") else None
        ),
        "signals": signals,
        "versions": versions_summary,
    }), 200
