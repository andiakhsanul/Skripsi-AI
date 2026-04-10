import os
from datetime import datetime, timezone
from pathlib import Path
from typing import Optional

import joblib
import pandas as pd
import psycopg2
from catboost import CatBoostClassifier
from flask import Flask, jsonify, request
from psycopg2 import sql
from psycopg2.extras import Json, RealDictCursor
from sklearn.metrics import (
    accuracy_score,
    balanced_accuracy_score,
    confusion_matrix,
    f1_score,
    fbeta_score,
    precision_score,
    recall_score,
)
from sklearn.model_selection import StratifiedKFold, train_test_split
from sklearn.naive_bayes import CategoricalNB

app = Flask(__name__)

APP_ROOT = Path(__file__).resolve().parent


def resolve_env_path(env_name: str, default: str) -> Path:
    configured_path = Path(os.getenv(env_name, default))
    return configured_path if configured_path.is_absolute() else APP_ROOT / configured_path


# ─── Definisi 13 Fitur SPK KIP-K ─────────────────────────────────────────────
BINARY_FEATURES = ["kip", "pkh", "kks", "dtks", "sktm"]
ORDINAL_FEATURES = [
    "penghasilan_gabungan",
    "penghasilan_ayah",
    "penghasilan_ibu",
    "jumlah_tanggungan",
    "anak_ke",
    "status_orangtua",
    "status_rumah",
    "daya_listrik",
]
FEATURE_COLUMNS = BINARY_FEATURES + ORDINAL_FEATURES
TARGET_COLUMN = "label_class"
REVIEW_PRIORITY_THRESHOLD = float(os.getenv("REVIEW_PRIORITY_THRESHOLD", "0.65"))
MODEL_VALIDATION_SPLIT = min(max(float(os.getenv("MODEL_VALIDATION_SPLIT", "0.2")), 0.1), 0.4)

CATBOOST_MODEL_PATH = resolve_env_path("CATBOOST_MODEL_PATH", "models/catboost_model.joblib")
NAIVE_BAYES_MODEL_PATH = resolve_env_path("NAIVE_BAYES_MODEL_PATH", "models/naive_bayes_model.joblib")
TRAINING_TABLE = os.getenv("TRAINING_TABLE", "spk_training_data")
MODEL_VERSIONS_TABLE = os.getenv("MODEL_VERSIONS_TABLE", "model_versions")
FLASK_INTERNAL_TOKEN = os.getenv("FLASK_INTERNAL_TOKEN", "spk_internal_dev_token")

MODEL_REGISTRY = {"catboost": None, "naive_bayes": None, "metadata": None}
DEFAULT_POSITIVE_THRESHOLD = float(os.getenv("DEFAULT_POSITIVE_THRESHOLD", "0.5"))
POSITIVE_F_BETA = float(os.getenv("POSITIVE_F_BETA", "1.0"))


def db_config() -> dict:
    return {
        "host": os.getenv("DB_HOST", "db"),
        "port": os.getenv("DB_PORT", "5432"),
        "dbname": os.getenv("DB_NAME", "spk_kipk_db"),
        "user": os.getenv("DB_USER", "postgres"),
        "password": os.getenv("DB_PASSWORD", "postgres"),
    }


def ensure_model_directory() -> None:
    CATBOOST_MODEL_PATH.parent.mkdir(parents=True, exist_ok=True)
    NAIVE_BAYES_MODEL_PATH.parent.mkdir(parents=True, exist_ok=True)


def normalize_label(raw_value: str) -> int:
    normalized = str(raw_value).strip().lower()
    positive_values = {"indikasi", "1", "true", "ya"}
    return 1 if normalized in positive_values else 0


def check_internal_token() -> bool:
    incoming_token = request.headers.get("X-Internal-Token", "")
    return incoming_token == FLASK_INTERNAL_TOKEN


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


def serialize_datetime(value) -> Optional[str]:
    if value is None:
        return None

    if isinstance(value, datetime):
        return value.astimezone(timezone.utc).isoformat()

    return str(value)


def current_model_metadata() -> dict:
    metadata = MODEL_REGISTRY.get("metadata") or {}
    catboost_artifact = MODEL_REGISTRY.get("catboost")
    _, catboost_threshold = extract_model_payload(MODEL_REGISTRY.get("catboost"))
    _, naive_bayes_threshold = extract_model_payload(MODEL_REGISTRY.get("naive_bayes"))
    artifact_positive_weight = catboost_artifact.get("positive_class_weight") if isinstance(catboost_artifact, dict) else None

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


