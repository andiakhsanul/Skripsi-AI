from pathlib import Path
from typing import Optional
import joblib

from database import fetch_active_model_version_record, fetch_latest_model_version_record
from config import MODEL_REGISTRY, CATBOOST_MODEL_PATH, NAIVE_BAYES_MODEL_PATH, APP_ROOT, DEFAULT_POSITIVE_THRESHOLD

def relative_artifact_path(path: Path) -> str:
    try:
        return str(path.relative_to(APP_ROOT))
    except ValueError:
        return str(path)

def resolve_artifact_path(raw_path: Optional[str], fallback: Path) -> Path:
    if not raw_path:
        return fallback

    candidate = Path(raw_path)
    return candidate if candidate.is_absolute() else APP_ROOT / candidate

def extract_model_payload(artifact):
    if isinstance(artifact, dict):
        return artifact.get("model"), float(artifact.get("indikasi_threshold", DEFAULT_POSITIVE_THRESHOLD))
    return artifact, DEFAULT_POSITIVE_THRESHOLD

def create_model_artifact(model, indikasi_threshold: float, extra_metadata: Optional[dict] = None) -> dict:
    payload = {
        "model": model,
        "indikasi_threshold": round(float(indikasi_threshold), 4),
    }
    if extra_metadata:
        payload.update(extra_metadata)
    return payload

def load_models_from_version_record(metadata: Optional[dict], persist_canonical: bool = False) -> bool:
    if metadata is None:
        return False

    catboost_path = resolve_artifact_path(
        metadata.get("catboost_artifact_path"),
        CATBOOST_MODEL_PATH,
    )
    naive_bayes_path = resolve_artifact_path(
        metadata.get("naive_bayes_artifact_path"),
        NAIVE_BAYES_MODEL_PATH,
    )

    if not catboost_path.exists() or not naive_bayes_path.exists():
        return False

    catboost_artifact = joblib.load(catboost_path)
    naive_bayes_artifact = joblib.load(naive_bayes_path)

    if persist_canonical:
        CATBOOST_MODEL_PATH.parent.mkdir(parents=True, exist_ok=True)
        joblib.dump(catboost_artifact, CATBOOST_MODEL_PATH)
        joblib.dump(naive_bayes_artifact, NAIVE_BAYES_MODEL_PATH)

    _, catboost_threshold = extract_model_payload(catboost_artifact)
    _, naive_bayes_threshold = extract_model_payload(naive_bayes_artifact)

    MODEL_REGISTRY["catboost"] = catboost_artifact
    MODEL_REGISTRY["naive_bayes"] = naive_bayes_artifact
    MODEL_REGISTRY["metadata"] = {
        **metadata,
        "catboost_threshold": catboost_threshold,
        "naive_bayes_threshold": naive_bayes_threshold,
    }

    return True

def load_saved_models() -> None:
    metadata = None

    try:
        metadata = fetch_active_model_version_record(status="ready")
        if not load_models_from_version_record(metadata):
            metadata = fetch_latest_model_version_record(status="ready")
            if load_models_from_version_record(metadata):
                return
    except Exception:
        metadata = None

    if CATBOOST_MODEL_PATH.exists():
        MODEL_REGISTRY["catboost"] = joblib.load(CATBOOST_MODEL_PATH)

    if NAIVE_BAYES_MODEL_PATH.exists():
        MODEL_REGISTRY["naive_bayes"] = joblib.load(NAIVE_BAYES_MODEL_PATH)

    MODEL_REGISTRY["metadata"] = metadata

def ensure_latest_models_loaded(force_reload: bool = False) -> None:
    active_ready = None

    try:
        active_ready = fetch_active_model_version_record(status="ready") or fetch_latest_model_version_record(status="ready")
    except Exception:
        active_ready = None

    current_metadata = MODEL_REGISTRY.get("metadata") or {}
    current_version_name = current_metadata.get("version_name")
    active_version_name = active_ready.get("version_name") if active_ready else None
    needs_reload = force_reload

    if MODEL_REGISTRY["catboost"] is None or MODEL_REGISTRY["naive_bayes"] is None:
        needs_reload = True

    if active_version_name and active_version_name != current_version_name:
        needs_reload = True

    if active_ready is None and (MODEL_REGISTRY["catboost"] is None or MODEL_REGISTRY["naive_bayes"] is None):
        needs_reload = True

    if needs_reload:
        load_saved_models()

def current_model_metadata() -> dict:
    from datetime import datetime, timezone
    
    metadata = MODEL_REGISTRY.get("metadata") or {}
    catboost_artifact = MODEL_REGISTRY.get("catboost")
    
    _, catboost_threshold = extract_model_payload(MODEL_REGISTRY.get("catboost"))
    _, naive_bayes_threshold = extract_model_payload(MODEL_REGISTRY.get("naive_bayes"))
    artifact_positive_weight = catboost_artifact.get("positive_class_weight") if isinstance(catboost_artifact, dict) else None

    def serialize_datetime(value) -> Optional[str]:
        if value is None:
            return None
        if isinstance(value, datetime):
            return value.astimezone(timezone.utc).isoformat()
        return str(value)

    return {
        "model_version_id": metadata.get("id"),
        "model_version_name": metadata.get("version_name"),
        "model_trained_at": serialize_datetime(metadata.get("trained_at")),
        "model_schema_version": metadata.get("schema_version"),
        "model_is_current": bool(metadata.get("is_current", False)),
        "model_activated_at": serialize_datetime(metadata.get("activated_at")),
        "catboost_threshold": metadata.get("catboost_threshold", catboost_threshold),
        "naive_bayes_threshold": metadata.get("naive_bayes_threshold", naive_bayes_threshold),
        "positive_class_weight": metadata.get("positive_class_weight", artifact_positive_weight),
    }
