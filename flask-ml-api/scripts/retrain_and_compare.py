"""Retrain model setelah koreksi label & tampilkan metric.

Memanggil train_and_save_models() secara sinkron, lalu print metric utama
untuk verifikasi peningkatan model.

Usage:
    python scripts/retrain_and_compare.py
    python scripts/retrain_and_compare.py --auto-tune --trials 80
"""
import argparse
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT))

from database import fetch_training_dataframe
from training import train_and_save_models


def fmt(v):
    if v is None:
        return "  N/A "
    return f"{v:.4f}" if isinstance(v, float) else str(v)


def print_metrics(label: str, m: dict):
    print(f"\n=== {label} ===")
    print(f"  Dataset: {m.get('evaluation_dataset')}, threshold={m.get('threshold')}")
    print(f"  --- OVERALL ---")
    print(f"  accuracy          : {fmt(m.get('accuracy'))}")
    print(f"  balanced_accuracy : {fmt(m.get('balanced_accuracy'))}")
    print(f"  roc_auc           : {fmt(m.get('roc_auc'))}")
    print(f"  --- KELAS 0 (Layak)  | support={m.get('support_layak')} ---")
    print(f"  precision_layak   : {fmt(m.get('precision_layak'))}")
    print(f"  recall_layak      : {fmt(m.get('recall_layak'))}")
    print(f"  f1_layak          : {fmt(m.get('f1_layak'))}")
    print(f"  --- KELAS 1 (Indikasi) | support={m.get('support_indikasi')} ---")
    print(f"  precision_indikasi: {fmt(m.get('precision_indikasi'))}")
    print(f"  recall_indikasi   : {fmt(m.get('recall_indikasi'))}")
    print(f"  f1_indikasi       : {fmt(m.get('f1_indikasi'))}")
    print(f"  --- AVERAGES ---")
    print(f"  precision_macro   : {fmt(m.get('precision_macro'))}")
    print(f"  recall_macro      : {fmt(m.get('recall_macro'))}")
    print(f"  f1_macro          : {fmt(m.get('f1_macro'))}")
    print(f"  precision_weighted: {fmt(m.get('precision_weighted'))}")
    print(f"  recall_weighted   : {fmt(m.get('recall_weighted'))}")
    print(f"  f1_weighted       : {fmt(m.get('f1_weighted'))}")
    cm = m.get("confusion_matrix") or {}
    print(f"  --- CONFUSION MATRIX ---")
    print(f"               Pred Layak   Pred Indikasi")
    print(f"  Aktual Layak    {cm.get('tn', 0):5d}        {cm.get('fp', 0):5d}")
    print(f"  Aktual Indikasi {cm.get('fn', 0):5d}        {cm.get('tp', 0):5d}")
    print(f"  --- LAINNYA ---")
    print(f"  cohen_kappa       : {fmt(m.get('cohen_kappa'))}")
    print(f"  matthews_corrcoef : {fmt(m.get('matthews_corrcoef'))}")

    sweep = m.get("threshold_sweep") or []
    if sweep:
        print(f"  --- THRESHOLD SENSITIVITY SWEEP ---")
        print(f"  thr   | acc    | bal_acc| f1_mac | rec_ind| prec_ind| f1_ind")
        for row in sweep:
            print(
                f"  {row['threshold']:.2f} | {row['accuracy']:.4f} | "
                f"{row['balanced_accuracy']:.4f} | {row['f1_macro']:.4f} | "
                f"{row['recall_indikasi']:.4f} | {row['precision_indikasi']:.4f}  | "
                f"{row['f1_indikasi']:.4f}"
            )


def print_tuning_summary(summary: dict):
    if not summary:
        return
    print("\n=== Optuna Hyperparameter Tuning ===")
    print(f"  trials_completed     : {summary.get('n_trials_completed')}")
    print(f"  best_balanced_acc    : {fmt(summary.get('best_balanced_accuracy'))}")
    print(f"  best_recall_indikasi : {fmt(summary.get('best_recall_indikasi'))}")
    print(f"  best_value(score)    : {fmt(summary.get('best_value'))}")
    print(f"  --- BEST PARAMS ---")
    for key, val in (summary.get("best_params") or {}).items():
        print(f"    {key:24s}: {val}")
    history = summary.get("search_history_tail") or []
    if history:
        print(f"  --- HISTORY (20 trial terakhir) ---")
        for row in history:
            print(
                f"    trial {row['trial']:3d} | bal_acc={row['balanced_accuracy']:.4f} "
                f"| recall_ind={row['recall_indikasi']:.4f} | score={row['score']:.4f}"
            )


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--auto-tune", action="store_true", help="Aktifkan Optuna hyperparameter search")
    parser.add_argument("--trials", type=int, default=80, help="Jumlah trial Optuna (default: 80)")
    args = parser.parse_args()

    print("Fetching training dataframe...")
    df = fetch_training_dataframe()
    print(f"  Total: {len(df)} rows")
    print(f"  Distribusi label_class: {df['label_class'].value_counts().to_dict()}")

    if args.auto_tune:
        print(f"\n[OPTUNA] Auto-tune aktif — {args.trials} trial.")

    print("\nTraining model (sinkron)...")
    result = train_and_save_models(
        df,
        triggered_by_user_id=None,
        triggered_by_email="label-correction-script@local",
        auto_tune=args.auto_tune,
        tuning_trials=args.trials,
    )

    print(f"\nVersion: {result.get('model_version_name')}")
    print(f"Train rows: {result.get('train_rows')} | Validation: {result.get('validation_rows')}")
    print(f"Class distribution: {result.get('class_distribution')}")
    print(f"CatBoost threshold: {result.get('catboost_threshold')}")
    print(f"Naive Bayes threshold: {result.get('naive_bayes_threshold')}")

    print_tuning_summary(result.get("tuning_summary"))

    print_metrics("CatBoost", result.get("catboost_metrics") or {})
    print_metrics("Naive Bayes", result.get("naive_bayes_metrics") or {})

    cv = result.get("cv_summary") or {}
    print("\n=== Cross-Validation Accuracy (5-Fold) ===")
    cb_cv = cv.get("catboost_cv") or {}
    nb_cv = cv.get("naive_bayes_cv") or {}
    print(f"  CatBoost   mean={cb_cv.get('mean')}, std={cb_cv.get('std')}")
    print(f"  NaiveBayes mean={nb_cv.get('mean')}, std={nb_cv.get('std')}")

    fi = result.get("feature_importance") or []
    if fi:
        print("\n=== Feature Importance (CatBoost) ===")
        for entry in fi:
            print(f"  {entry['feature']:30s} {entry['importance']:.4f}")


if __name__ == "__main__":
    main()
