import pandas as pd
from sklearn.metrics import (
    accuracy_score,
    balanced_accuracy_score,
    confusion_matrix,
    f1_score,
    fbeta_score,
    precision_score,
    recall_score,
    roc_auc_score,
)
from sklearn.model_selection import StratifiedKFold
from config import (
    DEFAULT_POSITIVE_THRESHOLD,
    POSITIVE_F_BETA,
    MIN_INDIKASI_RECALL,
    THRESHOLD_OBJECTIVE,
)

from features import transform_features_for_naive_bayes

def probability_for_class(model, features: pd.DataFrame, target_class: int = 1) -> float:
    """Get probability of target_class for a SINGLE row."""
    probabilities = model.predict_proba(features)[0]
    classes = list(getattr(model, "classes_", []))

    if target_class in classes:
        return float(probabilities[classes.index(target_class)])

    if len(probabilities) == 1:
        return float(probabilities[0])

    return float(probabilities[1])


def batch_probability_for_class(model, features: pd.DataFrame, target_class: int = 1) -> list[float]:
    """Get probability of target_class for ALL rows in a batch (much faster)."""
    probabilities = model.predict_proba(features)
    classes = list(getattr(model, "classes_", []))

    if target_class in classes:
        col_idx = classes.index(target_class)
    elif probabilities.shape[1] == 1:
        col_idx = 0
    else:
        col_idx = 1

    return [float(p) for p in probabilities[:, col_idx]]


def select_threshold_with_cv(
    model_class,
    model_params: dict,
    X: pd.DataFrame,
    y: pd.Series,
    cat_features: list[str],
    n_splits: int = 5,
    is_naive_bayes: bool = False,
    beta: float = POSITIVE_F_BETA,
    cancel_check=None,
) -> dict:
    """Use Stratified K-Fold cross-validation for more robust threshold selection.
    
    Args:
        cancel_check: Optional callable that raises if training is cancelled.
    """
    if len(y) < n_splits * 2:
        # Fallback to smaller splits if data is tiny
        if len(y) > 4:
            n_splits = min(5, sum(y) if sum(y) > 0 else 5)
        else:
            return {
                "threshold": DEFAULT_POSITIVE_THRESHOLD,
                "fbeta": 0.0,
                "balanced_accuracy": 0.0,
                "positive_recall": 0.0,
                "positive_precision": 0.0,
            }

    try:
        skf = StratifiedKFold(n_splits=n_splits, shuffle=True, random_state=42)
    except ValueError:
        skf = StratifiedKFold(n_splits=5, shuffle=True, random_state=42)
        
    all_probabilities = []
    all_labels = []

    for train_idx, val_idx in skf.split(X, y):
        if cancel_check:
            cancel_check("cv_threshold_fold")

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

        fold_probs = batch_probability_for_class(fold_model, X_fold_val, target_class=1)
        all_probabilities.extend(fold_probs)
        all_labels.extend(int(v) for v in y_fold_val)

    y_cv = pd.Series(all_labels)
    return select_optimal_indikasi_threshold(y_cv, all_probabilities, beta=beta)


