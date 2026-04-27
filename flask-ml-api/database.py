import os
from datetime import datetime
from typing import Optional

import pandas as pd
import psycopg2
from psycopg2 import sql
from psycopg2.extras import Json, RealDictCursor

from config import MODEL_VERSIONS_TABLE, TRAINING_TABLE


def db_config() -> dict:
    return {
        "host": os.getenv("DB_HOST", "db"),
        "port": os.getenv("DB_PORT", "5432"),
        "dbname": os.getenv("DB_NAME", "spk_kipk_db"),
        "user": os.getenv("DB_USER", "postgres"),
        "password": os.getenv("DB_PASSWORD", "postgres"),
    }


def fetch_latest_model_version_record(status: str = "ready") -> Optional[dict]:
    query = sql.SQL(
        """
        SELECT
            id,
            version_name,
            schema_version,
            status,
            is_current,
            catboost_artifact_path,
            naive_bayes_artifact_path,
            trained_at,
            activated_at
        FROM {table_name}
        WHERE status = %s
        ORDER BY trained_at DESC NULLS LAST, id DESC
        LIMIT 1
        """
    ).format(table_name=sql.Identifier(MODEL_VERSIONS_TABLE))

    with psycopg2.connect(**db_config()) as conn:
        with conn.cursor(cursor_factory=RealDictCursor) as cursor:
            cursor.execute(query, (status,))
            row = cursor.fetchone()
            return dict(row) if row else None


def fetch_active_model_version_record(status: str = "ready") -> Optional[dict]:
    query = sql.SQL(
        """
        SELECT
            id,
            version_name,
            schema_version,
            status,
            is_current,
            catboost_artifact_path,
            naive_bayes_artifact_path,
            trained_at,
            activated_at
        FROM {table_name}
        WHERE status = %s
          AND is_current = TRUE
        ORDER BY activated_at DESC NULLS LAST, trained_at DESC NULLS LAST, id DESC
        LIMIT 1
        """
    ).format(table_name=sql.Identifier(MODEL_VERSIONS_TABLE))

    with psycopg2.connect(**db_config()) as conn:
        with conn.cursor(cursor_factory=RealDictCursor) as cursor:
            cursor.execute(query, (status,))
            row = cursor.fetchone()
            return dict(row) if row else None


def fetch_model_version_record_by_id(model_version_id: int, status: Optional[str] = None) -> Optional[dict]:
    clauses = [sql.SQL("id = %s")]
    params = [model_version_id]

    if status is not None:
        clauses.append(sql.SQL("status = %s"))
        params.append(status)

    query = sql.SQL(
        """
        SELECT
            id,
            version_name,
            schema_version,
            status,
            is_current,
            catboost_artifact_path,
            naive_bayes_artifact_path,
            trained_at,
            activated_at
        FROM {table_name}
        WHERE {where_clause}
        LIMIT 1
        """
    ).format(
        table_name=sql.Identifier(MODEL_VERSIONS_TABLE),
        where_clause=sql.SQL(" AND ").join(clauses),
    )

    with psycopg2.connect(**db_config()) as conn:
        with conn.cursor(cursor_factory=RealDictCursor) as cursor:
            cursor.execute(query, tuple(params))
            row = cursor.fetchone()
            return dict(row) if row else None


def fetch_training_dataframe(schema_version: Optional[int] = None) -> pd.DataFrame:
    """Ambil SEMUA raw applicant data terlabel (status Verified/Rejected).

    Label default berasal dari status aplikasi:
      Verified → Layak (label_class=0)
      Rejected → Indikasi (label_class=1)

    Jika baris sudah muncul di spk_training_data sebagai admin_corrected,
    label dari training_data dipakai (menghormati koreksi admin).

    Flask melakukan encoding sendiri dari raw fields — tidak mengandalkan
    kolom encoded di DB.
    """
    query = sql.SQL(
        """
        SELECT
            sa.id AS source_application_id,
            sa.schema_version,
            sa.kip, sa.pkh, sa.kks, sa.dtks, sa.sktm,
            sa.penghasilan_ayah_rupiah, sa.penghasilan_ibu_rupiah,
            sa.penghasilan_gabungan_rupiah,
            sa.jumlah_tanggungan_raw, sa.anak_ke_raw,
            sa.status_orangtua_text, sa.status_rumah_text, sa.daya_listrik_text,
            CASE WHEN sa.status = 'Verified' THEN 'Layak' ELSE 'Indikasi' END AS default_label,
            CASE WHEN sa.status = 'Verified' THEN 0 ELSE 1 END AS default_label_class,
            td.label AS corrected_label,
            td.label_class AS corrected_label_class,
            COALESCE(td.admin_corrected, FALSE) AS admin_corrected
        FROM student_applications sa
        LEFT JOIN {training_table} td
               ON td.source_application_id = sa.id
              AND td.is_active = TRUE
              AND COALESCE(td.admin_corrected, FALSE) = TRUE
        WHERE sa.status IN ('Verified', 'Rejected')
        """
    ).format(training_table=sql.Identifier(TRAINING_TABLE))

    params: tuple[int, ...] = ()
    if schema_version is not None:
        query += sql.SQL(" AND sa.schema_version = %s")
        params = (schema_version,)

    with psycopg2.connect(**db_config()) as conn:
        df = pd.read_sql(query.as_string(conn), conn, params=params)

    # Terapkan koreksi admin bila ada, jika tidak gunakan default dari status.
    df["label"] = df["corrected_label"].where(df["admin_corrected"], df["default_label"])
    df["label_class"] = df["corrected_label_class"].where(
        df["admin_corrected"], df["default_label_class"]
    ).astype(int)

    return df.drop(columns=[
        "default_label", "default_label_class",
        "corrected_label", "corrected_label_class", "admin_corrected",
    ])


