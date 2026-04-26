import pandas as pd
from config import ORDINAL_FEATURES, FEATURE_COLUMNS


def _bin_rasio(value: float) -> int:
    # 0..4 ordinal: makin tinggi = beban tanggungan vs penghasilan makin berat
    if value <= 0.5:
        return 0
    if value <= 1.0:
        return 1
    if value <= 2.0:
        return 2
    if value <= 3.5:
        return 3
    return 4


def _bin_indeks_kerentanan(value: float) -> int:
    # 0..5 ordinal
    if value <= 1.0:
        return 0
    if value <= 1.7:
        return 1
    if value <= 2.4:
        return 2
    if value <= 3.1:
        return 3
    if value <= 3.8:
        return 4
    return 5


def add_engineered_features(features: pd.DataFrame) -> pd.DataFrame:
    df = features.copy()

    if "kip" in df.columns:
        # Skor bantuan sosial komposit (0-5) — semakin banyak aid = semakin miskin
        df["skor_bantuan_sosial"] = (
            df["kip"] + df["pkh"] + df["kks"] + df["dtks"] + df["sktm"]
        ).astype(int)

        # Flag penghasilan sangat rendah tanpa bantuan sosial apapun
        if "penghasilan_gabungan" in df.columns:
            df["rendah_tanpa_bantuan"] = (
                (df["penghasilan_gabungan"] <= 2) & (df["skor_bantuan_sosial"] == 0)
            ).astype(int)
        else:
            df["rendah_tanpa_bantuan"] = 0

        # Mismatch: punya >=2 bantuan tapi penghasilan tergolong tinggi (>=4)
        # Indikator anomali / potensi false positive di label.
        if "penghasilan_gabungan" in df.columns:
            df["mismatch_aid_income"] = (
                (df["skor_bantuan_sosial"] >= 2) & (df["penghasilan_gabungan"] >= 4)
            ).astype(int)
        else:
            df["mismatch_aid_income"] = 0

    # Rasio beban tanggungan terhadap penghasilan (0..4 ordinal).
    # Catatan: penghasilan_gabungan diukur 1..5 (5=paling sejahtera).
    # Inverse: 6 - penghasilan_gabungan -> beban relatif (1..5).
    if "jumlah_tanggungan" in df.columns and "penghasilan_gabungan" in df.columns:
        # Inverse jumlah_tanggungan: di skema saat ini, 5=keluarga kecil, 1=>=6 tanggungan.
        # Untuk rasio beban, kita pakai (6 - jumlah_tanggungan) sebagai "jumlah_tanggungan_real".
        beban_tanggungan = (6 - df["jumlah_tanggungan"].astype(int))
        beban_penghasilan = (6 - df["penghasilan_gabungan"].astype(int))
        rasio = beban_tanggungan / beban_penghasilan.replace(0, 1).clip(lower=1)
        df["rasio_tanggungan_penghasilan"] = rasio.apply(_bin_rasio).astype(int)
    else:
        df["rasio_tanggungan_penghasilan"] = 0

    # Indeks kerentanan tertimbang (0..5 ordinal) — komposit domain SPK KIP.
    needed = {"skor_bantuan_sosial", "penghasilan_gabungan", "jumlah_tanggungan",
              "status_rumah", "daya_listrik"}
    if needed.issubset(df.columns):
        score = (
            0.30 * df["skor_bantuan_sosial"].astype(float)
            + 0.25 * (6 - df["penghasilan_gabungan"].astype(float))
            + 0.15 * (6 - df["jumlah_tanggungan"].astype(float))
            + 0.15 * (5 - df["status_rumah"].astype(float))
            + 0.15 * (6 - df["daya_listrik"].astype(float))
        )
        df["indeks_kerentanan"] = score.apply(_bin_indeks_kerentanan).astype(int)
    else:
        df["indeks_kerentanan"] = 0

    # Pastikan urutan kolom sesuai dengan training (FEATURE_COLUMNS)
    expected_cols = [c for c in FEATURE_COLUMNS if c in df.columns]
    if len(expected_cols) == len(df.columns):
        df = df[expected_cols]

    return df


def transform_features_for_naive_bayes(features: pd.DataFrame) -> pd.DataFrame:
    """Pergeseran index ke 0-based untuk CategoricalNB."""
    transformed = features.copy().astype(int)
    ordinal_cols = [col for col in ORDINAL_FEATURES if col in transformed.columns]
    if ordinal_cols:
        transformed.loc[:, ordinal_cols] = transformed[ordinal_cols] - 1
    # Engineered features (skor_bantuan_sosial, rasio_tanggungan_penghasilan,
    # indeks_kerentanan, rendah_tanpa_bantuan, mismatch_aid_income) sudah 0-based.
    return transformed