def select_optimal_indikasi_threshold(
    y_true: pd.Series,
    probabilities,
    beta: float = POSITIVE_F_BETA,
    min_recall: float = None,
    objective: str = None,
) -> dict:
    """Pilih threshold optimal.

    Mode `balanced_accuracy_with_recall_constraint` (default):
    - Filter kandidat: recall_indikasi >= min_recall
    - Rank: (balanced_accuracy, f1_macro, accuracy) desc
    - Fallback: kandidat dengan recall_indikasi tertinggi jika tidak ada yang lolos.

    Mode `f_beta` (legacy): rank by fbeta seperti versi lama.
    """
    if min_recall is None:
        min_recall = MIN_INDIKASI_RECALL
    if objective is None:
        objective = THRESHOLD_OBJECTIVE

    default_metrics = {
        "threshold": DEFAULT_POSITIVE_THRESHOLD,
        "fbeta": 0.0,
        "balanced_accuracy": 0.0,
        "accuracy": 0.0,
        "f1_macro": 0.0,
        "positive_recall": 0.0,
        "positive_precision": 0.0,
        "objective": objective,
    }

    if y_true is None or len(y_true) == 0:
        return default_metrics

    y_true_values = [int(value) for value in list(y_true)]
    candidate_thresholds = {DEFAULT_POSITIVE_THRESHOLD}
    candidate_thresholds.update(round(index / 100, 2) for index in range(5, 96, 5))
    candidate_thresholds.update(round(float(probability), 4) for probability in probabilities)

    candidates = []
    for threshold in sorted(candidate_thresholds):
        predictions = [1 if float(probability) >= threshold else 0 for probability in probabilities]

        positive_true = sum(1 for actual in y_true_values if actual == 1)
        true_positive = sum(
            1 for actual, predicted in zip(y_true_values, predictions) if actual == 1 and predicted == 1
        )
        predicted_positive = sum(predictions)

        positive_recall = (true_positive / positive_true) if positive_true else 0.0
        positive_precision = (true_positive / predicted_positive) if predicted_positive else 0.0
        fbeta = float(fbeta_score(y_true_values, predictions, beta=beta, zero_division=0))
        balanced_accuracy = float(balanced_accuracy_score(y_true_values, predictions))
        accuracy = float(accuracy_score(y_true_values, predictions))
        f1_macro = float(f1_score(y_true_values, predictions, average="macro", zero_division=0))

        candidates.append({
            "threshold": round(float(threshold), 4),
            "fbeta": round(fbeta, 4),
            "balanced_accuracy": round(balanced_accuracy, 4),
            "accuracy": round(accuracy, 4),
            "f1_macro": round(f1_macro, 4),
            "positive_recall": round(positive_recall, 4),
            "positive_precision": round(positive_precision, 4),
            "objective": objective,
        })

    if not candidates:
        return default_metrics

    if objective == "f_beta":
        candidates.sort(
            key=lambda c: (c["fbeta"], c["positive_recall"], c["balanced_accuracy"], -c["threshold"]),
            reverse=True,
        )
        return candidates[0]

    # Default: balanced_accuracy_with_recall_constraint
    qualified = [c for c in candidates if c["positive_recall"] >= min_recall]
    if qualified:
        qualified.sort(
            key=lambda c: (
                c["balanced_accuracy"],
                c["f1_macro"],
                c["accuracy"],
                c["positive_recall"],
                -c["threshold"],
            ),
            reverse=True,
        )
        return qualified[0]

    # Fallback: tidak ada threshold yang memenuhi constraint recall.
    # Pilih kandidat dengan recall tertinggi (tie-break: balanced_accuracy).
    candidates.sort(
        key=lambda c: (c["positive_recall"], c["balanced_accuracy"], c["f1_macro"], -c["threshold"]),
        reverse=True,
    )
    fallback = candidates[0]
    fallback["objective"] = f"{objective}_fallback_max_recall"
    return fallback


def compute_threshold_sweep(
    y_true: pd.Series,
    probabilities,
    thresholds: list[float] = None,
) -> list[dict]:
    """Kompute metrik untuk daftar threshold (untuk dokumentasi sensitivitas).

    Returns list of {threshold, accuracy, balanced_accuracy, f1_macro,
    recall_indikasi, precision_indikasi, f1_indikasi}.
    """
    if thresholds is None:
        thresholds = [0.30, 0.35, 0.40, 0.45, 0.50, 0.55, 0.60, 0.65, 0.70]

    if y_true is None or len(y_true) == 0:
        return []

    y_true_values = [int(v) for v in list(y_true)]
    sweep = []
    for thr in thresholds:
        predictions = [1 if float(p) >= thr else 0 for p in probabilities]
        sweep.append({
            "threshold": round(float(thr), 4),
            "accuracy": round(float(accuracy_score(y_true_values, predictions)), 4),
            "balanced_accuracy": round(float(balanced_accuracy_score(y_true_values, predictions)), 4),
            "f1_macro": round(float(f1_score(y_true_values, predictions, average="macro", zero_division=0)), 4),
            "recall_indikasi": round(float(recall_score(y_true_values, predictions, pos_label=1, zero_division=0)), 4),
            "precision_indikasi": round(float(precision_score(y_true_values, predictions, pos_label=1, zero_division=0)), 4),
            "f1_indikasi": round(float(f1_score(y_true_values, predictions, pos_label=1, zero_division=0)), 4),
        })
    return sweep


