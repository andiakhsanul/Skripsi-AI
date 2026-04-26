import os
from pathlib import Path

APP_ROOT = Path(__file__).resolve().parent

def resolve_env_path(env_name: str, default: str) -> Path:
    configured_path = Path(os.getenv(env_name, default))
    return configured_path if configured_path.is_absolute() else APP_ROOT / configured_path

def resolve_positive_int(env_name: str, default: int) -> int:
    try:
        return max(1, int(os.getenv(env_name, str(default))))
    except (TypeError, ValueError):
        return default

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
# rendah_tanpa_bantuan: binary flag (0/1) — penghasilan sangat rendah tanpa bantuan
# mismatch_aid_income: binary flag (0/1) — anomali: banyak bantuan tapi penghasilan tinggi
ENGINEERED_BINARY = ["rendah_tanpa_bantuan", "mismatch_aid_income"]
# skor_bantuan_sosial: ordinal 0..5 (jumlah bantuan)
# rasio_tanggungan_penghasilan: ordinal 0..4 (beban ekonomi per tanggungan)
# indeks_kerentanan: ordinal 0..5 (komposit kemiskinan tertimbang)
ENGINEERED_ORDINAL = ["skor_bantuan_sosial", "rasio_tanggungan_penghasilan", "indeks_kerentanan"]

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
MIN_INDIKASI_RECALL = float(os.getenv("MIN_INDIKASI_RECALL", "0.85"))
THRESHOLD_OBJECTIVE = os.getenv("THRESHOLD_OBJECTIVE", "balanced_accuracy_with_recall_constraint")

# Strategi resolusi konflik label (feature vector identik dengan label berbeda):
#   "majority_vote" — ambil label terbanyak (default, kompatibel)
#   "drop_high_minority" — drop group jika minority >= 30% (lebih ketat, kurangi label noise)
#   "drop_all" — drop semua group yang ada konflik (paling agresif)
CONFLICT_STRATEGY = os.getenv("CONFLICT_STRATEGY", "majority_vote")
CONFLICT_MINORITY_THRESHOLD = float(os.getenv("CONFLICT_MINORITY_THRESHOLD", "0.30"))
ML_MAX_THREADS = resolve_positive_int("ML_MAX_THREADS", 2)
CATBOOST_THREAD_COUNT = resolve_positive_int("CATBOOST_THREAD_COUNT", ML_MAX_THREADS)

# Global State / Registry
MODEL_REGISTRY = {"catboost": None, "naive_bayes": None, "metadata": None}