def fetch_latest_model_version_record(status: str = "ready") -> Optional[dict]:
    query = sql.SQL(
        """
        SELECT
            id,
            version_name,
            schema_version,
            status,
            is_current,
            catboost_artifact_path,
            naive_bayes_artifact_path,
            trained_at,
            activated_at
        FROM {table_name}
        WHERE status = %s
        ORDER BY trained_at DESC NULLS LAST, id DESC
        LIMIT 1
        """
    ).format(table_name=sql.Identifier(MODEL_VERSIONS_TABLE))

    with psycopg2.connect(**db_config()) as conn:
        with conn.cursor(cursor_factory=RealDictCursor) as cursor:
            cursor.execute(query, (status,))
            row = cursor.fetchone()
            return dict(row) if row else None


def fetch_active_model_version_record(status: str = "ready") -> Optional[dict]:
    query = sql.SQL(
        """
        SELECT
            id,
            version_name,
            schema_version,
            status,
            is_current,
            catboost_artifact_path,
            naive_bayes_artifact_path,
            trained_at,
            activated_at
        FROM {table_name}
        WHERE status = %s
          AND is_current = TRUE
        ORDER BY activated_at DESC NULLS LAST, trained_at DESC NULLS LAST, id DESC
        LIMIT 1
        """
    ).format(table_name=sql.Identifier(MODEL_VERSIONS_TABLE))

    with psycopg2.connect(**db_config()) as conn:
        with conn.cursor(cursor_factory=RealDictCursor) as cursor:
            cursor.execute(query, (status,))
            row = cursor.fetchone()
            return dict(row) if row else None


def fetch_model_version_record_by_id(model_version_id: int, status: Optional[str] = None) -> Optional[dict]:
    clauses = [sql.SQL("id = %s")]
    params = [model_version_id]

    if status is not None:
        clauses.append(sql.SQL("status = %s"))
        params.append(status)

    query = sql.SQL(
        """
        SELECT
            id,
            version_name,
            schema_version,
            status,
            is_current,
            catboost_artifact_path,
            naive_bayes_artifact_path,
            trained_at,
            activated_at
        FROM {table_name}
        WHERE {where_clause}
        LIMIT 1
        """
    ).format(
        table_name=sql.Identifier(MODEL_VERSIONS_TABLE),
        where_clause=sql.SQL(" AND ").join(clauses),
    )

    with psycopg2.connect(**db_config()) as conn:
        with conn.cursor(cursor_factory=RealDictCursor) as cursor:
            cursor.execute(query, tuple(params))
            row = cursor.fetchone()
            return dict(row) if row else None


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


def fetch_training_dataframe(schema_version: Optional[int] = None) -> pd.DataFrame:
    query = sql.SQL(
        "SELECT kip, pkh, kks, dtks, sktm, "
        "penghasilan_gabungan, penghasilan_ayah, penghasilan_ibu, "
        "jumlah_tanggungan, anak_ke, status_orangtua, status_rumah, "
        "daya_listrik, label, label_class "
        "FROM {table_name} WHERE is_active = TRUE"
    ).format(table_name=sql.Identifier(TRAINING_TABLE))

    params: tuple[int, ...] = ()
    if schema_version is not None:
        query += sql.SQL(" AND schema_version = %s")
        params = (schema_version,)

    with psycopg2.connect(**db_config()) as conn:
        return pd.read_sql(query.as_string(conn), conn, params=params)


def derive_label_and_confidence(probability_indikasi: float, threshold: float = DEFAULT_POSITIVE_THRESHOLD) -> tuple[str, float]:
    bounded_probability = max(0.0, min(float(probability_indikasi), 1.0))
    bounded_threshold = max(0.0, min(float(threshold), 1.0))
    predicted_label = "Indikasi" if bounded_probability >= bounded_threshold else "Layak"
    confidence = bounded_probability if predicted_label == "Indikasi" else 1.0 - bounded_probability
    return predicted_label, round(confidence, 4)


def probability_for_class(model, features: pd.DataFrame, target_class: int = 1) -> float:
    probabilities = model.predict_proba(features)[0]
    classes = list(getattr(model, "classes_", []))

    if target_class in classes:
        return float(probabilities[classes.index(target_class)])

    if len(probabilities) == 1:
        return float(probabilities[0])

    return float(probabilities[1])


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


def calculate_positive_class_weight(target: pd.Series) -> float:
    class_counts = target.value_counts()
    negative_count = int(class_counts.get(0, 0))
    positive_count = int(class_counts.get(1, 0))

    if positive_count == 0 or negative_count == 0:
        return 1.0

    return max(1.0, round(negative_count / positive_count, 4))


def compute_class_prior(y: pd.Series) -> list[float]:
    """Compute class prior probabilities from label distribution."""
    counts = y.value_counts().sort_index()
    total = int(counts.sum())
    if len(counts) < 2 or total == 0:
        return [0.5, 0.5]
    return [int(counts.get(0, 1)) / total, int(counts.get(1, 1)) / total]


