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
from sklearn.metrics import accuracy_score
from sklearn.model_selection import train_test_split
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

    return {
        "model_version_id": metadata.get("id"),
        "model_version_name": metadata.get("version_name"),
        "model_trained_at": serialize_datetime(metadata.get("trained_at")),
        "model_schema_version": metadata.get("schema_version"),
        "model_is_current": bool(metadata.get("is_current", False)),
        "model_activated_at": serialize_datetime(metadata.get("activated_at")),
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

    catboost_model = joblib.load(catboost_path)
    naive_bayes_model = joblib.load(naive_bayes_path)

    if persist_canonical:
        joblib.dump(catboost_model, CATBOOST_MODEL_PATH)
        joblib.dump(naive_bayes_model, NAIVE_BAYES_MODEL_PATH)

    MODEL_REGISTRY["catboost"] = catboost_model
    MODEL_REGISTRY["naive_bayes"] = naive_bayes_model
    MODEL_REGISTRY["metadata"] = metadata

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


def derive_label_and_confidence(probability_indikasi: float) -> tuple[str, float]:
    bounded_probability = max(0.0, min(float(probability_indikasi), 1.0))
    predicted_label = "Indikasi" if bounded_probability >= 0.5 else "Layak"
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


def transform_features_for_naive_bayes(features: pd.DataFrame) -> pd.DataFrame:
    transformed = features.copy().astype(int)
    transformed.loc[:, ORDINAL_FEATURES] = transformed[ORDINAL_FEATURES] - 1
    return transformed


def infer_with_dual_model(features: pd.DataFrame) -> dict:
    ensure_latest_models_loaded()
    catboost_model = MODEL_REGISTRY["catboost"]
    nb_model = MODEL_REGISTRY["naive_bayes"]
    model_ready = catboost_model is not None and nb_model is not None

    if model_ready:
        cb_probability = probability_for_class(catboost_model, features, target_class=1)
        nb_features = transform_features_for_naive_bayes(features)
        nb_probability = probability_for_class(nb_model, nb_features, target_class=1)

        pred_cb, confidence_cb = derive_label_and_confidence(cb_probability)
        pred_nb, confidence_nb = derive_label_and_confidence(nb_probability)
    else:
        pred_cb, confidence_cb = "Indikasi", 0.5
        pred_nb, confidence_nb = "Indikasi", 0.5

    disagreement_flag = pred_cb != pred_nb
    final_recommendation = pred_cb
    review_priority = "high" if disagreement_flag or confidence_cb < REVIEW_PRIORITY_THRESHOLD else "normal"
    metadata = current_model_metadata()

    model_results = {
        "catboost": {"label": pred_cb, "confidence": confidence_cb},
        "naive_bayes": {"label": pred_nb, "confidence": confidence_nb},
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
            note,
            error_message,
            trained_at,
            activated_at,
            created_at,
            updated_at
        ) VALUES (
            %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s
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

    catboost_model = CatBoostClassifier(
        iterations=150,
        depth=5,
        learning_rate=0.08,
        verbose=False,
        random_seed=42,
        eval_metric="Accuracy",
    )
    catboost_model.fit(x_train, y_train, cat_features=FEATURE_COLUMNS)
    cb_train_accuracy = float(accuracy_score(y_train, catboost_model.predict(x_train)))
    cb_valid_accuracy = None
    if x_valid is not None and y_valid is not None:
        cb_valid_accuracy = float(accuracy_score(y_valid, catboost_model.predict(x_valid)))

    naive_bayes_model = CategoricalNB()
    naive_bayes_model.fit(nb_x_train, y_train)
    nb_train_accuracy = float(accuracy_score(y_train, naive_bayes_model.predict(nb_x_train)))
    nb_valid_accuracy = None
    if nb_x_valid is not None and y_valid is not None:
        nb_valid_accuracy = float(accuracy_score(y_valid, naive_bayes_model.predict(nb_x_valid)))

    ensure_model_directory()
    trained_at = datetime.now(timezone.utc)
    version_name = build_version_name(schema_version, trained_at, status="ready")
    catboost_versioned_path = CATBOOST_MODEL_PATH.parent / f"catboost_{version_name}.joblib"
    naive_bayes_versioned_path = NAIVE_BAYES_MODEL_PATH.parent / f"naive_bayes_{version_name}.joblib"

    joblib.dump(catboost_model, catboost_versioned_path)
    joblib.dump(naive_bayes_model, naive_bayes_versioned_path)

    class_distribution = {
        str(key): int(value)
        for key, value in cleaned[TARGET_COLUMN].value_counts().to_dict().items()
    }

    note = (
        "CatBoost adalah model primer. "
        "Categorical Naive Bayes digunakan sebagai pembanding untuk deteksi disagreement."
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
            "note": note,
            "trained_at": trained_at,
            "activated_at": trained_at,
        }
    )

    joblib.dump(catboost_model, CATBOOST_MODEL_PATH)
    joblib.dump(naive_bayes_model, NAIVE_BAYES_MODEL_PATH)

    activated_record = mark_model_version_as_current(version_record["id"], activated_at=trained_at)

    MODEL_REGISTRY["catboost"] = catboost_model
    MODEL_REGISTRY["naive_bayes"] = naive_bayes_model
    MODEL_REGISTRY["metadata"] = {
        **activated_record,
        "catboost_artifact_path": relative_artifact_path(catboost_versioned_path),
        "naive_bayes_artifact_path": relative_artifact_path(naive_bayes_versioned_path),
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
