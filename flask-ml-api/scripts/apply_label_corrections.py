"""Apply koreksi label hasil analyze_label_quality.py.

Step:
1. Backup baris yang akan diubah ke spk_training_data_label_backup_<timestamp>
2. UPDATE label & label_class & correction_note untuk kandidat dengan
   suggested_label_class != current_label_class
3. Set admin_corrected = TRUE
4. Tampilkan ringkasan
"""
import json
import os
import sys
from datetime import datetime, timezone
from pathlib import Path

import psycopg2
from psycopg2.extras import RealDictCursor

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT))

from database import db_config

CANDIDATES_PATH = ROOT / "scripts" / "ml_experiments" / "label_quality_candidates.json"


def main():
    if not CANDIDATES_PATH.exists():
        print(f"File kandidat tidak ditemukan: {CANDIDATES_PATH}")
        print("Jalankan analyze_label_quality.py dulu.")
        sys.exit(1)

    with open(CANDIDATES_PATH) as f:
        all_candidates = json.load(f)

    # Hanya ambil yang label-nya benar-benar berubah
    changes = [c for c in all_candidates if c["suggested_label_class"] != c["current_label_class"]]
    if not changes:
        print("Tidak ada perubahan label yang perlu di-apply.")
        return

    print(f"Akan apply {len(changes)} koreksi label:")
    by_direction = {}
    for c in changes:
        key = f"{c['current_label']} → {c['suggested_label']}"
        by_direction[key] = by_direction.get(key, 0) + 1
    for k, v in by_direction.items():
        print(f"  {k}: {v}")

    timestamp = datetime.now(timezone.utc).strftime("%Y%m%d_%H%M%S")
    backup_table = f"spk_training_data_label_backup_{timestamp}"

    with psycopg2.connect(**db_config()) as conn:
        with conn.cursor(cursor_factory=RealDictCursor) as cursor:
            # 1. BACKUP
            app_ids = tuple(c["source_application_id"] for c in changes)
            print(f"\n[1/2] Backup ke tabel: {backup_table}")
            cursor.execute(f"""
                CREATE TABLE {backup_table} AS
                SELECT id, source_application_id, label, label_class,
                       admin_corrected, correction_note, updated_at
                FROM spk_training_data
                WHERE source_application_id IN %s
            """, (app_ids,))
            cursor.execute(f"SELECT COUNT(*) AS cnt FROM {backup_table}")
            cnt = cursor.fetchone()["cnt"]
            print(f"  {cnt} baris di-backup.")

            # 2. UPDATE per row
            print(f"\n[2/2] Apply UPDATE...")
            updated = 0
            for c in changes:
                app_id = c["source_application_id"]
                new_label = c["suggested_label"]
                new_label_class = c["suggested_label_class"]
                note = (
                    f"[label-quality-analysis {timestamp}] "
                    f"{c['current_label']} → {new_label}. "
                    f"conf={c['confidence']:.2f}, "
                    f"vuln={c['vulnerability_score']:.2f}, "
                    f"oof_p={c['oof_proba_indikasi']:.2f}. "
                    f"reasons: {c['reasons']}"
                )
                cursor.execute("""
                    UPDATE spk_training_data
                    SET label = %s,
                        label_class = %s,
                        admin_corrected = TRUE,
                        correction_note = %s,
                        updated_at = NOW()
                    WHERE source_application_id = %s
                      AND is_active = TRUE
                """, (new_label, new_label_class, note, app_id))
                updated += cursor.rowcount

        conn.commit()

    print(f"  {updated} baris ter-update.")
    print(f"\nDONE.")
    print(f"Backup table: {backup_table}")
    print(f"  Untuk rollback: UPDATE spk_training_data SET label = b.label, label_class = b.label_class")
    print(f"                  FROM {backup_table} b WHERE spk_training_data.id = b.id;")


if __name__ == "__main__":
    main()
