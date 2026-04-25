"""Analisis kualitas label di spk_training_data.

Tujuan: Identifikasi baris yang kemungkinan salah dilabel (mislabeled)
yang merusak performa model. Kombinasi 3 sinyal:

1. Rule-based score disagreement
   Hitung skor vulnerabilitas (0-1) dari fitur. Bandingkan dengan label.
   - Skor sangat tinggi (poor) + label "Layak" (eligible) → konsisten
   - Skor sangat rendah (wealthy) + label "Layak" → SUSPICIOUS
   - Skor sangat tinggi + label "Indikasi" → SUSPICIOUS
   - Skor sangat rendah + label "Indikasi" → konsisten

   Catatan: Layak = eligible to receive aid (deserving / vulnerable)
            Indikasi = flagged as not deserving (less vulnerable)

2. Out-of-fold model disagreement (CatBoost 5-fold CV)
   Model dilatih CV. Untuk setiap baris, ambil prediksi dari fold di mana
   baris itu di validasi. Jika model SANGAT YAKIN (>0.85) berbeda dari
   label sebenarnya → kandidat mislabel.

3. Conflicting feature vectors (duplicate features, label berbeda)
   Baris dengan kombinasi fitur identik tapi label berbeda → minimal
   salah satu pasti salah label.

Output: CSV/JSON daftar baris kandidat dengan reason + skor confidence.
Tidak melakukan UPDATE. Update dilakukan terpisah setelah review.
"""
import json
import sys
from collections import defaultdict
from pathlib import Path

import numpy as np
import pandas as pd

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT))

from catboost import CatBoostClassifier
from sklearn.model_selection import StratifiedKFold

from config import BINARY_FEATURES, ORDINAL_FEATURES, FEATURE_COLUMNS, TARGET_COLUMN
from database import fetch_training_dataframe
from encoding import encode_application_features
from features import add_engineered_features


# ─── Heuristic 1: Rule-based vulnerability score ────────────────────────
def compute_vulnerability_score(row: pd.Series) -> float:
    """Skor vulnerabilitas 0-1, semakin tinggi = semakin pantas dapat KIP.

    Mirror dari RuleScoringService.php tapi diadaptasi untuk fitur encoded.
    Bobot 50% binary aid + 50% ordinal vulnerability.
    """
    binary_score = sum(int(row[f]) for f in BINARY_FEATURES) / max(len(BINARY_FEATURES), 1)

    # Ordinal: low value = poor/vulnerable. Konversi ke skor 0-1 (1 = poorest)
    ordinal_scores = []
    for f in ORDINAL_FEATURES:
        v = int(row[f])
        # Range max per fitur (lihat encoding.py)
        if f in ("status_orangtua",):
            max_v = 3
        elif f in ("status_rumah",):
            max_v = 4
        else:  # penghasilan, jumlah_tanggungan, anak_ke, daya_listrik
            max_v = 5
        # 1 = paling vulnerable → skor 1.0; max = paling sejahtera → skor 0.0
        score = (max_v - v) / (max_v - 1) if max_v > 1 else 0.0
        ordinal_scores.append(max(0.0, min(score, 1.0)))
    ordinal_score = sum(ordinal_scores) / max(len(ordinal_scores), 1)

    return 0.5 * binary_score + 0.5 * ordinal_score


# ─── Encoding raw → ordinal (sama seperti training pipeline) ───────────
def encode_dataframe(df_raw: pd.DataFrame) -> tuple[pd.DataFrame, list[int]]:
    encoded_rows = []
    success_indices = []
    for idx, row in df_raw.iterrows():
        try:
            payload = {f: row[f] for f in [
                "kip", "pkh", "kks", "dtks", "sktm",
                "penghasilan_ayah_rupiah", "penghasilan_ibu_rupiah",
                "penghasilan_gabungan_rupiah",
                "jumlah_tanggungan_raw", "anak_ke_raw",
                "status_orangtua_text", "status_rumah_text", "daya_listrik_text",
            ]}
            encoded = encode_application_features(payload)
            encoded_rows.append(encoded)
            success_indices.append(idx)
        except Exception as exc:
            print(f"  [skip row idx={idx} app_id={row.get('source_application_id')}]: {exc}", file=sys.stderr)
    return pd.DataFrame(encoded_rows), success_indices


