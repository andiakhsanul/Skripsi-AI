import os
from flask import Blueprint, jsonify

from model_registry import ensure_latest_models_loaded, current_model_metadata
from config import MODEL_REGISTRY, REVIEW_PRIORITY_THRESHOLD

health_bp = Blueprint("health", __name__)

@health_bp.route("/api/health", methods=["GET"])
def health_check():
    ensure_latest_models_loaded()
    catboost_ready = MODEL_REGISTRY["catboost"] is not None
    nb_ready = MODEL_REGISTRY["naive_bayes"] is not None

    return jsonify({
        "status": "ok",
        "service": "flask-ml-api",
        "model_ready": catboost_ready and nb_ready,
        "catboost_ready": catboost_ready,
        "naive_bayes_ready": nb_ready,
        "primary_model": "catboost",
        "secondary_model": "categorical_nb",
        "review_priority_threshold": REVIEW_PRIORITY_THRESHOLD,
        **current_model_metadata(),
    })