def select_threshold_with_cv(
    model_class,
    model_params: dict,
    X: pd.DataFrame,
    y: pd.Series,
    cat_features: list[str],
    n_splits: int = 5,
    is_naive_bayes: bool = False,
    beta: float = POSITIVE_F_BETA,
) -> dict:
    """Use Stratified K-Fold cross-validation for more robust threshold selection.

    This avoids overfitting the threshold to a tiny holdout set.
    """
    if len(y) < n_splits * 2:
        return {
            "threshold": DEFAULT_POSITIVE_THRESHOLD,
            "fbeta": 0.0,
            "balanced_accuracy": 0.0,
            "positive_recall": 0.0,
            "positive_precision": 0.0,
        }

    skf = StratifiedKFold(n_splits=n_splits, shuffle=True, random_state=42)
    all_probabilities = []
    all_labels = []

    for train_idx, val_idx in skf.split(X, y):
        X_fold_train = X.iloc[train_idx]
        y_fold_train = y.iloc[train_idx]
        X_fold_val = X.iloc[val_idx]
        y_fold_val = y.iloc[val_idx]

        if is_naive_bayes:
            X_fold_train = transform_features_for_naive_bayes(X_fold_train)
            X_fold_val = transform_features_for_naive_bayes(X_fold_val)
            fold_model = model_class(**model_params)
            fold_model.fit(X_fold_train, y_fold_train)
        else:
            fold_model = model_class(**model_params)
            fold_model.fit(X_fold_train, y_fold_train, cat_features=cat_features)

        for idx in range(len(X_fold_val)):
            prob = probability_for_class(fold_model, X_fold_val.iloc[[idx]], target_class=1)
            all_probabilities.append(prob)
            all_labels.append(int(y_fold_val.iloc[idx]))

    y_cv = pd.Series(all_labels)
    return select_optimal_indikasi_threshold(y_cv, all_probabilities, beta=beta)


def select_optimal_indikasi_threshold(y_true: pd.Series, probabilities, beta: float = POSITIVE_F_BETA) -> dict:
    default_metrics = {
        "threshold": DEFAULT_POSITIVE_THRESHOLD,
        "fbeta": 0.0,
        "balanced_accuracy": 0.0,
        "positive_recall": 0.0,
        "positive_precision": 0.0,
    }

    if y_true is None or len(y_true) == 0:
        return default_metrics

    y_true_values = [int(value) for value in list(y_true)]
    candidate_thresholds = {DEFAULT_POSITIVE_THRESHOLD}
    candidate_thresholds.update(round(index / 100, 2) for index in range(5, 96, 5))
    candidate_thresholds.update(round(float(probability), 4) for probability in probabilities)

    best = None

    for threshold in sorted(candidate_thresholds):
        predictions = [1 if float(probability) >= threshold else 0 for probability in probabilities]

        positive_true = sum(1 for actual in y_true_values if actual == 1)
        true_positive = sum(1 for actual, predicted in zip(y_true_values, predictions) if actual == 1 and predicted == 1)
        predicted_positive = sum(predictions)

        positive_recall = (true_positive / positive_true) if positive_true else 0.0
        positive_precision = (true_positive / predicted_positive) if predicted_positive else 0.0
        fbeta = float(fbeta_score(y_true_values, predictions, beta=beta, zero_division=0))
        balanced_accuracy = float(balanced_accuracy_score(y_true_values, predictions))

        candidate = {
            "threshold": round(float(threshold), 4),
            "fbeta": round(fbeta, 4),
            "balanced_accuracy": round(balanced_accuracy, 4),
            "positive_recall": round(positive_recall, 4),
            "positive_precision": round(positive_precision, 4),
        }

        if best is None:
            best = candidate
            continue

        current_rank = (
            candidate["fbeta"],
            candidate["positive_recall"],
            candidate["balanced_accuracy"],
            -candidate["threshold"],
        )
        best_rank = (
            best["fbeta"],
            best["positive_recall"],
            best["balanced_accuracy"],
            -best["threshold"],
        )

        if current_rank > best_rank:
            best = candidate

    return best or default_metrics


def build_evaluation_metrics(y_true: pd.Series, probabilities, threshold: float, evaluation_dataset: str) -> dict:
    y_true_values = [int(value) for value in list(y_true)]
    predicted_values = [1 if float(probability) >= threshold else 0 for probability in probabilities]
    tn, fp, fn, tp = confusion_matrix(y_true_values, predicted_values, labels=[0, 1]).ravel()

    return {
        "evaluation_dataset": evaluation_dataset,
        "threshold": round(float(threshold), 4),
        "accuracy": round(float(accuracy_score(y_true_values, predicted_values)), 4),
        "balanced_accuracy": round(float(balanced_accuracy_score(y_true_values, predicted_values)), 4),
        "precision_indikasi": round(float(precision_score(y_true_values, predicted_values, zero_division=0)), 4),
        "recall_indikasi": round(float(recall_score(y_true_values, predicted_values, zero_division=0)), 4),
        "f1_indikasi": round(float(f1_score(y_true_values, predicted_values, zero_division=0)), 4),
        "fbeta_indikasi": round(float(fbeta_score(y_true_values, predicted_values, beta=POSITIVE_F_BETA, zero_division=0)), 4),
        "confusion_matrix": {
            "tn": int(tn),
            "fp": int(fp),
            "fn": int(fn),
            "tp": int(tp),
        },
    }


