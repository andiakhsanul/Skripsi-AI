import os
from pathlib import Path
from typing import Optional

import joblib
import pandas as pd
import psycopg2
from psycopg2 import sql
from catboost import CatBoostClassifier
from flask import Flask, jsonify, request
from sklearn.naive_bayes import GaussianNB

app = Flask(__name__)

FEATURE_COLUMNS = ["kip_sma", "penghasilan_gabungan", "daya_listrik"]
TARGET_COLUMN = "label"
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
    positive_values = {"layak", "1", "true", "ya"}
    return 1 if normalized in positive_values else 0


def decode_label(prediction: int) -> str:
    return "Layak" if int(prediction) == 1 else "Tidak Layak"


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
        "SELECT kip_sma, penghasilan_gabungan, daya_listrik, label "
        "FROM {table_name} WHERE is_active = TRUE"
    ).format(table_name=sql.Identifier(TRAINING_TABLE))

    params: tuple[int, ...] = ()
    if schema_version is not None:
        query += sql.SQL(" AND schema_version = %s")
        params = (schema_version,)

    with psycopg2.connect(**db_config()) as conn:
        return pd.read_sql(query.as_string(conn), conn, params=params)


def derive_label_and_confidence(probability_layak: float) -> tuple[str, float]:
    bounded_probability = max(0.0, min(float(probability_layak), 1.0))
    predicted_label = "Layak" if bounded_probability >= 0.5 else "Tidak Layak"
    confidence = bounded_probability if predicted_label == "Layak" else 1.0 - bounded_probability

    return predicted_label, round(confidence, 4)


def infer_with_dual_model(features: pd.DataFrame) -> dict:
    catboost_model = MODEL_REGISTRY["catboost"]
    nb_model = MODEL_REGISTRY["naive_bayes"]

    model_ready = catboost_model is not None and nb_model is not None

    if model_ready:
        cb_probability = float(catboost_model.predict_proba(features)[0][1])
        nb_probability = float(nb_model.predict_proba(features)[0][1])

        pred_cb, confidence_cb = derive_label_and_confidence(cb_probability)
        pred_nb, confidence_nb = derive_label_and_confidence(nb_probability)
    else:
        # Fallback awal agar endpoint tetap bisa dipakai sebelum retrain pertama.
        pred_cb, confidence_cb = "Layak", 0.5
        pred_nb, confidence_nb = "Layak", 0.5

    disagreement_flag = pred_cb != pred_nb
    final_recommendation = pred_cb
    review_priority = (
        "high" if disagreement_flag or confidence_cb < REVIEW_PRIORITY_THRESHOLD else "normal"
    )

    model_results = {
        "catboost": {"label": pred_cb, "confidence": confidence_cb},
        "naive_bayes": {"label": pred_nb, "confidence": confidence_nb},
        "disagreement_flag": disagreement_flag,
        "final_recommendation": final_recommendation,
        "review_priority": review_priority,
        "model_ready": model_ready,
    }

    # Menjaga kompatibilitas dengan kontrak response lama.
    return {
        "catboost_label": pred_cb,
        "catboost_confidence": confidence_cb,
        "naive_bayes_label": pred_nb,
        "naive_bayes_confidence": confidence_nb,
        "catboost_result": pred_cb,
        "naive_bayes_result": pred_nb,
        "final_recommendation": final_recommendation,
        "disagreement_flag": disagreement_flag,
        "review_priority": review_priority,
        "model_ready": model_ready,
        "model_results": model_results,
    }


def train_and_save_models(dataframe: pd.DataFrame) -> dict:
    if dataframe.empty:
        raise ValueError("Data training kosong. Tambahkan data valid terlebih dahulu.")

    required = FEATURE_COLUMNS + [TARGET_COLUMN]
    missing = [column for column in required if column not in dataframe.columns]
    if missing:
        raise ValueError(f"Kolom training tidak lengkap: {', '.join(missing)}")

    cleaned = dataframe.dropna(subset=required).copy()
    if cleaned.empty:
        raise ValueError("Data training tidak valid setelah pembersihan nilai kosong.")

    cleaned[TARGET_COLUMN] = cleaned[TARGET_COLUMN].apply(normalize_label)
    if cleaned[TARGET_COLUMN].nunique() < 2:
        raise ValueError("Data training harus memiliki minimal 2 kelas label.")

    x_train = cleaned[FEATURE_COLUMNS].astype(float)
    y_train = cleaned[TARGET_COLUMN].astype(int)

    # CatBoost dipakai dengan parameter ringan agar cepat untuk iterasi awal.
    catboost_model = CatBoostClassifier(
        iterations=50,
        depth=4,
        learning_rate=0.1,
        verbose=False,
        random_seed=42,
    )
    catboost_model.fit(x_train, y_train)

    nb_model = GaussianNB()
    nb_model.fit(x_train, y_train)

    ensure_model_directory()
    joblib.dump(catboost_model, CATBOOST_MODEL_PATH)
    joblib.dump(nb_model, NAIVE_BAYES_MODEL_PATH)

    MODEL_REGISTRY["catboost"] = catboost_model
    MODEL_REGISTRY["naive_bayes"] = nb_model

    return {
        "rows_used": int(len(cleaned)),
        "class_distribution": cleaned[TARGET_COLUMN].value_counts().to_dict(),
    }


def get_prediction_input(payload: dict) -> pd.DataFrame:
    required_fields = FEATURE_COLUMNS
    missing_fields = [field for field in required_fields if field not in payload]
    if missing_fields:
        raise ValueError(f"Field wajib tidak lengkap: {', '.join(missing_fields)}")

    try:
        values = {
            "kip_sma": float(payload["kip_sma"]),
            "penghasilan_gabungan": float(payload["penghasilan_gabungan"]),
            "daya_listrik": float(payload["daya_listrik"]),
        }
    except (TypeError, ValueError) as exc:
        raise ValueError("Nilai fitur harus berupa angka yang valid") from exc

    return pd.DataFrame([values], columns=FEATURE_COLUMNS)


@app.route("/api/health", methods=["GET"])
def health_check():
    return jsonify(
        {
            "status": "ok",
            "service": "flask-ml-api",
            "model_ready": MODEL_REGISTRY["catboost"] is not None and MODEL_REGISTRY["naive_bayes"] is not None,
            "review_priority_threshold": REVIEW_PRIORITY_THRESHOLD,
        }
    )


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
            jsonify(
                {
                    "status": "error",
                    "message": "Terjadi kesalahan internal pada service ML",
                    "detail": str(exc),
                }
            ),
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
            jsonify(
                {
                    "status": "success",
                    "message": "Retrain model berhasil dijalankan",
                    "schema_version": schema_version,
                    "training_summary": training_result,
                }
            ),
            200,
        )
    except ValueError as exc:
        return jsonify({"status": "error", "message": str(exc)}), 400
    except Exception as exc:
        return (
            jsonify(
                {
                    "status": "error",
                    "message": "Retrain model gagal dijalankan",
                    "detail": str(exc),
                }
            ),
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
