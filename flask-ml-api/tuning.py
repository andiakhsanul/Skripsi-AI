"""Hyperparameter tuning untuk CatBoost via Optuna.

Optimasi: mean balanced_accuracy 5-fold Stratified CV,
dengan soft penalty agar recall kelas Indikasi tetap >= MIN_INDIKASI_RECALL.
"""
import logging
from typing import Callable, Optional

import numpy as np
import pandas as pd
from catboost import CatBoostClassifier
from sklearn.metrics import balanced_accuracy_score, recall_score
from sklearn.model_selection import StratifiedKFold

from config import MIN_INDIKASI_RECALL, CATBOOST_THREAD_COUNT

logger = logging.getLogger("tuning")


def _build_static_params(thread_count: int) -> dict:
    return {
        "verbose": False,
        "random_seed": 42,
        "eval_metric": "AUC",
        "od_type": "Iter",
        "od_wait": 50,
        "thread_count": thread_count,
    }


def _suggest_catboost_params(trial, thread_count: int) -> dict:
    """Search space dipilih berdasarkan referensi tuning CatBoost untuk dataset kecil."""
    params = {
        "iterations": trial.suggest_int("iterations", 300, 1500, step=100),
        "depth": trial.suggest_int("depth", 3, 8),
        "learning_rate": trial.suggest_float("learning_rate", 0.01, 0.15, log=True),
        "l2_leaf_reg": trial.suggest_float("l2_leaf_reg", 1.0, 15.0),
        "bagging_temperature": trial.suggest_float("bagging_temperature", 0.0, 1.5),
        "random_strength": trial.suggest_float("random_strength", 0.0, 3.0),
        "min_data_in_leaf": trial.suggest_int("min_data_in_leaf", 2, 20),
        "border_count": trial.suggest_categorical("border_count", [32, 64, 96, 128]),
        "rsm": trial.suggest_float("rsm", 0.6, 1.0),
        "auto_class_weights": trial.suggest_categorical(
            "auto_class_weights", ["Balanced", "SqrtBalanced", "None"]
        ),
    }
    if params["auto_class_weights"] == "None":
        params["auto_class_weights"] = None
    params.update(_build_static_params(thread_count))
    return params


def _evaluate_params(
    params: dict,
    X: pd.DataFrame,
    y: pd.Series,
    cat_features: list[str],
    cv_splits: int,
    min_recall: float,
) -> tuple[float, float, float]:
    """Return (mean_balanced_accuracy, mean_recall_indikasi, score_with_penalty)."""
    skf = StratifiedKFold(n_splits=cv_splits, shuffle=True, random_state=42)
    bas, recalls = [], []

    for train_idx, val_idx in skf.split(X, y):
        x_tr, x_val = X.iloc[train_idx], X.iloc[val_idx]
        y_tr, y_val = y.iloc[train_idx], y.iloc[val_idx]

        model = CatBoostClassifier(**params)
        model.fit(x_tr, y_tr, cat_features=cat_features)
        y_pred = model.predict(x_val)

        bas.append(float(balanced_accuracy_score(y_val, y_pred)))
        recalls.append(float(recall_score(y_val, y_pred, pos_label=1, zero_division=0)))

    mean_ba = float(np.mean(bas))
    mean_recall = float(np.mean(recalls))
    # Soft penalty: kurangi 0.5 per unit kekurangan recall di bawah min_recall.
    penalty = 0.5 * max(0.0, min_recall - mean_recall)
    score = mean_ba - penalty
    return mean_ba, mean_recall, score


def tune_catboost_params(
    X: pd.DataFrame,
    y: pd.Series,
    cat_features: list[str],
    n_trials: int = 80,
    cv_splits: int = 5,
    min_recall: float = None,
    thread_count: int = None,
    cancel_check: Optional[Callable[[str], None]] = None,
) -> dict:
    """Jalankan Optuna untuk cari hyperparameter CatBoost terbaik.

    Returns: {
        "best_params": dict (siap dipakai CatBoostClassifier),
        "best_value": float (mean BA dengan penalty),
        "best_balanced_accuracy": float,
        "best_recall_indikasi": float,
        "n_trials_completed": int,
        "search_history": list[dict],
    }
    """
    import optuna

    if min_recall is None:
        min_recall = MIN_INDIKASI_RECALL
    if thread_count is None:
        thread_count = CATBOOST_THREAD_COUNT

    # Pastikan minimum split memungkinkan
    min_class_count = int(y.value_counts().min())
    actual_splits = max(2, min(cv_splits, min_class_count))

    sampler = optuna.samplers.TPESampler(seed=42)
    pruner = optuna.pruners.MedianPruner(n_startup_trials=10, n_warmup_steps=0)
    study = optuna.create_study(direction="maximize", sampler=sampler, pruner=pruner)

    history: list[dict] = []

    def objective(trial):
        if cancel_check:
            cancel_check(f"optuna_trial_{trial.number}")

        params = _suggest_catboost_params(trial, thread_count)
        try:
            mean_ba, mean_recall, score = _evaluate_params(
                params, X, y, cat_features, actual_splits, min_recall
            )
        except Exception as exc:
            logger.warning(f"[OPTUNA] trial {trial.number} gagal: {exc}")
            raise optuna.TrialPruned() from exc

        trial.set_user_attr("balanced_accuracy", round(mean_ba, 4))
        trial.set_user_attr("recall_indikasi", round(mean_recall, 4))
        history.append({
            "trial": trial.number,
            "balanced_accuracy": round(mean_ba, 4),
            "recall_indikasi": round(mean_recall, 4),
            "score": round(score, 4),
        })
        return score

    optuna.logging.set_verbosity(optuna.logging.WARNING)
    study.optimize(objective, n_trials=n_trials, show_progress_bar=False)

    best_trial = study.best_trial
    best_params = dict(best_trial.params)
    if best_params.get("auto_class_weights") == "None":
        best_params["auto_class_weights"] = None
    best_params.update(_build_static_params(thread_count))

    return {
        "best_params": best_params,
        "best_value": round(float(best_trial.value), 4),
        "best_balanced_accuracy": float(best_trial.user_attrs.get("balanced_accuracy", 0.0)),
        "best_recall_indikasi": float(best_trial.user_attrs.get("recall_indikasi", 0.0)),
        "n_trials_completed": len(study.trials),
        "search_history": history[-20:],  # 20 trial terakhir untuk laporan
    }
