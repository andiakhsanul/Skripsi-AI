import os
from pathlib import Path
from typing import Optional

import joblib
import pandas as pd
import psycopg2
from psycopg2 import sql
from catboost import CatBoostClassifier
from flask import Flask, jsonify, request
from sklearn.metrics import accuracy_score
from sklearn.naive_bayes import GaussianNB

app = Flask(__name__)

# ─── Definisi 13 Fitur SPK KIP-K ─────────────────────────────────────────────
# Biner (0/1):  0=Tidak, 1=Ya (Prioritas 1 = paling rentan)
BINARY_FEATURES = ["kip", "pkh", "kks", "dtks", "sktm"]

# Ordinal (1/2/3): 1=Prioritas 1 (rentan), 3=Prioritas 3 (lebih baik)
#  penghasilan_gabungan/ayah/ibu: 1=<1jt, 2=1-4jt, 3=≥4jt
#  jumlah_tanggungan: 1=≥6 orang, 2=4-5 orang, 3=0-3 orang
#  anak_ke:           1=≥anak ke-5, 2=anak ke-3/4, 3=anak ke-1/2
#  status_orangtua:   1=Yatim Piatu, 2=Yatim/Piatu, 3=Lengkap
#  status_rumah:      1=Tidak Punya, 2=Sewa/Menumpang, 3=Milik Sendiri
#  daya_listrik:      1=Non-PLN, 2=PLN 450-900VA, 3=PLN >900VA
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
FEATURE_COLUMNS = BINARY_FEATURES + ORDINAL_FEATURES
TARGET_COLUMN = "label_class"
REVIEW_PRIORITY_THRESHOLD = float(os.getenv("REVIEW_PRIORITY_THRESHOLD", "0.65"))

CATBOOST_MODEL_PATH = Path(os.getenv("CATBOOST_MODEL_PATH", "models/catboost_model.joblib"))
NAIVE_BAYES_MODEL_PATH = Path(os.getenv("NAIVE_BAYES_MODEL_PATH", "models/naive_bayes_model.joblib"))
TRAINING_TABLE = os.getenv("TRAINING_TABLE", "spk_training_data")
FLASK_INTERNAL_TOKEN = os.getenv("FLASK_INTERNAL_TOKEN", "spk_internal_dev_token")

MODEL_REGISTRY = {"catboost": None, "naive_bayes": None}


def db_config() -> dict:
    return {
        "host": os.getenv("DB_HOST", "db"),
        "port": os.getenv("DB_PORT", "5432"),
        "dbname": os.getenv("DB_NAME", "spk_kipk_db"),
        "user": os.getenv("DB_USER", "postgres"),
        "password": os.getenv("DB_PASSWORD", "postgres"),
    }


def ensure_model_directory() -> None:
    CATBOOST_MODEL_PATH.parent.mkdir(parents=True, exist_ok=True)
    NAIVE_BAYES_MODEL_PATH.parent.mkdir(parents=True, exist_ok=True)


def normalize_label(raw_value: str) -> int:
    normalized = str(raw_value).strip().lower()
    positive_values = {"indikasi", "1", "true", "ya"}
    return 1 if normalized in positive_values else 0


def check_internal_token() -> bool:
    incoming_token = request.headers.get("X-Internal-Token", "")
    return incoming_token == FLASK_INTERNAL_TOKEN


def load_saved_models() -> None:
    if CATBOOST_MODEL_PATH.exists():
        MODEL_REGISTRY["catboost"] = joblib.load(CATBOOST_MODEL_PATH)
    if NAIVE_BAYES_MODEL_PATH.exists():
        MODEL_REGISTRY["naive_bayes"] = joblib.load(NAIVE_BAYES_MODEL_PATH)


def fetch_training_dataframe(schema_version: Optional[int] = None) -> pd.DataFrame:
    query = sql.SQL(
        "SELECT kip, pkh, kks, dtks, sktm, "
        "penghasilan_gabungan, penghasilan_ayah, penghasilan_ibu, "
        "jumlah_tanggungan, anak_ke, status_orangtua, status_rumah, "
        "daya_listrik, label, label_class "
        "FROM {table_name} WHERE is_active = TRUE"
    ).format(table_name=sql.Identifier(TRAINING_TABLE))

    params: tuple[int, ...] = ()
    if schema_version is not None:
        query += sql.SQL(" AND schema_version = %s")
        params = (schema_version,)

    with psycopg2.connect(**db_config()) as conn:
        return pd.read_sql(query.as_string(conn), conn, params=params)


def derive_label_and_confidence(probability_indikasi: float) -> tuple[str, float]:
    bounded_probability = max(0.0, min(float(probability_indikasi), 1.0))
    predicted_label = "Indikasi" if bounded_probability >= 0.5 else "Layak"
    confidence = bounded_probability if predicted_label == "Indikasi" else 1.0 - bounded_probability
    return predicted_label, round(confidence, 4)


