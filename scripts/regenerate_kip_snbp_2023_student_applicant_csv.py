#!/usr/bin/env python3

from __future__ import annotations

import argparse
import csv
import json
import sys
from collections import Counter
from datetime import datetime, timezone
from pathlib import Path

import export_kip_snbp_2023_student_applicant_csv as applicant_export
import export_kip_snbp_2023_feature_csv as source

PRESERVED_FIELDS = [
    "applicant_name",
    "applicant_email",
    "study_program",
    "faculty",
    "source_reference_number",
    "source_document_link",
    "source_label_text",
    "kip",
    "pkh",
    "kks",
    "dtks",
    "sktm",
    "penghasilan_ayah_rupiah",
    "penghasilan_ibu_rupiah",
    "penghasilan_gabungan_rupiah",
    "jumlah_tanggungan_raw",
    "anak_ke_raw",
    "status_orangtua_text",
    "status_rumah_text",
    "daya_listrik_text",
    "status",
    "admin_decision",
    "admin_decision_note",
]

EDITABLE_REVIEW_FIELDS = [
    "penghasilan_ayah_rupiah",
    "penghasilan_ibu_rupiah",
    "penghasilan_gabungan_rupiah",
    "jumlah_tanggungan_raw",
    "anak_ke_raw",
    "status_orangtua_text",
    "status_rumah_text",
    "daya_listrik_text",
]


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Regenerate CSV applicant dari Excel asli sambil mempertahankan koreksi yang sudah ada.",
    )
    parser.add_argument(
        "--input",
        default="/Users/andiakhsanul/Downloads/Verifikasi KIP SNBP 2023.xlsx",
        help="Path ke file Excel mentah.",
    )
    parser.add_argument(
        "--existing-csv",
        default="infra/data/processed/kip_snbp_2023_student_applications_raw.csv",
        help="CSV hasil koreksi saat ini yang ingin dipertahankan.",
    )
    parser.add_argument(
        "--output-dir",
        default="infra/data/processed",
        help="Direktori output untuk file regenerated.",
    )
    return parser.parse_args()


def load_existing_rows(path: Path) -> dict[str, dict[str, str]]:
    if not path.exists():
        return {}

    with path.open(newline="", encoding="utf-8") as handle:
        rows = list(csv.DictReader(handle))

    indexed: dict[str, dict[str, str]] = {}
    for row in rows:
        key = str(row.get("source_row_number", "")).strip()
        if key:
            indexed[key] = row
    return indexed


def is_truthy(value: object) -> bool:
    return str(value or "").strip().lower() in {"1", "true", "yes", "ya"}


def build_cleaning_notes(row: dict[str, object]) -> list[str]:
    notes: list[str] = []

    for token in str(row.get("cleaning_notes", "")).split("|"):
        item = token.strip()
        if item and item != "status_rumah_perlu_isi_manual":
            notes.append(item)

    if not str(row.get("status_rumah_text", "")).strip():
        notes.append("status_rumah_perlu_isi_manual")

    deduped: list[str] = []
    seen: set[str] = set()
    for note in notes:
        if note in seen:
            continue
        seen.add(note)
        deduped.append(note)
    return deduped


def merge_existing_values(base_row: dict[str, object], existing_row: dict[str, str] | None) -> dict[str, object]:
    merged = dict(base_row)

    if existing_row is None:
        notes = build_cleaning_notes(merged)
        merged["cleaning_notes"] = " | ".join(notes)
        merged["manual_house_review"] = 1 if not str(merged.get("status_rumah_text", "")).strip() else 0
        merged["manual_review_required"] = 1 if notes else 0
        return merged

    for field in PRESERVED_FIELDS:
        value = str(existing_row.get(field, "")).strip()
        if value != "":
            merged[field] = value

    notes = build_cleaning_notes(
        {
            **merged,
            "cleaning_notes": existing_row.get("cleaning_notes", merged.get("cleaning_notes", "")),
        }
    )

    merged["cleaning_notes"] = " | ".join(notes)
    merged["manual_house_review"] = 1 if not str(merged.get("status_rumah_text", "")).strip() else 0
    merged["manual_review_required"] = 1 if notes else 0

    return merged


def build_review_rows(rows: list[dict[str, object]]) -> list[dict[str, object]]:
    review_rows: list[dict[str, object]] = []
    for row in rows:
        if not is_truthy(row.get("manual_review_required")):
            continue

        review_rows.append(
            {
                "source_row_number": row.get("source_row_number", ""),
                "applicant_name": row.get("applicant_name", ""),
                "study_program": row.get("study_program", ""),
                "faculty": row.get("faculty", ""),
                "status": row.get("status", ""),
                "manual_house_review": row.get("manual_house_review", 0),
                "manual_review_required": row.get("manual_review_required", 0),
                "cleaning_notes": row.get("cleaning_notes", ""),
                "status_rumah_text": row.get("status_rumah_text", ""),
                "raw_status_rumah_condition": row.get("raw_status_rumah_condition", ""),
                "raw_status_rumah_notes": row.get("raw_status_rumah_notes", ""),
                "penghasilan_ayah_rupiah": row.get("penghasilan_ayah_rupiah", ""),
                "penghasilan_ibu_rupiah": row.get("penghasilan_ibu_rupiah", ""),
                "penghasilan_gabungan_rupiah": row.get("penghasilan_gabungan_rupiah", ""),
                "jumlah_tanggungan_raw": row.get("jumlah_tanggungan_raw", ""),
                "anak_ke_raw": row.get("anak_ke_raw", ""),
                "status_orangtua_text": row.get("status_orangtua_text", ""),
                "daya_listrik_text": row.get("daya_listrik_text", ""),
                "source_label_text": row.get("source_label_text", ""),
                "source_document_link": row.get("source_document_link", ""),
            }
        )
    return review_rows