def transform_features_for_naive_bayes(features: pd.DataFrame) -> pd.DataFrame:
    transformed = features.copy().astype(int)
    transformed.loc[:, ORDINAL_FEATURES] = transformed[ORDINAL_FEATURES] - 1
    return transformed


def infer_with_dual_model(features: pd.DataFrame) -> dict:
    ensure_latest_models_loaded()
    catboost_artifact = MODEL_REGISTRY["catboost"]
    nb_artifact = MODEL_REGISTRY["naive_bayes"]
    catboost_model, catboost_threshold = extract_model_payload(catboost_artifact)
    nb_model, naive_bayes_threshold = extract_model_payload(nb_artifact)
    model_ready = catboost_model is not None and nb_model is not None

    if model_ready:
        cb_probability = probability_for_class(catboost_model, features, target_class=1)
        nb_features = transform_features_for_naive_bayes(features)
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


def build_version_name(schema_version: Optional[int], trained_at: datetime, status: str = "ready") -> str:
    schema_token = f"schema-v{schema_version}" if schema_version else "schema-all"
    suffix = trained_at.strftime("%Y%m%dT%H%M%S%fZ")
    return f"{status}-{schema_token}-{suffix}"


def split_training_dataset(features: pd.DataFrame, target: pd.Series):
    class_counts = target.value_counts()
    class_total = int(target.nunique())
    holdout_rows = max(int(round(len(target) * MODEL_VALIDATION_SPLIT)), class_total)
    can_holdout = (
        class_counts.min() >= 2
        and holdout_rows < len(target)
        and (len(target) - holdout_rows) >= class_total
    )

    if not can_holdout:
        return features, None, target, None, "train_only_fallback_small_dataset"

    x_train, x_valid, y_train, y_valid = train_test_split(
        features,
        target,
        test_size=holdout_rows,
        random_state=42,
        stratify=target,
    )

    return x_train, x_valid, y_train, y_valid, f"holdout_{holdout_rows}_rows_stratified"


def rounded_or_none(value: Optional[float]) -> Optional[float]:
    if value is None:
        return None

    return round(float(value), 4)


def persist_model_version_record(payload: dict) -> dict:
    query = sql.SQL(
        """
        INSERT INTO {table_name} (
            version_name,
            schema_version,
            status,
            is_current,
            triggered_by_user_id,
            triggered_by_email,
            training_table,
            primary_model,
            secondary_model,
            dataset_rows_total,
            rows_used,
            train_rows,
            validation_rows,
            validation_strategy,
            class_distribution,
            catboost_artifact_path,
            naive_bayes_artifact_path,
            catboost_train_accuracy,
            catboost_validation_accuracy,
            naive_bayes_train_accuracy,
            naive_bayes_validation_accuracy,
            catboost_metrics,
            naive_bayes_metrics,
            note,
            error_message,
            trained_at,
            activated_at,
            created_at,
            updated_at
        ) VALUES (
            %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s
        )
        RETURNING id, version_name, schema_version, status, is_current, trained_at, activated_at
        """
    ).format(table_name=sql.Identifier(MODEL_VERSIONS_TABLE))

    now = datetime.now(timezone.utc)

    params = (
        payload["version_name"],
        payload.get("schema_version"),
        payload.get("status", "ready"),
        bool(payload.get("is_current", False)),
        payload.get("triggered_by_user_id"),
        payload.get("triggered_by_email"),
        payload.get("training_table", TRAINING_TABLE),
        payload.get("primary_model", "catboost"),
        payload.get("secondary_model", "categorical_nb"),
        payload.get("dataset_rows_total"),
        payload.get("rows_used"),
        payload.get("train_rows"),
        payload.get("validation_rows"),
        payload.get("validation_strategy"),
        Json(payload.get("class_distribution")) if payload.get("class_distribution") is not None else None,
        payload.get("catboost_artifact_path"),
        payload.get("naive_bayes_artifact_path"),
        payload.get("catboost_train_accuracy"),
        payload.get("catboost_validation_accuracy"),
        payload.get("naive_bayes_train_accuracy"),
        payload.get("naive_bayes_validation_accuracy"),
        Json(payload.get("catboost_metrics")) if payload.get("catboost_metrics") is not None else None,
        Json(payload.get("naive_bayes_metrics")) if payload.get("naive_bayes_metrics") is not None else None,
        payload.get("note"),
        payload.get("error_message"),
        payload.get("trained_at"),
        payload.get("activated_at"),
        now,
        now,
    )

    with psycopg2.connect(**db_config()) as conn:
        with conn.cursor(cursor_factory=RealDictCursor) as cursor:
            cursor.execute(query, params)
            row = cursor.fetchone()
            return dict(row)


