import pandas as pd
from sklearn.metrics import (
    accuracy_score,
    balanced_accuracy_score,
    confusion_matrix,
    f1_score,
    fbeta_score,
    precision_score,
    recall_score,
)
from sklearn.model_selection import StratifiedKFold
from config import DEFAULT_POSITIVE_THRESHOLD, POSITIVE_F_BETA

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
    n_splits: int = 10,
    is_naive_bayes: bool = False,
    beta: float = POSITIVE_F_BETA,
) -> dict:
    """Use Stratified K-Fold cross-validation for more robust threshold selection."""
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
        # We start to clamp thresholds so it does not become too aggressively low or high 
        # But we do that in the caller for minimum threshold
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