def persist_model_version_record(payload: dict) -> dict:
    query = sql.SQL(
        """
        INSERT INTO {table_name} (
            version_name,
            schema_version,
            status,
            is_current,
            triggered_by_user_id,
            triggered_by_email,
            training_table,
            primary_model,
            secondary_model,
            dataset_rows_total,
            rows_used,
            train_rows,
            validation_rows,
            validation_strategy,
            class_distribution,
            catboost_artifact_path,
            naive_bayes_artifact_path,
            catboost_train_accuracy,
            catboost_validation_accuracy,
            naive_bayes_train_accuracy,
            naive_bayes_validation_accuracy,
            catboost_metrics,
            naive_bayes_metrics,
            note,
            error_message,
            trained_at,
            activated_at,
            created_at,
            updated_at
        ) VALUES (
            %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s
        )
        RETURNING id, version_name, schema_version, status, is_current, trained_at, activated_at
        """
    ).format(table_name=sql.Identifier(MODEL_VERSIONS_TABLE))

    now = datetime.now() # postgres runs with its own TZ typically, but let's keep it consistent
    from datetime import timezone
    now_utc = datetime.now(timezone.utc)

    params = (
        payload["version_name"],
        payload.get("schema_version"),
        payload.get("status", "ready"),
        bool(payload.get("is_current", False)),
        payload.get("triggered_by_user_id"),
        payload.get("triggered_by_email"),
        payload.get("training_table", TRAINING_TABLE),
        payload.get("primary_model", "catboost"),
        payload.get("secondary_model", "categorical_nb"),
        payload.get("dataset_rows_total"),
        payload.get("rows_used"),
        payload.get("train_rows"),
        payload.get("validation_rows"),
        payload.get("validation_strategy"),
        Json(payload.get("class_distribution")) if payload.get("class_distribution") is not None else None,
        payload.get("catboost_artifact_path"),
        payload.get("naive_bayes_artifact_path"),
        payload.get("catboost_train_accuracy"),
        payload.get("catboost_validation_accuracy"),
        payload.get("naive_bayes_train_accuracy"),
        payload.get("naive_bayes_validation_accuracy"),
        Json(payload.get("catboost_metrics")) if payload.get("catboost_metrics") is not None else None,
        Json(payload.get("naive_bayes_metrics")) if payload.get("naive_bayes_metrics") is not None else None,
        payload.get("note"),
        payload.get("error_message"),
        payload.get("trained_at"),
        payload.get("activated_at"),
        now_utc,
        now_utc,
    )

    with psycopg2.connect(**db_config()) as conn:
        with conn.cursor(cursor_factory=RealDictCursor) as cursor:
            cursor.execute(query, params)
            row = cursor.fetchone()
            return dict(row)