def mark_model_version_as_current(model_version_id: int, activated_at: Optional[datetime] = None) -> dict:
    activation_time = activated_at or datetime.now(timezone.utc)

    with psycopg2.connect(**db_config()) as conn:
        with conn.cursor(cursor_factory=RealDictCursor) as cursor:
            cursor.execute(
                sql.SQL(
                    """
                    UPDATE {table_name}
                    SET is_current = FALSE, updated_at = %s
                    WHERE is_current = TRUE
                    """
                ).format(table_name=sql.Identifier(MODEL_VERSIONS_TABLE)),
                (activation_time,),
            )

            cursor.execute(
                sql.SQL(
                    """
                    UPDATE {table_name}
                    SET is_current = TRUE, activated_at = %s, updated_at = %s
                    WHERE id = %s AND status = 'ready'
                    RETURNING id, version_name, schema_version, status, is_current, catboost_artifact_path, naive_bayes_artifact_path, trained_at, activated_at
                    """
                ).format(table_name=sql.Identifier(MODEL_VERSIONS_TABLE)),
                (activation_time, activation_time, model_version_id),
            )
            row = cursor.fetchone()

        conn.commit()

    if not row:
        raise ValueError("Versi model siap tidak ditemukan atau tidak dapat diaktifkan.")

    return dict(row)


def activate_model_version(model_version_id: int) -> dict:
    target_version = fetch_model_version_record_by_id(model_version_id, status="ready")
    if target_version is None:
        raise ValueError("Versi model siap tidak ditemukan.")

    if not load_models_from_version_record(target_version, persist_canonical=True):
        raise ValueError("Artifact model untuk versi yang dipilih tidak ditemukan.")

    activated_record = mark_model_version_as_current(model_version_id)
    MODEL_REGISTRY["metadata"] = activated_record

    return activated_record


def register_failed_retrain(
    schema_version: Optional[int],
    triggered_by_user_id: Optional[int],
    triggered_by_email: Optional[str],
    error_message: str,
) -> None:
    trained_at = datetime.now(timezone.utc)
    payload = {
        "version_name": build_version_name(schema_version, trained_at, status="failed"),
        "schema_version": schema_version or 1,
        "status": "failed",
        "is_current": False,
        "triggered_by_user_id": triggered_by_user_id,
        "triggered_by_email": triggered_by_email,
        "training_table": TRAINING_TABLE,
        "primary_model": "catboost",
        "secondary_model": "categorical_nb",
        "note": "Retrain gagal sebelum model baru diaktifkan.",
        "error_message": error_message,
        "trained_at": trained_at,
        "activated_at": None,
    }

    try:
        persist_model_version_record(payload)
    except Exception:
        pass