def probability_for_class(model, features: pd.DataFrame, target_class: int = 1) -> float:
    probabilities = model.predict_proba(features)[0]
    classes = list(getattr(model, "classes_", []))

    if target_class in classes:
        index = classes.index(target_class)
        return float(probabilities[index])

    if len(probabilities) == 1:
        return float(probabilities[0])

    return float(probabilities[1])


def infer_with_dual_model(features: pd.DataFrame) -> dict:
    """
    Inferensi dual-model: CatBoost (primer) + Naive Bayes (sekunder).
    final_recommendation SELALU mengikuti CatBoost sebagai model utama.
    Naive Bayes berfungsi sebagai pembanding untuk mendeteksi ketidaksepakatan.
    """
    catboost_model = MODEL_REGISTRY["catboost"]
    nb_model = MODEL_REGISTRY["naive_bayes"]

    model_ready = catboost_model is not None and nb_model is not None

    if model_ready:
        cb_probability = probability_for_class(catboost_model, features, target_class=1)
        nb_probability = probability_for_class(nb_model, features, target_class=1)

        pred_cb, confidence_cb = derive_label_and_confidence(cb_probability)
        pred_nb, confidence_nb = derive_label_and_confidence(nb_probability)
    else:
        # Fallback: model belum dilatih, gunakan nilai default
        pred_cb, confidence_cb = "Indikasi", 0.5
        pred_nb, confidence_nb = "Indikasi", 0.5

    disagreement_flag = pred_cb != pred_nb

    # ★ final_recommendation SELALU dari CatBoost (model primer)
    final_recommendation = pred_cb

    review_priority = (
        "high" if disagreement_flag or confidence_cb < REVIEW_PRIORITY_THRESHOLD else "normal"
    )

    model_results = {
        "catboost":            {"label": pred_cb, "confidence": confidence_cb},
        "naive_bayes":         {"label": pred_nb, "confidence": confidence_nb},
        "disagreement_flag":   disagreement_flag,
        "final_recommendation": final_recommendation,
        "review_priority":     review_priority,
        "model_ready":         model_ready,
    }

    return {
        "catboost_label":          pred_cb,
        "catboost_confidence":     confidence_cb,
        "naive_bayes_label":       pred_nb,
        "naive_bayes_confidence":  confidence_nb,
        "catboost_result":         pred_cb,
        "naive_bayes_result":      pred_nb,
        "final_recommendation":    final_recommendation,   # Selalu CatBoost
        "disagreement_flag":       disagreement_flag,
        "review_priority":         review_priority,
        "model_ready":             model_ready,
        "model_results":           model_results,
    }


def train_and_save_models(dataframe: pd.DataFrame) -> dict:
    if dataframe.empty:
        raise ValueError("Data training kosong. Tambahkan data valid terlebih dahulu.")

    missing = [column for column in FEATURE_COLUMNS if column not in dataframe.columns]
    if missing:
        raise ValueError(f"Kolom training tidak lengkap: {', '.join(missing)}")

    cleaned = dataframe.dropna(subset=FEATURE_COLUMNS).copy()
    if cleaned.empty:
        raise ValueError("Data training tidak valid setelah pembersihan nilai kosong.")

    if TARGET_COLUMN in cleaned.columns:
        if cleaned[TARGET_COLUMN].isnull().any():
            if "label" not in cleaned.columns:
                raise ValueError("Kolom label_class mengandung nilai kosong dan kolom label tidak tersedia.")
            cleaned[TARGET_COLUMN] = cleaned[TARGET_COLUMN].fillna(
                cleaned["label"].apply(normalize_label)
            )
    elif "label" in cleaned.columns:
        cleaned[TARGET_COLUMN] = cleaned["label"].apply(normalize_label)
    else:
        raise ValueError("Kolom target tidak tersedia. Minimal butuh label atau label_class.")

    cleaned[TARGET_COLUMN] = cleaned[TARGET_COLUMN].astype(int)
    if cleaned[TARGET_COLUMN].nunique() < 2:
        raise ValueError("Data training harus memiliki minimal 2 kelas label.")

    x_train = cleaned[FEATURE_COLUMNS].astype(float)
    y_train = cleaned[TARGET_COLUMN].astype(int)

    # ── CatBoost (Model Primer) ───────────────────────────────────────────────
    catboost_model = CatBoostClassifier(
        iterations=100,
        depth=4,
        learning_rate=0.1,
        verbose=False,
        random_seed=42,
        eval_metric="Accuracy",
    )
    catboost_model.fit(x_train, y_train)
    cb_predictions = catboost_model.predict(x_train)
    cb_accuracy = float(accuracy_score(y_train, cb_predictions))

    # ── Naive Bayes (Model Sekunder / Pembanding) ─────────────────────────────
    nb_model = GaussianNB()
    nb_model.fit(x_train, y_train)
    nb_predictions = nb_model.predict(x_train)
    nb_accuracy = float(accuracy_score(y_train, nb_predictions))

    ensure_model_directory()
    joblib.dump(catboost_model, CATBOOST_MODEL_PATH)
    joblib.dump(nb_model, NAIVE_BAYES_MODEL_PATH)

    MODEL_REGISTRY["catboost"] = catboost_model
    MODEL_REGISTRY["naive_bayes"] = nb_model

    class_dist = cleaned[TARGET_COLUMN].value_counts().to_dict()

    return {
        "rows_used":             int(len(cleaned)),
        "class_distribution":    class_dist,
        "catboost_accuracy":     round(cb_accuracy, 4),
        "naive_bayes_accuracy":  round(nb_accuracy, 4),
        "primary_model":         "catboost",
        "note": (
            "CatBoost adalah model primer. "
            "Naive Bayes digunakan sebagai pembanding untuk deteksi disagreement."
        ),
    }


