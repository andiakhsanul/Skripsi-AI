#!/usr/bin/env python3

from __future__ import annotations

import argparse
import csv
import json
import sys
from collections import Counter
from pathlib import Path

import export_kip_snbp_2023_feature_csv as source

APPLICANT_HEADERS = [
    "schema_version",
    "submission_source",
    "student_user_id",
    "applicant_name",
    "applicant_email",
    "study_program",
    "faculty",
    "source_reference_number",
    "source_document_link",
    "source_sheet_name",
    "source_row_number",
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
    "manual_review_required",
    "manual_house_review",
    "cleaning_notes",
    "raw_status_ayah",
    "raw_status_ibu",
    "raw_status_rumah_condition",
    "raw_status_rumah_notes",
    "raw_penghasilan_ayah_cell",
    "raw_penghasilan_ibu_cell",
    "raw_social_docs",
    "label_detected_from_red_style",
]

REVIEW_HEADERS = [
    "source_row_number",
    "applicant_name",
    "study_program",
    "faculty",
    "status",
    "manual_house_review",
    "manual_review_required",
    "cleaning_notes",
    "status_rumah_text",
    "raw_status_rumah_condition",
    "raw_status_rumah_notes",
    "penghasilan_ayah_rupiah",
    "penghasilan_ibu_rupiah",
    "penghasilan_gabungan_rupiah",
    "jumlah_tanggungan_raw",
    "anak_ke_raw",
    "status_orangtua_text",
    "daya_listrik_text",
    "source_label_text",
    "source_document_link",
]


def house_status_text(notes: object, condition: object) -> tuple[str, bool]:
    encoded, _ = source.encode_house_status(notes, condition)
    mapping = {
        1: "Tidak memiliki rumah",
        2: "Sewa / Menumpang",
        3: "Milik sendiri",
    }
    if encoded is None:
        return "", True
    return mapping[encoded], False


def parent_status_text(father_status: object, mother_status: object) -> str:
    father = str(father_status or "").strip()
    mother = str(mother_status or "").strip()
    if not father and not mother:
        return ""
    return f"ayah={father}; ibu={mother}"


def decision_from_red(row: dict[str, object]) -> tuple[str, str]:
    if source.cell_is_red(row, "Y"):
        return "Rejected", "Rejected"
    return "Verified", "Verified"


def build_applicant_record(row: dict[str, object]) -> dict[str, object] | None:
    applicant_name = source.cell_value(row, "D")
    if not applicant_name:
        return None

    social_flags, social_warnings = source.extract_social_flags(source.cell_value(row, "T"))
    father_income = source.infer_income(source.cell_value(row, "P"), source.cell_value(row, "I"), source.cell_value(row, "K"))
    mother_income = source.infer_income(source.cell_value(row, "Q"), source.cell_value(row, "J"), source.cell_value(row, "L"))
    total_income = None if father_income is None and mother_income is None else (father_income or 0) + (mother_income or 0)

    dependents_raw = source.parse_dependents(source.cell_value(row, "N"))
    child_order_raw = source.parse_child_order(source.cell_value(row, "M"))
    house_text, house_manual = house_status_text(source.cell_value(row, "S"), source.cell_value(row, "R"))
    status_text = parent_status_text(source.cell_value(row, "K"), source.cell_value(row, "L"))
    status, admin_decision = decision_from_red(row)

    cleaning_notes: list[str] = []

    if social_warnings:
        cleaning_notes.extend(social_warnings)
    if father_income is None:
        cleaning_notes.append("penghasilan_ayah_perlu_cek_manual")
    if mother_income is None:
        cleaning_notes.append("penghasilan_ibu_perlu_cek_manual")
    if dependents_raw is None:
        cleaning_notes.append("jumlah_tanggungan_perlu_cek_manual")
    if child_order_raw is None:
        cleaning_notes.append("anak_ke_perlu_cek_manual")
    if not status_text:
        cleaning_notes.append("status_orangtua_perlu_cek_manual")
    if house_manual:
        cleaning_notes.append("status_rumah_perlu_isi_manual")
    if not source.cell_value(row, "O"):
        cleaning_notes.append("daya_listrik_perlu_cek_manual")

    return {
        "schema_version": 1,
        "submission_source": "offline_admin_import",
        "student_user_id": "",
        "applicant_name": applicant_name,
        "applicant_email": "",
        "study_program": source.cell_value(row, "E"),
        "faculty": source.cell_value(row, "F"),
        "source_reference_number": source.cell_value(row, "C"),
        "source_document_link": source.cell_value(row, "G"),
        "source_sheet_name": source.TARGET_SHEET,
        "source_row_number": row["row_number"],
        "source_label_text": source.cell_value(row, "Y"),
        "kip": social_flags["kip"],
        "pkh": social_flags["pkh"],
        "kks": social_flags["kks"],
        "dtks": social_flags["dtks"],
        "sktm": social_flags["sktm"],
        "penghasilan_ayah_rupiah": father_income if father_income is not None else "",
        "penghasilan_ibu_rupiah": mother_income if mother_income is not None else "",
        "penghasilan_gabungan_rupiah": total_income if total_income is not None else "",
        "jumlah_tanggungan_raw": dependents_raw if dependents_raw is not None else "",
        "anak_ke_raw": child_order_raw if child_order_raw is not None else "",
        "status_orangtua_text": status_text,
        "status_rumah_text": house_text,
        "daya_listrik_text": source.cell_value(row, "O"),
        "status": status,
        "admin_decision": admin_decision,
        "admin_decision_note": "Dibersihkan dari Verifikasi KIP SNBP 2023.xlsx sebelum import ke student_applications",
        "manual_review_required": 1 if cleaning_notes else 0,
        "manual_house_review": 1 if house_manual else 0,
        "cleaning_notes": " | ".join(cleaning_notes),
        "raw_status_ayah": source.cell_value(row, "K"),
        "raw_status_ibu": source.cell_value(row, "L"),
        "raw_status_rumah_condition": source.cell_value(row, "R"),
        "raw_status_rumah_notes": source.cell_value(row, "S"),
        "raw_penghasilan_ayah_cell": source.cell_value(row, "P"),
        "raw_penghasilan_ibu_cell": source.cell_value(row, "Q"),
        "raw_social_docs": source.cell_value(row, "T"),
        "label_detected_from_red_style": 1 if source.cell_is_red(row, "Y") else 0,
    }