def train_and_save_models(
    dataframe: pd.DataFrame,
    schema_version: Optional[int] = None,
    triggered_by_user_id: Optional[int] = None,
    triggered_by_email: Optional[str] = None,
) -> dict:
    dataset_rows_total = int(len(dataframe))

    if dataframe.empty:
        raise ValueError("Data training kosong. Tambahkan data valid terlebih dahulu.")

    missing = [column for column in FEATURE_COLUMNS if column not in dataframe.columns]
    if missing:
        raise ValueError(f"Kolom training tidak lengkap: {', '.join(missing)}")

    cleaned = dataframe.dropna(subset=FEATURE_COLUMNS).copy()
    if cleaned.empty:
        raise ValueError("Data training tidak valid setelah pembersihan nilai kosong.")

    if TARGET_COLUMN in cleaned.columns:
        if cleaned[TARGET_COLUMN].isnull().any():
            if "label" not in cleaned.columns:
                raise ValueError("Kolom label_class mengandung nilai kosong dan kolom label tidak tersedia.")
            cleaned[TARGET_COLUMN] = cleaned[TARGET_COLUMN].fillna(
                cleaned["label"].apply(normalize_label)
            )
    elif "label" in cleaned.columns:
        cleaned[TARGET_COLUMN] = cleaned["label"].apply(normalize_label)
    else:
        raise ValueError("Kolom target tidak tersedia. Minimal butuh label atau label_class.")

    cleaned[TARGET_COLUMN] = cleaned[TARGET_COLUMN].astype(int)
    if cleaned[TARGET_COLUMN].nunique() < 2:
        raise ValueError("Data training harus memiliki minimal 2 kelas label.")

    x_full = cleaned[FEATURE_COLUMNS].astype(int)
    y_full = cleaned[TARGET_COLUMN].astype(int)
    x_train, x_valid, y_train, y_valid, validation_strategy = split_training_dataset(x_full, y_full)
    nb_x_train = transform_features_for_naive_bayes(x_train)
    nb_x_valid = transform_features_for_naive_bayes(x_valid) if x_valid is not None else None

    positive_class_weight = calculate_positive_class_weight(y_train)

    # ─── CatBoost: binary fitur sebagai kategorikal, ordinal sebagai numerik ─────
    # Binary features (kip, pkh, kks, dtks, sktm) diperlakukan sebagai kategorikal
    # karena tidak ada urutan bermakna antara 0 dan 1.
    # Ordinal features (penghasilan, tanggungan, dll) diperlakukan sebagai numerik
    # karena urutan 1→rendah, 2→sedang, 3→tinggi bermakna untuk prediksi.
    catboost_model = CatBoostClassifier(
        iterations=500,
        depth=6,
        learning_rate=0.03,
        l2_leaf_reg=7,
        random_strength=1,
        bagging_temperature=1,
        border_count=64,
        verbose=False,
        random_seed=42,
        eval_metric="F1",
        class_weights=[1.0, positive_class_weight],
        auto_class_weights=None,
    )

    if x_valid is not None and y_valid is not None:
        catboost_model.fit(
            x_train, y_train,
            cat_features=BINARY_FEATURES,
            eval_set=(x_valid, y_valid),
            early_stopping_rounds=50,
            verbose=False,
        )
    else:
        catboost_model.fit(x_train, y_train, cat_features=BINARY_FEATURES)

    cb_train_accuracy = float(accuracy_score(y_train, catboost_model.predict(x_train)))
    cb_valid_accuracy = None

    # ─── Threshold selection via Stratified K-Fold CV (lebih robust) ─────────────
    # Menggunakan seluruh data x_full/y_full untuk CV-based threshold selection
    # agar threshold tidak overfit ke satu holdout split.
    cb_threshold_metrics = select_threshold_with_cv(
        CatBoostClassifier,
        {
            "iterations": 500,
            "depth": 6,
            "learning_rate": 0.03,
            "l2_leaf_reg": 7,
            "random_strength": 1,
            "bagging_temperature": 1,
            "border_count": 64,
            "verbose": False,
            "random_seed": 42,
            "eval_metric": "F1",
            "class_weights": [1.0, positive_class_weight],
            "auto_class_weights": None,
        },
        x_full,
        y_full,
        cat_features=BINARY_FEATURES,
        n_splits=5,
        is_naive_bayes=False,
        beta=POSITIVE_F_BETA,
    )

    # Clamp threshold minimum 0.35 agar tidak terlalu agresif
    if cb_threshold_metrics["threshold"] < 0.35:
        cb_threshold_metrics["threshold"] = DEFAULT_POSITIVE_THRESHOLD

    if x_valid is not None and y_valid is not None:
        cb_valid_accuracy = float(accuracy_score(y_valid, catboost_model.predict(x_valid)))
        cb_valid_probabilities = [
            probability_for_class(catboost_model, x_valid.iloc[[index]], target_class=1)
            for index in range(len(x_valid))
        ]
        cb_evaluation_metrics = build_evaluation_metrics(
            y_valid,
            cb_valid_probabilities,
            cb_threshold_metrics["threshold"],
            "validation",
        )
    else:
        cb_train_probabilities = [
            probability_for_class(catboost_model, x_train.iloc[[index]], target_class=1)
            for index in range(len(x_train))
        ]
        cb_evaluation_metrics = build_evaluation_metrics(
            y_train,
            cb_train_probabilities,
            cb_threshold_metrics["threshold"],
            "training",
        )

    # ─── Naive Bayes: dengan Laplace smoothing dan class prior proporsional ─────
    class_prior = compute_class_prior(y_train)
    # min_categories memastikan NB mengenali semua kemungkinan nilai fitur,
    # bahkan jika suatu nilai (misal dtks=1) tidak pernah muncul di data training.
    # Binary features: 2 kategori (0/1), ordinal features setelah transform: 3 kategori (0/1/2).
    nb_min_categories = [2, 2, 2, 2, 2, 3, 3, 3, 3, 3, 3, 3, 3]
    nb_params = {
        "fit_prior": True,
        "class_prior": class_prior,
        "alpha": 1.0,
        "min_categories": nb_min_categories,
    }
    naive_bayes_model = CategoricalNB(**nb_params)
    naive_bayes_model.fit(nb_x_train, y_train)
    nb_train_accuracy = float(accuracy_score(y_train, naive_bayes_model.predict(nb_x_train)))
    nb_valid_accuracy = None

    # ─── NB Threshold via Stratified K-Fold CV ──────────────────────────────────
    nb_threshold_metrics = select_threshold_with_cv(
        CategoricalNB,
        nb_params,
        x_full,
        y_full,
        cat_features=BINARY_FEATURES,
        n_splits=5,
        is_naive_bayes=True,
        beta=POSITIVE_F_BETA,
    )

    if nb_threshold_metrics["threshold"] < 0.35:
        nb_threshold_metrics["threshold"] = DEFAULT_POSITIVE_THRESHOLD

    if nb_x_valid is not None and y_valid is not None:
        nb_valid_accuracy = float(accuracy_score(y_valid, naive_bayes_model.predict(nb_x_valid)))
        nb_valid_probabilities = [
            probability_for_class(naive_bayes_model, nb_x_valid.iloc[[index]], target_class=1)
            for index in range(len(nb_x_valid))
        ]
        nb_evaluation_metrics = build_evaluation_metrics(
            y_valid,
            nb_valid_probabilities,
            nb_threshold_metrics["threshold"],
            "validation",
        )
    else:
        nb_train_probabilities = [
            probability_for_class(naive_bayes_model, nb_x_train.iloc[[index]], target_class=1)
            for index in range(len(nb_x_train))
        ]
        nb_evaluation_metrics = build_evaluation_metrics(
            y_train,
            nb_train_probabilities,
            nb_threshold_metrics["threshold"],
            "training",
        )

    ensure_model_directory()
    trained_at = datetime.now(timezone.utc)
    version_name = build_version_name(schema_version, trained_at, status="ready")
    catboost_versioned_path = CATBOOST_MODEL_PATH.parent / f"catboost_{version_name}.joblib"
    naive_bayes_versioned_path = NAIVE_BAYES_MODEL_PATH.parent / f"naive_bayes_{version_name}.joblib"

    catboost_artifact = create_model_artifact(
        catboost_model,
        cb_threshold_metrics["threshold"],
        {
            "positive_class_weight": positive_class_weight,
        },
    )
    naive_bayes_artifact = create_model_artifact(
        naive_bayes_model,
        nb_threshold_metrics["threshold"],
    )

    joblib.dump(catboost_artifact, catboost_versioned_path)
    joblib.dump(naive_bayes_artifact, naive_bayes_versioned_path)

    class_distribution = {
        str(key): int(value)
        for key, value in cleaned[TARGET_COLUMN].value_counts().to_dict().items()
    }

    note = (
        "CatBoost adalah model primer (depth=6, lr=0.03, l2_leaf_reg=7, cat_features=binary_only). "
        "Categorical Naive Bayes (alpha=1.0, fit_prior=True, min_categories=enforced) "
        "digunakan sebagai pembanding untuk deteksi disagreement. "
        "Threshold dipilih via Stratified 5-Fold CV."
    )
    note += (
        f" Threshold indikasi CatBoost={cb_threshold_metrics['threshold']:.4f}, "
        f"Naive Bayes={nb_threshold_metrics['threshold']:.4f}. "
        f"CatBoost class weight positif={positive_class_weight:.4f}."
    )
    if validation_strategy.startswith("train_only_fallback"):
        note += " Validation split belum dipakai karena dataset final masih terlalu kecil atau distribusi kelas belum aman."

    version_record = persist_model_version_record(
        {
            "version_name": version_name,
            "schema_version": schema_version or 1,
            "status": "ready",
            "is_current": True,
            "triggered_by_user_id": triggered_by_user_id,
            "triggered_by_email": triggered_by_email,
            "training_table": TRAINING_TABLE,
            "primary_model": "catboost",
            "secondary_model": "categorical_nb",
            "dataset_rows_total": dataset_rows_total,
            "rows_used": int(len(cleaned)),
            "train_rows": int(len(x_train)),
            "validation_rows": int(len(x_valid)) if x_valid is not None else 0,
            "validation_strategy": validation_strategy,
            "class_distribution": class_distribution,
            "catboost_artifact_path": relative_artifact_path(catboost_versioned_path),
            "naive_bayes_artifact_path": relative_artifact_path(naive_bayes_versioned_path),
            "catboost_train_accuracy": rounded_or_none(cb_train_accuracy),
            "catboost_validation_accuracy": rounded_or_none(cb_valid_accuracy),
            "naive_bayes_train_accuracy": rounded_or_none(nb_train_accuracy),
            "naive_bayes_validation_accuracy": rounded_or_none(nb_valid_accuracy),
            "catboost_metrics": cb_evaluation_metrics,
            "naive_bayes_metrics": nb_evaluation_metrics,
            "note": note,
            "trained_at": trained_at,
            "activated_at": trained_at,
        }
    )

    joblib.dump(catboost_artifact, CATBOOST_MODEL_PATH)
    joblib.dump(naive_bayes_artifact, NAIVE_BAYES_MODEL_PATH)

    activated_record = mark_model_version_as_current(version_record["id"], activated_at=trained_at)

    MODEL_REGISTRY["catboost"] = catboost_artifact
    MODEL_REGISTRY["naive_bayes"] = naive_bayes_artifact
    MODEL_REGISTRY["metadata"] = {
        **activated_record,
        "catboost_artifact_path": relative_artifact_path(catboost_versioned_path),
        "naive_bayes_artifact_path": relative_artifact_path(naive_bayes_versioned_path),
        "catboost_threshold": cb_threshold_metrics["threshold"],
        "naive_bayes_threshold": nb_threshold_metrics["threshold"],
        "positive_class_weight": positive_class_weight,
    }

    effective_cb_accuracy = cb_valid_accuracy if cb_valid_accuracy is not None else cb_train_accuracy
    effective_nb_accuracy = nb_valid_accuracy if nb_valid_accuracy is not None else nb_train_accuracy

    return {
        "model_version_id": version_record["id"],
        "model_version_name": version_name,
        "rows_received": dataset_rows_total,
        "rows_used": int(len(cleaned)),
        "train_rows": int(len(x_train)),
        "validation_rows": int(len(x_valid)) if x_valid is not None else 0,
        "validation_strategy": validation_strategy,
        "class_distribution": class_distribution,
        "catboost_training_accuracy": rounded_or_none(cb_train_accuracy),
        "catboost_validation_accuracy": rounded_or_none(cb_valid_accuracy),
        "naive_bayes_training_accuracy": rounded_or_none(nb_train_accuracy),
        "naive_bayes_validation_accuracy": rounded_or_none(nb_valid_accuracy),
        "catboost_threshold": cb_threshold_metrics["threshold"],
        "naive_bayes_threshold": nb_threshold_metrics["threshold"],
        "catboost_threshold_fbeta": cb_threshold_metrics["fbeta"],
        "naive_bayes_threshold_fbeta": nb_threshold_metrics["fbeta"],
        "positive_class_weight": positive_class_weight,
        "catboost_metrics": cb_evaluation_metrics,
        "naive_bayes_metrics": nb_evaluation_metrics,
        "catboost_accuracy": rounded_or_none(effective_cb_accuracy),
        "naive_bayes_accuracy": rounded_or_none(effective_nb_accuracy),
        "primary_model": "catboost",
        "secondary_model": "categorical_nb",
        "note": note,
    }


