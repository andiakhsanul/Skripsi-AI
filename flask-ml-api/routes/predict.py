from flask import Blueprint, jsonify, request
from config import FLASK_INTERNAL_TOKEN
from inference import get_prediction_input, infer_with_dual_model

predict_bp = Blueprint("predict", __name__)

def check_internal_token() -> bool:
    incoming_token = request.headers.get("X-Internal-Token", "")
    return incoming_token == FLASK_INTERNAL_TOKEN

@predict_bp.route("/api/predict", methods=["POST"])
def predict():
    try:
        if not check_internal_token():
            return jsonify({"status": "error", "message": "Token internal tidak valid"}), 401

        payload = request.get_json(silent=True)
        if not payload:
            return jsonify({"status": "error", "message": "Payload JSON tidak ditemukan"}), 400

        features = get_prediction_input(payload)
        prediction = infer_with_dual_model(features)

        return jsonify({"status": "success", **prediction}), 200
    except ValueError as exc:
        return jsonify({"status": "error", "message": str(exc)}), 400
    except Exception as exc:
        return (
            jsonify({
                "status": "error",
                "message": "Terjadi kesalahan internal pada service ML",
                "detail": str(exc),
            }),
            500,
        )
