from flask import Blueprint, jsonify, request
from config import FLASK_INTERNAL_TOKEN
from database import fetch_training_dataframe
from training import train_and_save_models, register_failed_retrain

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

        import threading
        from database import persist_model_version_record
        from training import build_version_name
        from datetime import datetime, timezone

        trained_at = datetime.now(timezone.utc)
        version_name = build_version_name(schema_version, trained_at, status="training")
        
        try:
            persist_model_version_record({
                "version_name": version_name,
                "schema_version": schema_version or 1,
                "status": "training",
                "is_current": False,
                "triggered_by_user_id": triggered_by_user_id,
                "triggered_by_email": triggered_by_email,
                "training_table": "spk_training_data",
                "primary_model": "catboost",
                "secondary_model": "categorical_nb",
                "note": "Proses pelatihan sedang berjalan di latar belakang...",
                "trained_at": trained_at,
            })
        except Exception:
            pass
            
        def run_retrain_async(sv, uid, email):
            try:
                dataframe = fetch_training_dataframe(schema_version=sv)
                train_and_save_models(
                    dataframe,
                    schema_version=sv,
                    triggered_by_user_id=uid,
                    triggered_by_email=email,
                )
            except Exception as exc:
                register_failed_retrain(sv, uid, email, str(exc))

        thread = threading.Thread(
            target=run_retrain_async,
            args=(schema_version, triggered_by_user_id, triggered_by_email)
        )
        thread.daemon = True
        thread.start()

        return (
            jsonify({
                "status": "success",
                "message": "Pelatihan ulang model sedang dikerjakan di latar belakang.",
                "schema_version": schema_version,
            }),
            202,
        )
    except Exception as exc:
        register_failed_retrain(schema_version, triggered_by_user_id, triggered_by_email, str(exc))
        return (
            jsonify({
                "status": "error",
                "message": "Retrain model gagal dijalankan",
                "detail": str(exc),
            }),
            500,
        )
