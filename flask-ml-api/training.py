import joblib
import logging
import pandas as pd
from datetime import datetime, timezone
from typing import Optional
from catboost import CatBoostClassifier
from sklearn.calibration import CalibratedClassifierCV
from sklearn.metrics import accuracy_score, roc_auc_score, cohen_kappa_score, matthews_corrcoef
from sklearn.model_selection import train_test_split, StratifiedKFold

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
from training_manager import training_manager, TrainingCancelled

logger = logging.getLogger("training")

# ─── Hyperparameter CatBoost (dioptimalkan untuk ~900 baris, 15 fitur) ─────
# auto_class_weights="Balanced" (bukan SqrtBalanced) untuk menghindari
# over-prediction kelas positif. Regularisasi ditingkatkan untuk mengurangi
# false positive. Threshold keputusan akhir dipilih via StratifiedKFold CV.
CATBOOST_PARAMS = {
    "iterations": 600,
    "depth": 5,
    "learning_rate": 0.035,
    "l2_leaf_reg": 8,
    "random_strength": 1.0,
    "bagging_temperature": 0.8,
    "border_count": 64,
    "min_data_in_leaf": 8,
    "rsm": 0.85,
    "verbose": False,
    "random_seed": 42,
    "eval_metric": "AUC",
    "auto_class_weights": "Balanced",
    "od_type": "Iter",
    "od_wait": 50,
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


def encode_raw_dataframe(raw_df: pd.DataFrame) -> tuple[pd.DataFrame, list[int]]:
    """Encode setiap baris raw applicant data. Return (encoded_df, success_indices)."""
    encoded_rows = []
    success_indices = []
    failed_rows = 0
    for idx, record in enumerate(raw_df.to_dict(orient="records")):
        try:
            encoded_rows.append(encode_application_features(record))
            success_indices.append(idx)
        except ValueError:
            failed_rows += 1
            continue
    if failed_rows > 0:
        logger.warning(f"[ENCODING] {failed_rows} baris gagal di-encode, dilewati.")
    training_manager.advance_step("encoding", {"failed_encoding_rows": failed_rows})
    return pd.DataFrame(encoded_rows), success_indices


def compute_cv_accuracy(model_class, model_params, x, y, cat_features, is_naive_bayes=False, n_splits=5):
    """Hitung cross-validation accuracy (mean ± std) untuk laporan kepercayaan."""
    min_class = y.value_counts().min()
    actual_splits = min(n_splits, min_class)
    if actual_splits < 2:
        return {"mean": None, "std": None, "n_splits": 0}

    skf = StratifiedKFold(n_splits=actual_splits, shuffle=True, random_state=42)
    fold_accuracies = []

    for train_idx, val_idx in skf.split(x, y):
        training_manager.check_cancelled("cv_accuracy")
        x_tr, x_val = x.iloc[train_idx], x.iloc[val_idx]
        y_tr, y_val = y.iloc[train_idx], y.iloc[val_idx]
        if is_naive_bayes:
            x_tr = transform_features_for_naive_bayes(x_tr)
            x_val = transform_features_for_naive_bayes(x_val)
            fold_model = model_class(**model_params)
            fold_model.fit(x_tr, y_tr)
        else:
            fold_model = model_class(**model_params)
            fold_model.fit(x_tr, y_tr, cat_features=cat_features)
        fold_acc = float(accuracy_score(y_val, fold_model.predict(x_val)))
        fold_accuracies.append(fold_acc)

    import numpy as np
    return {
        "mean": round(float(np.mean(fold_accuracies)), 4),
        "std": round(float(np.std(fold_accuracies)), 4),
        "n_splits": actual_splits,
        "per_fold": [round(a, 4) for a in fold_accuracies],
    }


def build_extended_evaluation(y_true, probabilities, threshold, dataset_label):
    """Bangun evaluasi yang diperluas dengan ROC-AUC, Kappa, MCC."""
    base_metrics = build_evaluation_metrics(y_true, probabilities, threshold, dataset_label)

    y_true_list = [int(v) for v in y_true]
    y_pred = [1 if float(p) >= threshold else 0 for p in probabilities]

    try:
        roc_auc = round(float(roc_auc_score(y_true_list, probabilities)), 4)
    except ValueError:
        roc_auc = None

    try:
        kappa = round(float(cohen_kappa_score(y_true_list, y_pred)), 4)
    except Exception:
        kappa = None

    try:
        mcc = round(float(matthews_corrcoef(y_true_list, y_pred)), 4)
    except Exception:
        mcc = None

    base_metrics["roc_auc"] = roc_auc
    base_metrics["cohen_kappa"] = kappa
    base_metrics["matthews_corrcoef"] = mcc
    return base_metrics


def extract_feature_importance(model, feature_names):
    """Ambil feature importance dari CatBoost model."""
    try:
        importances = model.get_feature_importance()
        pairs = sorted(zip(feature_names, importances), key=lambda x: x[1], reverse=True)
        return [{"feature": name, "importance": round(float(imp), 4)} for name, imp in pairs]
    except Exception:
        return []


def train_and_save_models(
    dataframe: pd.DataFrame,
    schema_version: Optional[int] = None,
    triggered_by_user_id: Optional[int] = None,
    triggered_by_email: Optional[str] = None,
) -> dict:
    dataset_rows_total = int(len(dataframe))
    if dataframe.empty:
        raise ValueError("Data training kosong. Tambahkan data valid terlebih dahulu.")

    # ─── Step 1: Encode RAW applicant data ─────────────────────────────
    training_manager.advance_step("encoding", {"total_rows": dataset_rows_total})
    encoded_features, success_indices = encode_raw_dataframe(dataframe)
    if encoded_features.empty:
        raise ValueError("Tidak ada baris raw yang berhasil di-encode.")

    # Fix label alignment: hanya ambil label dari baris yang berhasil di-encode
    encoded_features = encoded_features.reset_index(drop=True)
    labels_source = dataframe.reset_index(drop=True).iloc[success_indices].reset_index(drop=True)

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

    # ─── Step 2: Data quality check ────────────────────────────────────
    training_manager.advance_step("data_quality_check")
    cleaned = df_engineered.dropna(subset=FEATURE_COLUMNS).copy()
    if cleaned.empty:
        raise ValueError("Data training tidak valid setelah pembersihan nilai kosong.")

    cleaned[TARGET_COLUMN] = cleaned[TARGET_COLUMN].astype(int)
    if cleaned[TARGET_COLUMN].nunique() < 2:
        raise ValueError("Data training harus memiliki minimal 2 kelas label.")

    # Resolusi konflik (majority-vote deduplication)
    deduplicated = cleaned.groupby(FEATURE_COLUMNS)[TARGET_COLUMN].agg(
        lambda items: items.value_counts().index[0]
    ).reset_index()

    x_full = deduplicated[FEATURE_COLUMNS].astype(int)
    y_full = deduplicated[TARGET_COLUMN].astype(int)
    quality_summary = training_quality_summary(x_full, y_full)

    training_manager.advance_step("split_dataset", {
        "rows_after_cleaning": int(len(cleaned)),
        "rows_after_dedup": int(len(deduplicated)),
        "quality_summary": quality_summary,
    })

    x_eval_train, x_valid, y_eval_train, y_valid, validation_strategy = split_training_dataset(x_full, y_full)

    nb_x_eval_train = transform_features_for_naive_bayes(x_eval_train)
    nb_x_valid = transform_features_for_naive_bayes(x_valid) if x_valid is not None else None
    nb_x_full = transform_features_for_naive_bayes(x_full)

    positive_class_weight = calculate_positive_class_weight(y_full)
    cat_features_list = BINARY_FEATURES + ENGINEERED_BINARY

    # ─── Step 3: CatBoost — eval model ────────────────────────────────
    training_manager.advance_step("training_catboost_eval")
    catboost_eval_model = CatBoostClassifier(**CATBOOST_PARAMS)

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

    # ─── Step 4: CatBoost threshold via Stratified K-Fold CV ──────────
    training_manager.advance_step("cv_threshold_catboost")
    cb_threshold_metrics = select_threshold_with_cv(
        CatBoostClassifier,
        CATBOOST_PARAMS,
        x_full, y_full,
        cat_features=cat_features_list,
        n_splits=5,
        is_naive_bayes=False,
        beta=POSITIVE_F_BETA,
        cancel_check=training_manager.check_cancelled,
    )
    if cb_threshold_metrics["threshold"] < 0.40:
        cb_threshold_metrics["threshold"] = 0.40

    # CatBoost CV accuracy
    cb_cv_accuracy = compute_cv_accuracy(
        CatBoostClassifier, CATBOOST_PARAMS,
        x_full, y_full, cat_features_list,
        is_naive_bayes=False, n_splits=5,
    )

    if x_valid is not None and y_valid is not None:
        cb_valid_accuracy = float(accuracy_score(y_valid, catboost_eval_model.predict(x_valid)))
        cb_valid_probabilities = batch_probability_for_class(catboost_eval_model, x_valid, target_class=1)
        cb_evaluation_metrics = build_extended_evaluation(
            y_valid, cb_valid_probabilities, cb_threshold_metrics["threshold"], "validation",
        )
    else:
        cb_train_probabilities = batch_probability_for_class(catboost_eval_model, x_eval_train, target_class=1)
        cb_evaluation_metrics = build_extended_evaluation(
            y_eval_train, cb_train_probabilities, cb_threshold_metrics["threshold"], "training",
        )

    # ─── Step 5: CatBoost final model (full data) ─────────────────────
    training_manager.advance_step("training_catboost_final")
    catboost_model = CatBoostClassifier(**CATBOOST_PARAMS)
    catboost_model.fit(x_full, y_full, cat_features=cat_features_list)
    cb_final_train_accuracy = float(accuracy_score(y_full, catboost_model.predict(x_full)))

    # Feature importance
    feature_importance = extract_feature_importance(catboost_model, list(FEATURE_COLUMNS))

    # ─── Step 6: Naive Bayes eval ─────────────────────────────────────
    training_manager.advance_step("training_naive_bayes_eval")
    class_prior = compute_class_prior(y_full)
    nb_base_categories = [2] * len(BINARY_FEATURES) + [5] * len(
        [f for f in ["penghasilan_gabungan", "penghasilan_ayah", "penghasilan_ibu",
                      "jumlah_tanggungan", "anak_ke"] if f in FEATURE_COLUMNS]
    )
    # status_orangtua(3), status_rumah(4), daya_listrik(5)
    nb_base_categories += [3, 4, 5]
    # rendah_tanpa_bantuan(2), skor_bantuan_sosial(6)
    nb_min_categories = nb_base_categories + [2] + [6]

    nb_params = {
        "fit_prior": True,
        "class_prior": class_prior,
        "alpha": 1.0,
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

    # ─── Step 7: NB threshold via CV ──────────────────────────────────
    training_manager.advance_step("cv_threshold_naive_bayes")
    nb_threshold_metrics = select_threshold_with_cv(
        CategoricalNB, nb_params,
        x_full, y_full,
        cat_features=cat_features_list,
        n_splits=5,
        is_naive_bayes=True,
        beta=POSITIVE_F_BETA,
        cancel_check=training_manager.check_cancelled,
    )
    if nb_threshold_metrics["threshold"] < 0.40:
        nb_threshold_metrics["threshold"] = 0.40

    # NB CV accuracy
    nb_cv_accuracy = compute_cv_accuracy(
        CategoricalNB, nb_params,
        x_full, y_full, cat_features_list,
        is_naive_bayes=True, n_splits=5,
    )

    # ─── Step 8: NB final model (full data) ───────────────────────────
    training_manager.advance_step("training_naive_bayes_final")
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

    if nb_x_valid is not None and y_valid is not None:
        nb_valid_accuracy = float(accuracy_score(y_valid, naive_bayes_eval_model.predict(nb_x_valid)))
        nb_valid_probabilities = batch_probability_for_class(naive_bayes_eval_model, nb_x_valid, target_class=1)
        nb_evaluation_metrics = build_extended_evaluation(
            y_valid, nb_valid_probabilities, nb_threshold_metrics["threshold"], "validation",
        )
    else:
        nb_train_probabilities = batch_probability_for_class(naive_bayes_eval_model, nb_x_eval_train, target_class=1)
        nb_evaluation_metrics = build_extended_evaluation(
            y_eval_train, nb_train_probabilities, nb_threshold_metrics["threshold"], "training",
        )

    # ─── Step 9: Build evaluation summary ─────────────────────────────
    training_manager.advance_step("building_evaluation")

    cv_summary = {
        "catboost_cv": cb_cv_accuracy,
        "naive_bayes_cv": nb_cv_accuracy,
    }

    # ─── Step 10: Persist models ──────────────────────────────────────
    training_manager.advance_step("persisting_models")
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
        f"Encoding oleh Flask ML (authoritative). "
        f"CatBoost: depth={CATBOOST_PARAMS['depth']} iter={CATBOOST_PARAMS['iterations']} "
        f"lr={CATBOOST_PARAMS['learning_rate']} l2={CATBOOST_PARAMS['l2_leaf_reg']} "
        f"rsm={CATBOOST_PARAMS['rsm']} auto_class_weights={CATBOOST_PARAMS['auto_class_weights']}. "
        f"Categorical NB: alpha=0.5 + Isotonic calibration. "
        f"Threshold dipilih via 5-Fold Stratified CV (floor 0.35)."
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
        "cv_summary": cv_summary,
        "feature_importance": feature_importance,
        "catboost_accuracy": rounded_or_none(effective_cb_accuracy),
        "naive_bayes_accuracy": rounded_or_none(effective_nb_accuracy),
        "primary_model": "catboost",
        "secondary_model": "categorical_nb",
        "note": note,
    }