def get_prediction_input(payload: dict) -> pd.DataFrame:
    missing_fields = [field for field in FEATURE_COLUMNS if field not in payload]
    if missing_fields:
        raise ValueError(f"Field wajib tidak lengkap: {', '.join(missing_fields)}")

    try:
        values = {}

        for feature in BINARY_FEATURES:
            parsed_value = int(payload[feature])
            if parsed_value not in (0, 1):
                raise ValueError(f"Field {feature} wajib bernilai 0 atau 1 (biner)")
            values[feature] = parsed_value

        for feature in ORDINAL_FEATURES:
            parsed_value = int(payload[feature])
            if parsed_value not in (1, 2, 3):
                raise ValueError(f"Field {feature} wajib bernilai 1, 2, atau 3 (ordinal)")
            values[feature] = parsed_value
    except (TypeError, ValueError) as exc:
        raise ValueError("Nilai fitur harus berupa angka yang valid") from exc

    return pd.DataFrame([values], columns=FEATURE_COLUMNS)


@app.route("/api/health", methods=["GET"])
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


@app.route("/api/predict", methods=["POST"])
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


@app.route("/api/models/activate", methods=["POST"])
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

        activated = activate_model_version(model_version_id)

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


@app.route("/api/retrain", methods=["POST"])
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

        dataframe = fetch_training_dataframe(schema_version=schema_version)
        training_result = train_and_save_models(
            dataframe,
            schema_version=schema_version,
            triggered_by_user_id=triggered_by_user_id,
            triggered_by_email=triggered_by_email,
        )

        return (
            jsonify({
                "status": "success",
                "message": "Retrain model berhasil dijalankan",
                "schema_version": schema_version,
                "training_summary": training_result,
            }),
            200,
        )
    except ValueError as exc:
        register_failed_retrain(schema_version, triggered_by_user_id, triggered_by_email, str(exc))
        return jsonify({"status": "error", "message": str(exc)}), 400
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


try:
    load_saved_models()
except Exception:
    pass


if __name__ == "__main__":
    flask_host = os.getenv("FLASK_HOST", "0.0.0.0")
    flask_port = int(os.getenv("FLASK_PORT", "5000"))
    app.run(host=flask_host, port=flask_port)
