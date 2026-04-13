from flask import Blueprint, jsonify, request
from datetime import datetime, timezone
from config import FLASK_INTERNAL_TOKEN
from database import mark_model_version_as_current, fetch_model_version_record_by_id
from model_registry import load_models_from_version_record, current_model_metadata, MODEL_REGISTRY

activate_bp = Blueprint("activate", __name__)

def check_internal_token() -> bool:
    incoming_token = request.headers.get("X-Internal-Token", "")
    return incoming_token == FLASK_INTERNAL_TOKEN

def activate_model_version_local(model_version_id: int) -> dict:
    target_version = fetch_model_version_record_by_id(model_version_id, status="ready")
    if target_version is None:
        raise ValueError("Versi model siap tidak ditemukan.")

    if not load_models_from_version_record(target_version, persist_canonical=True):
        raise ValueError("Artifact model untuk versi yang dipilih tidak ditemukan.")

    activated_record = mark_model_version_as_current(model_version_id)
    MODEL_REGISTRY["metadata"] = activated_record

    return activated_record

def serialize_datetime(value) -> str|None:
    if value is None:
        return None
    if isinstance(value, datetime):
        return value.astimezone(timezone.utc).isoformat()
    return str(value)

@activate_bp.route("/api/models/activate", methods=["POST"])
def activate_model():
    try:
        if not check_internal_token():
            return jsonify({"status": "error", "message": "Token internal tidak valid"}), 401

        payload = request.get_json(silent=True) or {}
        if payload.get("model_version_id") is None:
            raise ValueError("model_version_id wajib dikirim.")

        try:
            model_version_id = int(payload["model_version_id"])
        except (TypeError, ValueError) as exc:
            raise ValueError("model_version_id harus berupa angka integer.") from exc

        if model_version_id <= 0:
            raise ValueError("model_version_id harus lebih besar dari 0.")

        activated = activate_model_version_local(model_version_id)

        return (
            jsonify({
                "status": "success",
                "message": "Versi model aktif berhasil diperbarui",
                "model_version": {
                    "id": activated["id"],
                    "version_name": activated["version_name"],
                    "schema_version": activated["schema_version"],
                    "trained_at": serialize_datetime(activated.get("trained_at")),
                    "activated_at": serialize_datetime(activated.get("activated_at")),
                    "is_current": bool(activated.get("is_current", False)),
                },
                **current_model_metadata(),
            }),
            200,
        )
    except ValueError as exc:
        return jsonify({"status": "error", "message": str(exc)}), 400
    except Exception as exc:
        return (
            jsonify({
                "status": "error",
                "message": "Aktivasi versi model gagal dijalankan",
                "detail": str(exc),
            }),
            500,
        )
