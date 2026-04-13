import os
from pathlib import Path

APP_ROOT = Path(__file__).resolve().parent

def resolve_env_path(env_name: str, default: str) -> Path:
    configured_path = Path(os.getenv(env_name, default))
    return configured_path if configured_path.is_absolute() else APP_ROOT / configured_path

# ─── Definisi Fitur KIP-K ─────────────────────────────────────────────
BINARY_FEATURES = ["kip", "pkh", "kks", "dtks", "sktm"]
ORDINAL_FEATURES = [
    "penghasilan_gabungan",
    "penghasilan_ayah",
    "penghasilan_ibu",
    "jumlah_tanggungan",
    "anak_ke",
    "status_orangtua",
    "status_rumah",
    "daya_listrik",
]
DB_FEATURE_COLUMNS = BINARY_FEATURES + ORDINAL_FEATURES

# Engineered Features
ENGINEERED_BINARY = ["rendah_tanpa_bantuan"]
ENGINEERED_ORDINAL = ["skor_bantuan_sosial"]  # 0 to 5 categories

FEATURE_COLUMNS = DB_FEATURE_COLUMNS + ENGINEERED_BINARY + ENGINEERED_ORDINAL
TARGET_COLUMN = "label_class"

# Configurations
REVIEW_PRIORITY_THRESHOLD = float(os.getenv("REVIEW_PRIORITY_THRESHOLD", "0.65"))
MODEL_VALIDATION_SPLIT = min(max(float(os.getenv("MODEL_VALIDATION_SPLIT", "0.2")), 0.1), 0.4)

CATBOOST_MODEL_PATH = resolve_env_path("CATBOOST_MODEL_PATH", "models/catboost_model.joblib")
NAIVE_BAYES_MODEL_PATH = resolve_env_path("NAIVE_BAYES_MODEL_PATH", "models/naive_bayes_model.joblib")
TRAINING_TABLE = os.getenv("TRAINING_TABLE", "spk_training_data")
MODEL_VERSIONS_TABLE = os.getenv("MODEL_VERSIONS_TABLE", "model_versions")
FLASK_INTERNAL_TOKEN = os.getenv("FLASK_INTERNAL_TOKEN", "spk_internal_dev_token")

DEFAULT_POSITIVE_THRESHOLD = float(os.getenv("DEFAULT_POSITIVE_THRESHOLD", "0.5"))
POSITIVE_F_BETA = float(os.getenv("POSITIVE_F_BETA", "1.0"))

# Global State / Registry
MODEL_REGISTRY = {"catboost": None, "naive_bayes": None, "metadata": None}