# ─── Heuristic 2: Out-of-fold model disagreement ────────────────────────
def compute_oof_predictions(X: pd.DataFrame, y: pd.Series, n_splits: int = 5) -> np.ndarray:
    """Cross-validated predicted probabilities of class 1 (Indikasi)."""
    oof_proba = np.zeros(len(y))
    skf = StratifiedKFold(n_splits=n_splits, shuffle=True, random_state=42)
    cat_features = BINARY_FEATURES + ORDINAL_FEATURES + ["rendah_tanpa_bantuan", "skor_bantuan_sosial"]
    cat_features = [c for c in cat_features if c in X.columns]

    for fold, (train_idx, val_idx) in enumerate(skf.split(X, y)):
        model = CatBoostClassifier(
            iterations=600, depth=5, learning_rate=0.035,
            l2_leaf_reg=8, verbose=False, random_seed=42,
            auto_class_weights="Balanced",
        )
        model.fit(X.iloc[train_idx], y.iloc[train_idx], cat_features=cat_features)
        proba = model.predict_proba(X.iloc[val_idx])[:, list(model.classes_).index(1)]
        oof_proba[val_idx] = proba
        print(f"  fold {fold + 1}/{n_splits} done")
    return oof_proba


# ─── Heuristic 3: Conflicting feature vectors ──────────────────────────
def find_conflicting_groups(X: pd.DataFrame, y: pd.Series, app_ids: pd.Series) -> dict:
    """Baris dengan fitur identik tapi label berbeda."""
    feature_tuple = X.apply(lambda row: tuple(row), axis=1)
    df = pd.DataFrame({"feat": feature_tuple, "label": y.values, "app_id": app_ids.values})

    conflicts = {}
    for feat, group in df.groupby("feat"):
        labels = group["label"].unique()
        if len(labels) > 1:
            counts = group["label"].value_counts()
            majority = int(counts.idxmax())
            minority = int(counts.idxmin())
            # IDs di kelas minority = kandidat mislabel
            minority_ids = group[group["label"] == minority]["app_id"].tolist()
            conflicts[str(feat)] = {
                "feat": feat,
                "majority_label": majority,
                "minority_label": minority,
                "majority_count": int(counts[majority]),
                "minority_count": int(counts[minority]),
                "minority_app_ids": [int(x) for x in minority_ids],
            }
    return conflicts