def write_csv(path: Path, headers: list[str], rows: list[dict[str, object]]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", newline="", encoding="utf-8") as handle:
        writer = csv.DictWriter(handle, fieldnames=headers)
        writer.writeheader()
        for row in rows:
            writer.writerow({key: row.get(key, "") for key in headers})


def build_summary(source_path: Path, rows: list[dict[str, object]]) -> dict[str, object]:
    status_counts = Counter(str(row["status"]) for row in rows)
    manual_review_rows = sum(int(row["manual_review_required"]) for row in rows)
    manual_house_rows = sum(int(row["manual_house_review"]) for row in rows)
    red_rows = sum(int(row["label_detected_from_red_style"]) for row in rows)

    note_counter = Counter()
    for row in rows:
        for part in str(row["cleaning_notes"]).split("|"):
            item = part.strip()
            if item:
                note_counter[item] += 1

    return {
        "source_file": str(source_path),
        "target_sheet": source.TARGET_SHEET,
        "total_rows": len(rows),
        "status_counts": dict(status_counts),
        "manual_review_rows": manual_review_rows,
        "manual_house_rows": manual_house_rows,
        "indikasi_red_rows": red_rows,
        "cleaning_note_counts": dict(note_counter),
        "assumption": "Baris merah pada kolom kesimpulan diperlakukan sebagai Indikasi/Rejected. Baris tidak merah diperlakukan sebagai Layak/Verified. Status rumah yang ambigu dikosongkan agar dikoreksi manual.",
    }


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Export raw applicant CSV untuk student_applications dari Verifikasi KIP SNBP 2023.")
    parser.add_argument(
        "--input",
        default="/Users/andiakhsanul/Downloads/Verifikasi KIP SNBP 2023.xlsx",
        help="Path ke file Excel mentah.",
    )
    parser.add_argument(
        "--output-dir",
        default="infra/data/processed",
        help="Direktori output CSV/JSON hasil pembersihan applicant.",
    )
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    source_path = Path(args.input).expanduser()
    output_dir = Path(args.output_dir)

    if not source_path.exists():
        print(f"File tidak ditemukan: {source_path}", file=sys.stderr)
        return 1

    workbook_rows = source.parse_workbook_rows(source_path, source.TARGET_SHEET)
    if not workbook_rows:
        print("Workbook kosong atau sheet tidak memiliki data.", file=sys.stderr)
        return 1

    applicant_rows: list[dict[str, object]] = []
    review_rows: list[dict[str, object]] = []

    for row in workbook_rows[1:]:
        record = build_applicant_record(row)
        if record is None:
            continue
        applicant_rows.append(record)
        if record["manual_review_required"]:
            review_rows.append(record)

    applicant_path = output_dir / "kip_snbp_2023_student_applications_raw.csv"
    review_path = output_dir / "kip_snbp_2023_student_applications_manual_review.csv"
    summary_path = output_dir / "kip_snbp_2023_student_applications_summary.json"

    write_csv(applicant_path, APPLICANT_HEADERS, applicant_rows)
    write_csv(review_path, REVIEW_HEADERS, review_rows)

    summary = build_summary(source_path, applicant_rows)
    summary_path.write_text(json.dumps(summary, indent=2, ensure_ascii=False), encoding="utf-8")

    print(json.dumps(summary, indent=2, ensure_ascii=False))
    print(f"applicant_csv={applicant_path}")
    print(f"manual_review_csv={review_path}")
    print(f"summary_json={summary_path}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
