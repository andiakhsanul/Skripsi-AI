import pandas as pd
from config import ORDINAL_FEATURES

def add_engineered_features(features: pd.DataFrame) -> pd.DataFrame:
    df = features.copy()
    
    if "kip" in df.columns:
        # 1. Skor bantuan sosial komposit (0-5)
        df["skor_bantuan_sosial"] = df["kip"] + df["pkh"] + df["kks"] + df["dtks"] + df["sktm"]
        
        # 2. Flag penghasilan rendah tanpa bantuan
        df["rendah_tanpa_bantuan"] = ((df["penghasilan_gabungan"] == 1) & (df["skor_bantuan_sosial"] == 0)).astype(int)
    
    return df

def transform_features_for_naive_bayes(features: pd.DataFrame) -> pd.DataFrame:
    transformed = features.copy().astype(int)
    # Subtract 1 from ordinal features (1-3) to make them (0-2) for Naive Bayes
    if all(col in transformed.columns for col in ORDINAL_FEATURES):
        transformed.loc[:, ORDINAL_FEATURES] = transformed[ORDINAL_FEATURES] - 1
    # Note: skor_bantuan_sosial is already 0-5. No need to subtract 1.
    return transformed
