import pandas as pd
from typing import Optional

from config import DEFAULT_POSITIVE_THRESHOLD, REVIEW_PRIORITY_THRESHOLD, DB_FEATURE_COLUMNS, MODEL_REGISTRY
from encoding import validate_encoded_features
from features import add_engineered_features, transform_features_for_naive_bayes
from evaluation import probability_for_class
from model_registry import ensure_latest_models_loaded, extract_model_payload, current_model_metadata

def derive_label_and_confidence(probability_indikasi: float, threshold: float = DEFAULT_POSITIVE_THRESHOLD) -> tuple[str, float]:
    bounded_probability = max(0.0, min(float(probability_indikasi), 1.0))
    bounded_threshold = max(0.0, min(float(threshold), 1.0))
    predicted_label = "Indikasi" if bounded_probability >= bounded_threshold else "Layak"
    confidence = bounded_probability if predicted_label == "Indikasi" else 1.0 - bounded_probability
    return predicted_label, round(confidence, 4)

def infer_with_dual_model(features: pd.DataFrame) -> dict:
    ensure_latest_models_loaded()
    catboost_artifact = MODEL_REGISTRY["catboost"]
    nb_artifact = MODEL_REGISTRY["naive_bayes"]
    catboost_model, catboost_threshold = extract_model_payload(catboost_artifact)
    nb_model, naive_bayes_threshold = extract_model_payload(nb_artifact)
    model_ready = catboost_model is not None and nb_model is not None

    if model_ready:
        features_extended = add_engineered_features(features)
        cb_probability = probability_for_class(catboost_model, features_extended, target_class=1)
        nb_features = transform_features_for_naive_bayes(features_extended)
        nb_probability = probability_for_class(nb_model, nb_features, target_class=1)

        pred_cb, confidence_cb = derive_label_and_confidence(cb_probability, catboost_threshold)
        pred_nb, confidence_nb = derive_label_and_confidence(nb_probability, naive_bayes_threshold)
    else:
        pred_cb, confidence_cb = "Indikasi", 0.5
        pred_nb, confidence_nb = "Indikasi", 0.5
        catboost_threshold = DEFAULT_POSITIVE_THRESHOLD
        naive_bayes_threshold = DEFAULT_POSITIVE_THRESHOLD

    disagreement_flag = pred_cb != pred_nb
    final_recommendation = pred_cb
    review_priority = "high" if disagreement_flag or confidence_cb < REVIEW_PRIORITY_THRESHOLD else "normal"
    metadata = current_model_metadata()

    model_results = {
        "catboost": {"label": pred_cb, "confidence": confidence_cb},
        "naive_bayes": {"label": pred_nb, "confidence": confidence_nb},
        "catboost_threshold": round(float(catboost_threshold), 4),
        "naive_bayes_threshold": round(float(naive_bayes_threshold), 4),
        "disagreement_flag": disagreement_flag,
        "final_recommendation": final_recommendation,
        "review_priority": review_priority,
        "model_ready": model_ready,
    }

    return {
        "catboost_label": pred_cb,
        "catboost_confidence": confidence_cb,
        "naive_bayes_label": pred_nb,
        "naive_bayes_confidence": confidence_nb,
        "catboost_result": pred_cb,
        "naive_bayes_result": pred_nb,
        "catboost_threshold": round(float(catboost_threshold), 4),
        "naive_bayes_threshold": round(float(naive_bayes_threshold), 4),
        "final_recommendation": final_recommendation,
        "disagreement_flag": disagreement_flag,
        "review_priority": review_priority,
        "model_ready": model_ready,
        "model_results": model_results,
        **metadata,
    }

def get_prediction_input(payload: dict) -> pd.DataFrame:
    """Takes features, validates that they assume expected encoded integer forms, adds engineered features."""
    values = validate_encoded_features(payload)
    df = pd.DataFrame([values], columns=DB_FEATURE_COLUMNS)
    return add_engineered_features(df)