def build_evaluation_metrics(y_true: pd.Series, probabilities, threshold: float, evaluation_dataset: str) -> dict:
    y_true_values = [int(value) for value in list(y_true)]
    predicted_values = [1 if float(probability) >= threshold else 0 for probability in probabilities]
    tn, fp, fn, tp = confusion_matrix(y_true_values, predicted_values, labels=[0, 1]).ravel()

    try:
        roc_auc = round(float(roc_auc_score(y_true_values, [float(p) for p in probabilities])), 4)
    except ValueError:
        roc_auc = None

    return {
        "evaluation_dataset": evaluation_dataset,
        "threshold": round(float(threshold), 4),
        # Overall
        "accuracy": round(float(accuracy_score(y_true_values, predicted_values)), 4),
        "balanced_accuracy": round(float(balanced_accuracy_score(y_true_values, predicted_values)), 4),
        "roc_auc": roc_auc,
        # Kelas 0 — Layak
        "precision_layak": round(float(precision_score(y_true_values, predicted_values, pos_label=0, zero_division=0)), 4),
        "recall_layak": round(float(recall_score(y_true_values, predicted_values, pos_label=0, zero_division=0)), 4),
        "f1_layak": round(float(f1_score(y_true_values, predicted_values, pos_label=0, zero_division=0)), 4),
        "support_layak": int(y_true_values.count(0)),
        # Kelas 1 — Indikasi
        "precision_indikasi": round(float(precision_score(y_true_values, predicted_values, pos_label=1, zero_division=0)), 4),
        "recall_indikasi": round(float(recall_score(y_true_values, predicted_values, pos_label=1, zero_division=0)), 4),
        "f1_indikasi": round(float(f1_score(y_true_values, predicted_values, pos_label=1, zero_division=0)), 4),
        "fbeta_indikasi": round(float(fbeta_score(y_true_values, predicted_values, beta=POSITIVE_F_BETA, pos_label=1, zero_division=0)), 4),
        "support_indikasi": int(y_true_values.count(1)),
        # Macro average
        "precision_macro": round(float(precision_score(y_true_values, predicted_values, average="macro", zero_division=0)), 4),
        "recall_macro": round(float(recall_score(y_true_values, predicted_values, average="macro", zero_division=0)), 4),
        "f1_macro": round(float(f1_score(y_true_values, predicted_values, average="macro", zero_division=0)), 4),
        # Weighted average
        "precision_weighted": round(float(precision_score(y_true_values, predicted_values, average="weighted", zero_division=0)), 4),
        "recall_weighted": round(float(recall_score(y_true_values, predicted_values, average="weighted", zero_division=0)), 4),
        "f1_weighted": round(float(f1_score(y_true_values, predicted_values, average="weighted", zero_division=0)), 4),
        # Confusion matrix
        "confusion_matrix": {
            "tn": int(tn),
            "fp": int(fp),
            "fn": int(fn),
            "tp": int(tp),
        },
        # Threshold sensitivity sweep (untuk dokumentasi skripsi)
        "threshold_sweep": compute_threshold_sweep(y_true_values, probabilities),
    }

def training_quality_summary(features: pd.DataFrame, target: pd.Series) -> dict:
    from config import FEATURE_COLUMNS, TARGET_COLUMN
    
    feature_label_rows = features.copy()
    feature_label_rows[TARGET_COLUMN] = target.values

    feature_groups = feature_label_rows.groupby(FEATURE_COLUMNS)[TARGET_COLUMN]
    group_summary = feature_groups.agg(
        rows_in_group="size",
        labels_in_group=lambda values: int(values.nunique()),
    )
    conflict_groups = group_summary[group_summary["labels_in_group"] > 1]

    return {
        "unique_feature_vectors": int(features.drop_duplicates().shape[0]),
        "unique_feature_label_rows": int(feature_label_rows.drop_duplicates().shape[0]),
        "conflicting_feature_vectors": int(len(conflict_groups)),
        "rows_inside_conflicting_vectors": int(conflict_groups["rows_in_group"].sum()) if not conflict_groups.empty else 0,
    }
