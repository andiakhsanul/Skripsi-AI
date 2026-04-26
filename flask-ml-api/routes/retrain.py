from flask import Blueprint, jsonify, request
from config import FLASK_INTERNAL_TOKEN
from database import fetch_training_dataframe, purge_model_artifacts
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

        # Auto-tune via Optuna (opsional). Default off agar retrain biasa tetap cepat.
        auto_tune = bool(payload.get("auto_tune", False))
        try:
            tuning_trials = int(payload.get("tuning_trials", 80))
        except (TypeError, ValueError) as exc:
            raise ValueError("tuning_trials harus berupa angka integer") from exc
        tuning_trials = max(10, min(tuning_trials, 300))

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

        def run_retrain_async(sv, uid, email, do_auto_tune, n_trials):
            import gc
            import logging
            logger = logging.getLogger("retrain_thread")

            try:
                training_manager.advance_step("fetching_data")
                logger.info(
                    f"[RETRAIN] Starting training with schema_version={sv} "
                    f"auto_tune={do_auto_tune} trials={n_trials}"
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
                auto_tune,
                tuning_trials,
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
            "auto_tune": auto_tune,
            "tuning_trials": tuning_trials if auto_tune else None,
            "training_status_url": "/api/training/status",
            "training_cancel_url": "/api/training/cancel",
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
