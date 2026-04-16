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
    MODEL_REGISTRY
)
from database import persist_model_version_record, mark_model_version_as_current
from features import add_engineered_features, transform_features_for_naive_bayes
from evaluation import (
    training_quality_summary,
    select_threshold_with_cv,
    probability_for_class,
    build_evaluation_metrics,
)
from model_registry import (
    relative_artifact_path,
    create_model_artifact
)

def rounded_or_none(value: Optional[float]) -> Optional[float]:
    if value is None:
        return None
    return round(float(value), 4)

def normalize_label(raw_value: str) -> int:
    normalized = str(raw_value).strip().lower()
    positive_values = {"indikasi", "1", "true", "ya"}
    return 1 if normalized in positive_values else 0

def build_version_name(schema_version: Optional[int], trained_at: datetime, status: str = "ready") -> str:
    schema_token = f"schema-v{schema_version}" if schema_version else "schema-all"
    suffix = trained_at.strftime("%Y%m%dT%H%M%S%fZ")
    return f"{status}-{schema_token}-{suffix}"

def calculate_positive_class_weight(target: pd.Series) -> float:
    """Calculate weight ratio. Kept for metadata logging only; not used in model."""
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
        features,
        target,
        test_size=holdout_rows,
        random_state=42,
        stratify=target,
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

