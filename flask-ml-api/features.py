import pandas as pd
from config import ORDINAL_FEATURES


def add_engineered_features(features: pd.DataFrame) -> pd.DataFrame:
    df = features.copy()

    if "kip" in df.columns:
        # Skor bantuan sosial komposit (0-5) — semakin banyak aid = semakin miskin
        df["skor_bantuan_sosial"] = (
            df["kip"] + df["pkh"] + df["kks"] + df["dtks"] + df["sktm"]
        )

        # Flag penghasilan sangat rendah tanpa bantuan sosial apapun
        if "penghasilan_gabungan" in df.columns:
            df["rendah_tanpa_bantuan"] = (
                (df["penghasilan_gabungan"] <= 2) & (df["skor_bantuan_sosial"] == 0)
            ).astype(int)
        else:
            df["rendah_tanpa_bantuan"] = 0

    return df


def transform_features_for_naive_bayes(features: pd.DataFrame) -> pd.DataFrame:
    """Pergeseran index ke 0-based untuk CategoricalNB."""
    transformed = features.copy().astype(int)
    ordinal_cols = [col for col in ORDINAL_FEATURES if col in transformed.columns]
    if ordinal_cols:
        transformed.loc[:, ordinal_cols] = transformed[ordinal_cols] - 1
    # skor_bantuan_sosial & rendah_tanpa_bantuan sudah 0-based
    return transformed
