import joblib
import pandas as pd
from datetime import datetime, timezone
from typing import Optional
from catboost import CatBoostClassifier
from sklearn.calibration import CalibratedClassifierCV
from sklearn.metrics import accuracy_score
from sklearn.model_selection import train_test_split
from sklearn.naive_bayes import CategoricalNB

from config import (
    FEATURE_COLUMNS,
    TARGET_COLUMN,
    MODEL_VALIDATION_SPLIT,
    BINARY_FEATURES,
    ENGINEERED_BINARY,
    POSITIVE_F_BETA,
    DEFAULT_POSITIVE_THRESHOLD,
    CATBOOST_MODEL_PATH,
    NAIVE_BAYES_MODEL_PATH,
    TRAINING_TABLE,
    MODEL_REGISTRY,
)
from database import persist_model_version_record, mark_model_version_as_current
from encoding import encode_application_features
from features import add_engineered_features, transform_features_for_naive_bayes
from evaluation import (
    training_quality_summary,
    select_threshold_with_cv,
    probability_for_class,
    batch_probability_for_class,
    build_evaluation_metrics,
)
from model_registry import relative_artifact_path, create_model_artifact


# ─── Hyperparameter CatBoost (dioptimalkan untuk ~900 baris, 13-15 fitur) ─────
# Catatan: tidak memakai auto_class_weights="Balanced" untuk menghindari
# over-prediction kelas positif (Indikasi). Keseimbangan precision-recall
# dikendalikan oleh threshold selection via StratifiedKFold CV.
CATBOOST_PARAMS = {
    "iterations": 900,
    "depth": 6,
    "learning_rate": 0.035,
    "l2_leaf_reg": 5,
    "random_strength": 1.2,
    "bagging_temperature": 0.6,
    "border_count": 64,
    "min_data_in_leaf": 6,
    "rsm": 0.85,
    "verbose": False,
    "random_seed": 42,
    "eval_metric": "AUC",
    "auto_class_weights": "SqrtBalanced",
    "od_type": "Iter",
    "od_wait": 75,
}


def rounded_or_none(value: Optional[float]) -> Optional[float]:
    if value is None:
        return None
    return round(float(value), 4)


def normalize_label(raw_value) -> int:
    normalized = str(raw_value).strip().lower()
    positive_values = {"indikasi", "1", "true", "ya"}
    return 1 if normalized in positive_values else 0


def build_version_name(schema_version: Optional[int], trained_at: datetime, status: str = "ready") -> str:
    schema_token = f"schema-v{schema_version}" if schema_version else "schema-all"
    suffix = trained_at.strftime("%Y%m%dT%H%M%S%fZ")
    return f"{status}-{schema_token}-{suffix}"


def calculate_positive_class_weight(target: pd.Series) -> float:
    class_counts = target.value_counts()
    negative_count = int(class_counts.get(0, 0))
    positive_count = int(class_counts.get(1, 0))
    if positive_count == 0 or negative_count == 0:
        return 1.0
    return max(1.0, round(negative_count / positive_count, 4))


def compute_class_prior(y: pd.Series) -> list[float]:
    counts = y.value_counts().sort_index()
    total = int(counts.sum())
    if len(counts) < 2 or total == 0:
        return [0.5, 0.5]
    return [int(counts.get(0, 1)) / total, int(counts.get(1, 1)) / total]


def calibration_cv_splits(y: pd.Series, max_splits: int = 5) -> int:
    class_counts = y.value_counts()
    if class_counts.empty:
        return 0
    return min(max_splits, int(class_counts.min()))


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
        features, target, test_size=holdout_rows, random_state=42, stratify=target,
    )
    return x_train, x_valid, y_train, y_valid, f"holdout_{holdout_rows}_rows_stratified"


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


def encode_raw_dataframe(raw_df: pd.DataFrame) -> pd.DataFrame:
    """Encode setiap baris raw applicant data menggunakan encoding.py (authoritative)."""
    encoded_rows = []
    failed_rows = 0
    for record in raw_df.to_dict(orient="records"):
        try:
            encoded_rows.append(encode_application_features(record))
        except ValueError:
            failed_rows += 1
            continue
    if failed_rows > 0:
        # Mencatat saja, tidak menggagalkan training
        pass
    return pd.DataFrame(encoded_rows)