def get_prediction_input(payload: dict) -> pd.DataFrame:
    required_fields = FEATURE_COLUMNS
    missing_fields = [field for field in required_fields if field not in payload]
    if missing_fields:
        raise ValueError(f"Field wajib tidak lengkap: {', '.join(missing_fields)}")

    try:
        values = {}

        for feature in BINARY_FEATURES:
            parsed_value = int(payload[feature])
            if parsed_value not in (0, 1):
                raise ValueError(f"Field {feature} wajib bernilai 0 atau 1 (biner)")
            values[feature] = float(parsed_value)

        for feature in ORDINAL_FEATURES:
            parsed_value = int(payload[feature])
            if parsed_value not in (1, 2, 3):
                raise ValueError(f"Field {feature} wajib bernilai 1, 2, atau 3 (ordinal)")
            values[feature] = float(parsed_value)
    except (TypeError, ValueError) as exc:
        raise ValueError("Nilai fitur harus berupa angka yang valid") from exc

    return pd.DataFrame([values], columns=FEATURE_COLUMNS)


# ─── Endpoints ────────────────────────────────────────────────────────────────

@app.route("/api/health", methods=["GET"])
def health_check():
    catboost_ready = MODEL_REGISTRY["catboost"] is not None
    nb_ready = MODEL_REGISTRY["naive_bayes"] is not None
    return jsonify({
        "status":                   "ok",
        "service":                  "flask-ml-api",
        "model_ready":              catboost_ready and nb_ready,
        "catboost_ready":           catboost_ready,
        "naive_bayes_ready":        nb_ready,
        "primary_model":            "catboost",
        "review_priority_threshold": REVIEW_PRIORITY_THRESHOLD,
    })


@app.route("/api/predict", methods=["POST"])
def predict():
    try:
        payload = request.get_json(silent=True)
        if not payload:
            return jsonify({"status": "error", "message": "Payload JSON tidak ditemukan"}), 400

        features = get_prediction_input(payload)
        prediction = infer_with_dual_model(features)

        return jsonify({"status": "success", **prediction}), 200
    except ValueError as exc:
        return jsonify({"status": "error", "message": str(exc)}), 400
    except Exception as exc:
        return (
            jsonify({
                "status":  "error",
                "message": "Terjadi kesalahan internal pada service ML",
                "detail":  str(exc),
            }),
            500,
        )


@app.route("/api/retrain", methods=["POST"])
def retrain_model():
    try:
        if not check_internal_token():
            return jsonify({"status": "error", "message": "Token internal tidak valid"}), 401

        payload = request.get_json(silent=True) or {}

        schema_version = None
        if "schema_version" in payload and payload["schema_version"] is not None:
            try:
                schema_version = int(payload["schema_version"])
            except (TypeError, ValueError) as exc:
                raise ValueError("schema_version harus berupa angka integer") from exc

            if schema_version <= 0:
                raise ValueError("schema_version harus lebih besar dari 0")

        dataframe = fetch_training_dataframe(schema_version=schema_version)
        training_result = train_and_save_models(dataframe)

        return (
            jsonify({
                "status":           "success",
                "message":          "Retrain model berhasil dijalankan",
                "schema_version":   schema_version,
                "training_summary": training_result,
            }),
            200,
        )
    except ValueError as exc:
        return jsonify({"status": "error", "message": str(exc)}), 400
    except Exception as exc:
        return (
            jsonify({
                "status":  "error",
                "message": "Retrain model gagal dijalankan",
                "detail":  str(exc),
            }),
            500,
        )


try:
    load_saved_models()
except Exception:
    pass


if __name__ == "__main__":
    flask_host = os.getenv("FLASK_HOST", "0.0.0.0")
    flask_port = int(os.getenv("FLASK_PORT", "5000"))
    app.run(host=flask_host, port=flask_port)