def mark_model_version_as_current(model_version_id: int, activated_at: Optional[datetime] = None) -> dict:
    from datetime import timezone
    activation_time = activated_at or datetime.now(timezone.utc)

    with psycopg2.connect(**db_config()) as conn:
        with conn.cursor(cursor_factory=RealDictCursor) as cursor:
            cursor.execute(
                sql.SQL(
                    """
                    UPDATE {table_name}
                    SET is_current = FALSE, updated_at = %s
                    WHERE is_current = TRUE
                    """
                ).format(table_name=sql.Identifier(MODEL_VERSIONS_TABLE)),
                (activation_time,),
            )

            cursor.execute(
                sql.SQL(
                    """
                    UPDATE {table_name}
                    SET is_current = TRUE, activated_at = %s, updated_at = %s
                    WHERE id = %s AND status = 'ready'
                    RETURNING id, version_name, schema_version, status, is_current, catboost_artifact_path, naive_bayes_artifact_path, trained_at, activated_at
                    """
                ).format(table_name=sql.Identifier(MODEL_VERSIONS_TABLE)),
                (activation_time, activation_time, model_version_id),
            )
            row = cursor.fetchone()

        conn.commit()

    if not row:
        raise ValueError("Versi model siap tidak ditemukan atau tidak dapat diaktifkan.")

    return dict(row)


def fetch_recent_model_versions(limit: int = 10) -> list[dict]:
    """Ambil N versi model 'ready' terbaru, dari yang terbaru.

    Dipakai untuk:
    - Auto-trigger Optuna tuning (lihat pertumbuhan data + tren metrik)
    - Endpoint /api/training/insights
    """
    query = sql.SQL(
        """
        SELECT
            id, version_name, status, is_current,
            dataset_rows_total, rows_used, train_rows, validation_rows,
            catboost_train_accuracy, catboost_validation_accuracy,
            naive_bayes_train_accuracy, naive_bayes_validation_accuracy,
            catboost_metrics, naive_bayes_metrics,
            note, trained_at, activated_at
        FROM {table_name}
        WHERE status = 'ready'
        ORDER BY trained_at DESC
        LIMIT %s
        """
    ).format(table_name=sql.Identifier(MODEL_VERSIONS_TABLE))

    with psycopg2.connect(**db_config()) as conn:
        with conn.cursor(cursor_factory=RealDictCursor) as cursor:
            cursor.execute(query, (int(limit),))
            rows = cursor.fetchall()
    return [dict(row) for row in rows]


def fetch_latest_tuning_artifact() -> Optional[dict]:
    """Ambil best_params Optuna dari versi terakhir yang punya tuning_summary.

    Returns dict berisi `best_params` (siap dipakai sebagai warm-start trial) +
    metadata, atau None kalau tidak ada riwayat tuning.
    """
    query = sql.SQL(
        """
        SELECT
            id, version_name, dataset_rows_total, catboost_metrics, trained_at
        FROM {table_name}
        WHERE status = 'ready'
          AND catboost_metrics IS NOT NULL
          AND catboost_metrics ? 'tuning_summary'
        ORDER BY trained_at DESC
        LIMIT 1
        """
    ).format(table_name=sql.Identifier(MODEL_VERSIONS_TABLE))

    with psycopg2.connect(**db_config()) as conn:
        with conn.cursor(cursor_factory=RealDictCursor) as cursor:
            cursor.execute(query)
            row = cursor.fetchone()

    if not row:
        return None
    metrics = row["catboost_metrics"] or {}
    tuning_summary = metrics.get("tuning_summary") or {}
    best_params = tuning_summary.get("best_params") or None
    if not best_params:
        return None
    return {
        "version_id": row["id"],
        "version_name": row["version_name"],
        "dataset_rows_total": row["dataset_rows_total"],
        "trained_at": row["trained_at"],
        "best_params": best_params,
        "best_balanced_accuracy": tuning_summary.get("best_balanced_accuracy"),
    }


def purge_all_training_data() -> int:
    """Hapus SEMUA baris di spk_training_data. Return jumlah baris yang dihapus."""
    with psycopg2.connect(**db_config()) as conn:
        with conn.cursor() as cursor:
            cursor.execute(
                sql.SQL("DELETE FROM {table_name}").format(
                    table_name=sql.Identifier(TRAINING_TABLE)
                )
            )
            deleted_count = cursor.rowcount
        conn.commit()
    return deleted_count


def purge_model_artifacts() -> list[str]:
    """Hapus semua file model .joblib versioned di direktori models/. Kembalikan daftar file yang dihapus."""
    import glob
    from pathlib import Path
    from config import CATBOOST_MODEL_PATH

    models_dir = CATBOOST_MODEL_PATH.parent
    deleted_files = []
    for pattern in ["catboost_*.joblib", "naive_bayes_*.joblib"]:
        for filepath in glob.glob(str(models_dir / pattern)):
            path = Path(filepath)
            # Jangan hapus file canonical (tanpa timestamp)
            if path.name in ("catboost_model.joblib", "naive_bayes_model.joblib"):
                continue
            try:
                path.unlink()
                deleted_files.append(path.name)
            except OSError:
                pass
    return deleted_files