def build_changes_preview(
    regenerated_rows: list[dict[str, object]],
    existing_rows: dict[str, dict[str, str]],
) -> list[dict[str, object]]:
    preview: list[dict[str, object]] = []

    for row in regenerated_rows:
        key = str(row.get("source_row_number", "")).strip()
        existing = existing_rows.get(key)
        if existing is None:
            preview.append(
                {
                    "source_row_number": key,
                    "applicant_name": row.get("applicant_name", ""),
                    "change_type": "new_from_excel",
                    "changed_fields": "",
                    "manual_review_required": row.get("manual_review_required", 0),
                    "cleaning_notes": row.get("cleaning_notes", ""),
                }
            )
            continue

        changed_fields: list[str] = []
        for field in EDITABLE_REVIEW_FIELDS:
            before = str(existing.get(field, "")).strip()
            after = str(row.get(field, "")).strip()
            if before != after:
                changed_fields.append(field)

        if changed_fields:
            preview.append(
                {
                    "source_row_number": key,
                    "applicant_name": row.get("applicant_name", ""),
                    "change_type": "merged_with_existing_corrections",
                    "changed_fields": ", ".join(changed_fields),
                    "manual_review_required": row.get("manual_review_required", 0),
                    "cleaning_notes": row.get("cleaning_notes", ""),
                }
            )

    return preview


def write_csv(path: Path, headers: list[str], rows: list[dict[str, object]]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", newline="", encoding="utf-8") as handle:
        writer = csv.DictWriter(handle, fieldnames=headers)
        writer.writeheader()
        for row in rows:
            writer.writerow({key: row.get(key, "") for key in headers})


def build_summary(
    regenerated_rows: list[dict[str, object]],
    review_rows: list[dict[str, object]],
    existing_rows: dict[str, dict[str, str]],
    source_path: Path,
    existing_csv: Path,
) -> dict[str, object]:
    status_counts = Counter(str(row.get("status", "")).strip() for row in regenerated_rows if str(row.get("status", "")).strip())
    note_counts = Counter()
    for row in regenerated_rows:
        for part in str(row.get("cleaning_notes", "")).split("|"):
            item = part.strip()
            if item:
                note_counts[item] += 1

    preserved_corrections = 0
    for row in regenerated_rows:
        existing = existing_rows.get(str(row.get("source_row_number", "")).strip())
        if existing is None:
            continue
        if str(existing.get("status_rumah_text", "")).strip() and str(row.get("status_rumah_text", "")).strip() == str(existing.get("status_rumah_text", "")).strip():
            preserved_corrections += 1

    return {
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "source_file": str(source_path),
        "existing_csv_preserved": str(existing_csv),
        "target_sheet": source.TARGET_SHEET,
        "total_rows_from_excel": len(regenerated_rows),
        "rows_still_needing_manual_review": len(review_rows),
        "rows_with_house_status_filled": sum(1 for row in regenerated_rows if str(row.get("status_rumah_text", "")).strip()),
        "rows_with_house_status_pending": sum(1 for row in regenerated_rows if not str(row.get("status_rumah_text", "")).strip()),
        "status_counts": dict(status_counts),
        "cleaning_note_counts": dict(note_counts),
        "preserved_house_corrections": preserved_corrections,
        "assumption": "Excel asli diregenerate penuh, lalu nilai non-kosong dari CSV lama dipertahankan di atas row yang sama berdasarkan source_row_number. File lama tidak ditimpa.",
    }


def main() -> int:
    args = parse_args()
    source_path = Path(args.input).expanduser()
    existing_csv = Path(args.existing_csv)
    output_dir = Path(args.output_dir)

    if not source_path.exists():
        print(f"File tidak ditemukan: {source_path}", file=sys.stderr)
        return 1

    existing_rows = load_existing_rows(existing_csv)

    workbook_rows = source.parse_workbook_rows(source_path, source.TARGET_SHEET)
    if not workbook_rows:
        print("Workbook kosong atau sheet tidak memiliki data.", file=sys.stderr)
        return 1

    regenerated_rows: list[dict[str, object]] = []
    for workbook_row in workbook_rows[1:]:
        record = applicant_export.build_applicant_record(workbook_row)
        if record is None:
            continue
        key = str(record.get("source_row_number", "")).strip()
        regenerated_rows.append(merge_existing_values(record, existing_rows.get(key)))

    review_rows = build_review_rows(regenerated_rows)
    changes_preview = build_changes_preview(regenerated_rows, existing_rows)
    summary = build_summary(regenerated_rows, review_rows, existing_rows, source_path, existing_csv)

    merged_csv = output_dir / "kip_snbp_2023_student_applications_regenerated_merged.csv"
    review_csv = output_dir / "kip_snbp_2023_student_applications_regenerated_review.csv"
    changes_csv = output_dir / "kip_snbp_2023_student_applications_regenerated_changes.csv"
    summary_json = output_dir / "kip_snbp_2023_student_applications_regenerated_summary.json"

    write_csv(merged_csv, applicant_export.APPLICANT_HEADERS, regenerated_rows)
    write_csv(review_csv, applicant_export.REVIEW_HEADERS, review_rows)
    write_csv(
        changes_csv,
        ["source_row_number", "applicant_name", "change_type", "changed_fields", "manual_review_required", "cleaning_notes"],
        changes_preview,
    )
    summary_json.write_text(json.dumps(summary, indent=2, ensure_ascii=False), encoding="utf-8")

    print(json.dumps(summary, indent=2, ensure_ascii=False))
    print(f"merged_csv={merged_csv}")
    print(f"review_csv={review_csv}")
    print(f"changes_csv={changes_csv}")
    print(f"summary_json={summary_json}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