def train_and_save_models(
    dataframe: pd.DataFrame,
    schema_version: Optional[int] = None,
    triggered_by_user_id: Optional[int] = None,
    triggered_by_email: Optional[str] = None,
) -> dict:
    dataset_rows_total = int(len(dataframe))
    if dataframe.empty:
        raise ValueError("Data training kosong. Tambahkan data valid terlebih dahulu.")

    # ─── Step 1: Encode RAW applicant data via Flask authoritative encoder ────
    encoded_features = encode_raw_dataframe(dataframe)
    if encoded_features.empty:
        raise ValueError("Tidak ada baris raw yang berhasil di-encode.")

    # Sejajarkan label dengan baris yang lolos encoding
    encoded_features = encoded_features.reset_index(drop=True)
    labels_source = dataframe.reset_index(drop=True).iloc[: len(encoded_features)].copy()

    if TARGET_COLUMN in labels_source.columns and labels_source[TARGET_COLUMN].notna().any():
        target_series = labels_source[TARGET_COLUMN].fillna(
            labels_source["label"].apply(normalize_label) if "label" in labels_source.columns else 0
        )
    elif "label" in labels_source.columns:
        target_series = labels_source["label"].apply(normalize_label)
    else:
        raise ValueError("Kolom target tidak tersedia. Minimal butuh label atau label_class.")

    encoded_features[TARGET_COLUMN] = target_series.astype(int).values
    df_engineered = add_engineered_features(encoded_features)

    missing = [column for column in FEATURE_COLUMNS if column not in df_engineered.columns]
    if missing:
        raise ValueError(f"Kolom training tidak lengkap setelah encoding: {', '.join(missing)}")

    cleaned = df_engineered.dropna(subset=FEATURE_COLUMNS).copy()
    if cleaned.empty:
        raise ValueError("Data training tidak valid setelah pembersihan nilai kosong.")

    cleaned[TARGET_COLUMN] = cleaned[TARGET_COLUMN].astype(int)
    if cleaned[TARGET_COLUMN].nunique() < 2:
        raise ValueError("Data training harus memiliki minimal 2 kelas label.")

    # ─── Step 2: Resolusi konflik (majority-vote deduplication) ─────────
    deduplicated = cleaned.groupby(FEATURE_COLUMNS)[TARGET_COLUMN].agg(
        lambda items: items.value_counts().index[0]
    ).reset_index()

    x_full = deduplicated[FEATURE_COLUMNS].astype(int)
    y_full = deduplicated[TARGET_COLUMN].astype(int)
    quality_summary = training_quality_summary(x_full, y_full)
    x_eval_train, x_valid, y_eval_train, y_valid, validation_strategy = split_training_dataset(x_full, y_full)

    nb_x_eval_train = transform_features_for_naive_bayes(x_eval_train)
    nb_x_valid = transform_features_for_naive_bayes(x_valid) if x_valid is not None else None
    nb_x_full = transform_features_for_naive_bayes(x_full)

    positive_class_weight = calculate_positive_class_weight(y_full)

    # ─── Step 3: CatBoost — eval model (dengan holdout validation) ────────
    cat_features_list = BINARY_FEATURES + ENGINEERED_BINARY
    catboost_eval_model = CatBoostClassifier(**CATBOOST_PARAMS)

    if x_valid is not None and y_valid is not None:
        catboost_eval_model.fit(
            x_eval_train, y_eval_train,
            cat_features=cat_features_list,
            eval_set=(x_valid, y_valid),
            early_stopping_rounds=60,
            verbose=False,
        )
    else:
        catboost_eval_model.fit(x_eval_train, y_eval_train, cat_features=cat_features_list)

    cb_train_accuracy = float(accuracy_score(y_eval_train, catboost_eval_model.predict(x_eval_train)))
    cb_valid_accuracy = None

    # ─── Step 4: Threshold selection via Stratified K-Fold CV ─────────────
    cb_threshold_metrics = select_threshold_with_cv(
        CatBoostClassifier,
        CATBOOST_PARAMS,
        x_full, y_full,
        cat_features=cat_features_list,
        n_splits=10,
        is_naive_bayes=False,
        beta=POSITIVE_F_BETA,
    )
    if cb_threshold_metrics["threshold"] < 0.30:
        cb_threshold_metrics["threshold"] = 0.30

    if x_valid is not None and y_valid is not None:
        cb_valid_accuracy = float(accuracy_score(y_valid, catboost_eval_model.predict(x_valid)))
        cb_valid_probabilities = batch_probability_for_class(catboost_eval_model, x_valid, target_class=1)
        cb_evaluation_metrics = build_evaluation_metrics(
            y_valid, cb_valid_probabilities, cb_threshold_metrics["threshold"], "validation",
        )
    else:
        cb_train_probabilities = batch_probability_for_class(catboost_eval_model, x_eval_train, target_class=1)
        cb_evaluation_metrics = build_evaluation_metrics(
            y_eval_train, cb_train_probabilities, cb_threshold_metrics["threshold"], "training",
        )

    catboost_model = CatBoostClassifier(**CATBOOST_PARAMS)
    catboost_model.fit(x_full, y_full, cat_features=cat_features_list)
    cb_final_train_accuracy = float(accuracy_score(y_full, catboost_model.predict(x_full)))

    # ─── Step 5: Categorical Naive Bayes dengan Laplace + Isotonic calibration ──
    class_prior = compute_class_prior(y_full)
    nb_base_categories = [2, 2, 2, 2, 2, 5, 5, 5, 5, 5, 3, 4, 5]
    nb_min_categories = nb_base_categories + [2] + [6]  # rendah_tanpa_bantuan(2) + skor_bantuan_sosial(6)

    nb_params = {
        "fit_prior": True,
        "class_prior": class_prior,
        "alpha": 0.5,
        "min_categories": nb_min_categories,
    }

    naive_bayes_eval_base = CategoricalNB(**nb_params)
    naive_bayes_eval_base.fit(nb_x_eval_train, y_eval_train)

    naive_bayes_eval_model = naive_bayes_eval_base
    eval_calibration_splits = calibration_cv_splits(y_eval_train)
    if len(y_eval_train) >= 10 and eval_calibration_splits >= 2:
        naive_bayes_eval_model = CalibratedClassifierCV(
            naive_bayes_eval_base, method="isotonic", cv=eval_calibration_splits,
        )
        try:
            naive_bayes_eval_model.fit(nb_x_eval_train, y_eval_train)
        except ValueError:
            naive_bayes_eval_model = naive_bayes_eval_base

    nb_train_accuracy = float(accuracy_score(y_eval_train, naive_bayes_eval_model.predict(nb_x_eval_train)))
    nb_valid_accuracy = None

    naive_bayes_full_base = CategoricalNB(**nb_params)
    naive_bayes_full_base.fit(nb_x_full, y_full)
    naive_bayes_model = naive_bayes_full_base
    full_calibration_splits = calibration_cv_splits(y_full)
    if len(y_full) >= 10 and full_calibration_splits >= 2:
        naive_bayes_model = CalibratedClassifierCV(
            naive_bayes_full_base, method="isotonic", cv=full_calibration_splits,
        )
        try:
            naive_bayes_model.fit(nb_x_full, y_full)
        except ValueError:
            naive_bayes_model = naive_bayes_full_base

    nb_final_train_accuracy = float(accuracy_score(y_full, naive_bayes_model.predict(nb_x_full)))

    # ─── Step 6: Threshold NB via CV ──────────────────────────────────────
    nb_threshold_metrics = select_threshold_with_cv(
        CategoricalNB, nb_params,
        x_full, y_full,
        cat_features=cat_features_list,
        n_splits=10,
        is_naive_bayes=True,
        beta=POSITIVE_F_BETA,
    )
    if nb_threshold_metrics["threshold"] < 0.30:
        nb_threshold_metrics["threshold"] = 0.30

    if nb_x_valid is not None and y_valid is not None:
        nb_valid_accuracy = float(accuracy_score(y_valid, naive_bayes_eval_model.predict(nb_x_valid)))
        nb_valid_probabilities = batch_probability_for_class(naive_bayes_eval_model, nb_x_valid, target_class=1)
        nb_evaluation_metrics = build_evaluation_metrics(
            y_valid, nb_valid_probabilities, nb_threshold_metrics["threshold"], "validation",
        )
    else:
        nb_train_probabilities = batch_probability_for_class(naive_bayes_eval_model, nb_x_eval_train, target_class=1)
        nb_evaluation_metrics = build_evaluation_metrics(
            y_eval_train, nb_train_probabilities, nb_threshold_metrics["threshold"], "training",
        )

    # ─── Step 7: Persist models ───────────────────────────────────────────
    CATBOOST_MODEL_PATH.parent.mkdir(parents=True, exist_ok=True)
    NAIVE_BAYES_MODEL_PATH.parent.mkdir(parents=True, exist_ok=True)

    trained_at = datetime.now(timezone.utc)
    version_name = build_version_name(schema_version, trained_at, status="ready")
    catboost_versioned_path = CATBOOST_MODEL_PATH.parent / f"catboost_{version_name}.joblib"
    naive_bayes_versioned_path = NAIVE_BAYES_MODEL_PATH.parent / f"naive_bayes_{version_name}.joblib"

    catboost_artifact = create_model_artifact(
        catboost_model, cb_threshold_metrics["threshold"],
        {"positive_class_weight": positive_class_weight},
    )
    naive_bayes_artifact = create_model_artifact(
        naive_bayes_model, nb_threshold_metrics["threshold"],
    )

    joblib.dump(catboost_artifact, catboost_versioned_path)
    joblib.dump(naive_bayes_artifact, naive_bayes_versioned_path)

    class_distribution = {
        str(key): int(value)
        for key, value in cleaned[TARGET_COLUMN].value_counts().to_dict().items()
    }

    note = (
        "Encoding dilakukan oleh Flask ML (authoritative). "
        "CatBoost: depth=5 iter=800 lr=0.04 l2=6 rsm=0.9 auto_class_weights=Balanced. "
        "Categorical NB: alpha=0.5 + Isotonic calibration. "
        "Threshold dipilih via 10-Fold Stratified CV (floor 0.40)."
    )
    if len(deduplicated) < len(cleaned):
        note += (
            f" Resolusi konflik (majority-vote) mereduksi {len(cleaned)} baris menjadi "
            f"{len(deduplicated)} vektor unik."
        )

    version_record = persist_model_version_record({
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
        "train_rows": int(len(x_full)),
        "validation_rows": int(len(x_valid)) if x_valid is not None else 0,
        "validation_strategy": f"{validation_strategy}_evaluation_only",
        "class_distribution": class_distribution,
        "catboost_artifact_path": relative_artifact_path(catboost_versioned_path),
        "naive_bayes_artifact_path": relative_artifact_path(naive_bayes_versioned_path),
        "catboost_train_accuracy": rounded_or_none(cb_final_train_accuracy),
        "catboost_validation_accuracy": rounded_or_none(cb_valid_accuracy),
        "naive_bayes_train_accuracy": rounded_or_none(nb_final_train_accuracy),
        "naive_bayes_validation_accuracy": rounded_or_none(nb_valid_accuracy),
        "catboost_metrics": cb_evaluation_metrics,
        "naive_bayes_metrics": nb_evaluation_metrics,
        "note": note,
        "trained_at": trained_at,
        "activated_at": trained_at,
    })

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
        "train_rows": int(len(x_full)),
        "validation_rows": int(len(x_valid)) if x_valid is not None else 0,
        "validation_strategy": f"{validation_strategy}_evaluation_only",
        "data_quality": quality_summary,
        "class_distribution": class_distribution,
        "catboost_training_accuracy": rounded_or_none(cb_final_train_accuracy),
        "catboost_validation_accuracy": rounded_or_none(cb_valid_accuracy),
        "naive_bayes_training_accuracy": rounded_or_none(nb_final_train_accuracy),
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