def train_and_save_models(
    dataframe: pd.DataFrame,
    schema_version: Optional[int] = None,
    triggered_by_user_id: Optional[int] = None,
    triggered_by_email: Optional[str] = None,
) -> dict:
    dataset_rows_total = int(len(dataframe))

    if dataframe.empty:
        raise ValueError("Data training kosong. Tambahkan data valid terlebih dahulu.")

    df_engineered = add_engineered_features(dataframe)

    missing = [column for column in FEATURE_COLUMNS if column not in df_engineered.columns]
    if missing:
        raise ValueError(f"Kolom training tidak lengkap: {', '.join(missing)}")

    cleaned = df_engineered.dropna(subset=FEATURE_COLUMNS).copy()
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

    # ─── Resolusi Konflik (Majority-Vote Deduplication) ─────
    # Jika ada baris dengan vektor fitur identik tapi label berbeda, ambil label mayoritas
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

    # ─── CatBoost: Optimasi 499 baris data ─────
    # auto_class_weights='Balanced' agar CatBoost menghitung bobot kelas otomatis
    # depth=4 untuk mengurangi overfitting pada 394 vektor unik
    # l2_leaf_reg=8 untuk regularisasi lebih ketat (mengurangi false positives)
    cat_features_list = BINARY_FEATURES + ENGINEERED_BINARY
    catboost_eval_model = CatBoostClassifier(
        iterations=500,
        depth=4,
        learning_rate=0.05,
        l2_leaf_reg=8,
        random_strength=2,
        bagging_temperature=1,
        border_count=32,
        min_data_in_leaf=7,
        verbose=False,
        random_seed=42,
        eval_metric="BalancedAccuracy",
        auto_class_weights="Balanced",
    )

    if x_valid is not None and y_valid is not None:
        catboost_eval_model.fit(
            x_eval_train, y_eval_train,
            cat_features=cat_features_list,
            eval_set=(x_valid, y_valid),
            early_stopping_rounds=50,
            verbose=False,
        )
    else:
        catboost_eval_model.fit(x_eval_train, y_eval_train, cat_features=cat_features_list)

    cb_train_accuracy = float(accuracy_score(y_eval_train, catboost_eval_model.predict(x_eval_train)))
    cb_valid_accuracy = None

    # ─── Threshold selection via Stratified K-Fold CV (10-fold untuk 499 baris) ─────────────
    cb_threshold_metrics = select_threshold_with_cv(
        CatBoostClassifier,
        {
            "iterations": 500,
            "depth": 4,
            "learning_rate": 0.05,
            "l2_leaf_reg": 8,
            "random_strength": 2,
            "bagging_temperature": 1,
            "min_data_in_leaf": 7,
            "border_count": 32,
            "verbose": False,
            "random_seed": 42,
            "eval_metric": "BalancedAccuracy",
            "auto_class_weights": "Balanced",
        },
        x_full,
        y_full,
        cat_features=cat_features_list,
        n_splits=10,
        is_naive_bayes=False,
        beta=POSITIVE_F_BETA,
    )

    if cb_threshold_metrics["threshold"] < 0.40:
        cb_threshold_metrics["threshold"] = DEFAULT_POSITIVE_THRESHOLD

    if x_valid is not None and y_valid is not None:
        cb_valid_accuracy = float(accuracy_score(y_valid, catboost_eval_model.predict(x_valid)))
        cb_valid_probabilities = [
            probability_for_class(catboost_eval_model, x_valid.iloc[[index]], target_class=1)
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
            probability_for_class(catboost_eval_model, x_eval_train.iloc[[index]], target_class=1)
            for index in range(len(x_eval_train))
        ]
        cb_evaluation_metrics = build_evaluation_metrics(
            y_eval_train,
            cb_train_probabilities,
            cb_threshold_metrics["threshold"],
            "training",
        )

    catboost_model = CatBoostClassifier(
        iterations=500,
        depth=4,
        learning_rate=0.05,
        l2_leaf_reg=8,
        random_strength=2,
        bagging_temperature=1,
        border_count=32,
        min_data_in_leaf=7,
        verbose=False,
        random_seed=42,
        eval_metric="BalancedAccuracy",
        auto_class_weights="Balanced",
    )
    catboost_model.fit(x_full, y_full, cat_features=cat_features_list)
    cb_final_train_accuracy = float(accuracy_score(y_full, catboost_model.predict(x_full)))

    # ─── Naive Bayes: dengan Laplace smoothing dan class prior proporsional ─────
    class_prior = compute_class_prior(y_full)
    # 5 binary (2), 5 ordinal (5), 1 status_orangtua (3), 1 status_rumah (4), 1 daya_listrik (5)
    nb_base_categories = [2, 2, 2, 2, 2, 5, 5, 5, 5, 5, 3, 4, 5]
    nb_min_categories = nb_base_categories + [2] + [6] # + rendah_tanpa_bantuan(2) + skor_bantuan_sosial(6)
    
    nb_params = {
        "fit_prior": True,
        "class_prior": class_prior,
        "alpha": 1.0, # Smoothing standard krn data cukup
        "min_categories": nb_min_categories,
    }
    
    naive_bayes_eval_base = CategoricalNB(**nb_params)
    naive_bayes_eval_base.fit(nb_x_eval_train, y_eval_train)
    
    naive_bayes_eval_model = naive_bayes_eval_base
    eval_calibration_splits = calibration_cv_splits(y_eval_train)
    if len(y_eval_train) >= 10 and eval_calibration_splits >= 2:
        naive_bayes_eval_model = CalibratedClassifierCV(
            naive_bayes_eval_base,
            method="isotonic",
            cv=eval_calibration_splits,
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
            naive_bayes_full_base,
            method="isotonic",
            cv=full_calibration_splits,
        )
        try:
            naive_bayes_model.fit(nb_x_full, y_full)
        except ValueError:
            naive_bayes_model = naive_bayes_full_base
            
    nb_final_train_accuracy = float(accuracy_score(y_full, naive_bayes_model.predict(nb_x_full)))

    # ─── NB Threshold via CV ──────────────────────────────────
    nb_threshold_metrics = select_threshold_with_cv(
        CategoricalNB,
        nb_params,
        x_full,
        y_full,
        cat_features=cat_features_list,
        n_splits=10,
        is_naive_bayes=True,
        beta=POSITIVE_F_BETA,
    )

    if nb_threshold_metrics["threshold"] < 0.40:
        nb_threshold_metrics["threshold"] = DEFAULT_POSITIVE_THRESHOLD

    if nb_x_valid is not None and y_valid is not None:
        nb_valid_accuracy = float(accuracy_score(y_valid, naive_bayes_eval_model.predict(nb_x_valid)))
        nb_valid_probabilities = [
            probability_for_class(naive_bayes_eval_model, nb_x_valid.iloc[[index]], target_class=1)
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
            probability_for_class(naive_bayes_eval_model, nb_x_eval_train.iloc[[index]], target_class=1)
            for index in range(len(nb_x_eval_train))
        ]
        nb_evaluation_metrics = build_evaluation_metrics(
            y_eval_train,
            nb_train_probabilities,
            nb_threshold_metrics["threshold"],
            "training",
        )

    # Save models
    # `ensure_model_directory` dari model_registry
    CATBOOST_MODEL_PATH.parent.mkdir(parents=True, exist_ok=True)
    NAIVE_BAYES_MODEL_PATH.parent.mkdir(parents=True, exist_ok=True)
    
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
        "CatBoost dioptimasi untuk precision tinggi (depth=4, iter=500, l2=8, auto_class_weights=Balanced). "
        "Categorical NB diaktifkan dengan prior proporsional dan Isotonic calibration. "
        "Threshold dipilih memakai 10-Fold CV dengan minimum floor 0.40."
    )
    
    if len(deduplicated) < len(cleaned):
        note += (
            f" Dilakukan resolusi konflik (majority-vote deduplication) mengurangi "
            f"{len(cleaned)} baris menjadi {len(deduplicated)} baris unik."
        )

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