# ─── Main analysis ──────────────────────────────────────────────────────
def main():
    print("=== Analisis Kualitas Label ===\n")

    print("[1/4] Fetch training data dari database...")
    df_raw = fetch_training_dataframe()
    print(f"  Total rows: {len(df_raw)}")
    print(f"  Distribusi label: {df_raw['label_class'].value_counts().to_dict()}\n")

    print("[2/4] Encoding fitur...")
    encoded, success_idx = encode_dataframe(df_raw)
    df_aligned = df_raw.iloc[success_idx].reset_index(drop=True)
    encoded = encoded.reset_index(drop=True)
    encoded[TARGET_COLUMN] = df_aligned["label_class"].astype(int).values
    df_eng = add_engineered_features(encoded)

    # Bersihkan NaN
    df_eng = df_eng.dropna(subset=FEATURE_COLUMNS).reset_index(drop=True)
    df_aligned = df_aligned.iloc[df_eng.index].reset_index(drop=True) if len(df_eng) < len(df_aligned) else df_aligned
    df_aligned = df_aligned.reset_index(drop=True)
    df_eng = df_eng.reset_index(drop=True)
    print(f"  Setelah encoding & cleaning: {len(df_eng)} rows\n")

    X = df_eng[FEATURE_COLUMNS].astype(int)
    y = df_eng[TARGET_COLUMN].astype(int)

    # Heuristic 1: Rule-based score
    print("[3a/4] Hitung rule-based vulnerability score...")
    df_aligned["vulnerability_score"] = X.apply(compute_vulnerability_score, axis=1)

    # Heuristic 2: OOF predictions
    print("[3b/4] Train CatBoost cross-validation untuk OOF predictions...")
    oof_proba = compute_oof_predictions(X, y)
    df_aligned["oof_proba_indikasi"] = oof_proba
    df_aligned["oof_pred"] = (oof_proba >= 0.5).astype(int)

    # Heuristic 3: Conflicting feature vectors
    print("\n[3c/4] Cari conflicting feature vectors...")
    conflicts = find_conflicting_groups(X, y, df_aligned["source_application_id"])
    conflict_app_ids = set()
    for c in conflicts.values():
        conflict_app_ids.update(c["minority_app_ids"])
    print(f"  Jumlah grup konflik: {len(conflicts)}")
    print(f"  App IDs di kelas minority (kandidat mislabel): {len(conflict_app_ids)}\n")

    # ─── Identifikasi suspicious rows ──────────────────────────────────
    print("[4/4] Identifikasi kandidat mislabel...")

    suspicious = []
    for i, row in df_aligned.iterrows():
        app_id = int(row["source_application_id"])
        current_label = int(row["label_class"])  # 0=Layak, 1=Indikasi
        vuln = float(row["vulnerability_score"])
        oof_p = float(row["oof_proba_indikasi"])

        reasons = []
        confidence = 0.0
        suggested_label = current_label

        # R1: Konflik dengan rule (vulnerability vs label)
        # Konvensi: tinggi vuln = miskin = pantas Layak (0)
        # tinggi vuln tapi label Indikasi (1) → kemungkinan harus Layak
        # rendah vuln tapi label Layak (0) → kemungkinan harus Indikasi
        if current_label == 1 and vuln >= 0.65:
            reasons.append(f"vuln_score={vuln:.2f} tinggi tapi label=Indikasi (mungkin Layak)")
            suggested_label = 0
            confidence += 0.35
        elif current_label == 0 and vuln <= 0.20:
            reasons.append(f"vuln_score={vuln:.2f} rendah tapi label=Layak (mungkin Indikasi)")
            suggested_label = 1
            confidence += 0.35

        # R2: Model OOF sangat yakin berbeda dari label
        # Jika current=0 (Layak) tapi oof_proba>=0.85 (model yakin Indikasi) → suspicious
        # Jika current=1 (Indikasi) tapi oof_proba<=0.15 (model yakin Layak) → suspicious
        if current_label == 0 and oof_p >= 0.85:
            reasons.append(f"OOF model yakin Indikasi (p={oof_p:.2f}) tapi label=Layak")
            suggested_label = 1
            confidence += 0.45
        elif current_label == 1 and oof_p <= 0.15:
            reasons.append(f"OOF model yakin Layak (p={oof_p:.2f}) tapi label=Indikasi")
            suggested_label = 0
            confidence += 0.45

        # R3: Anggota kelas minority di grup konflik
        if app_id in conflict_app_ids:
            reasons.append("conflicting_feature_vector (label minoritas dalam grup fitur identik)")
            confidence += 0.30

        if reasons and confidence >= 0.30:
            suspicious.append({
                "source_application_id": app_id,
                "current_label_class": current_label,
                "current_label": "Layak" if current_label == 0 else "Indikasi",
                "suggested_label_class": suggested_label,
                "suggested_label": "Layak" if suggested_label == 0 else "Indikasi",
                "vulnerability_score": round(vuln, 3),
                "oof_proba_indikasi": round(oof_p, 3),
                "confidence": round(min(confidence, 1.0), 2),
                "reasons": " | ".join(reasons),
            })

    suspicious.sort(key=lambda r: -r["confidence"])

    # Save outputs
    out_dir = ROOT / "scripts" / "ml_experiments"
    out_dir.mkdir(parents=True, exist_ok=True)
    csv_path = out_dir / "label_quality_candidates.csv"
    json_path = out_dir / "label_quality_candidates.json"

    df_out = pd.DataFrame(suspicious)
    df_out.to_csv(csv_path, index=False)
    with open(json_path, "w") as f:
        json.dump(suspicious, f, indent=2)

    # Ringkasan
    print(f"\n=== RINGKASAN ===")
    print(f"Total rows analyzed   : {len(df_aligned)}")
    print(f"Conflicting groups    : {len(conflicts)}")
    print(f"Kandidat mislabel     : {len(suspicious)}")
    if suspicious:
        labels_change = defaultdict(int)
        for s in suspicious:
            labels_change[(s["current_label"], s["suggested_label"])] += 1
        print(f"Perubahan label yang disarankan:")
        for (cur, sug), cnt in labels_change.items():
            print(f"  {cur} → {sug}: {cnt}")
        print(f"\nTop-10 kandidat (confidence tertinggi):")
        for s in suspicious[:10]:
            print(f"  app_id={s['source_application_id']:4d} | "
                  f"{s['current_label']:8s} → {s['suggested_label']:8s} | "
                  f"conf={s['confidence']:.2f} | vuln={s['vulnerability_score']:.2f} | "
                  f"oof_p={s['oof_proba_indikasi']:.2f}")
            print(f"             reasons: {s['reasons']}")

    print(f"\nOutput tersimpan ke:")
    print(f"  {csv_path}")
    print(f"  {json_path}")


if __name__ == "__main__":
    main()
