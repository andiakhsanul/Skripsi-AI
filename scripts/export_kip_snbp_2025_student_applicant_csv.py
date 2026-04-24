#!/usr/bin/env python3

from __future__ import annotations

import argparse
import json
import re
import sys
from collections import Counter
from datetime import datetime, timezone
from decimal import Decimal, InvalidOperation
from pathlib import Path

import export_kip_snbp_2023_feature_csv as workbook
import export_kip_snbp_2024_student_applicant_csv as helper_2024
from export_kip_snbp_2023_student_applicant_csv import APPLICANT_HEADERS

TARGET_SHEET = "Verif Eligible Kem KIP SNBP 202"
SOURCE_SHEET_NAME = "Verif Eligible Kem KIP SNBP 2025"

REVIEW_HEADERS = [
    "source_row_number",
    "applicant_name",
    "study_program",
    "faculty",
    "status",
    "source_label_text",
    "manual_review_required",
    "manual_house_review",
    "cleaning_notes",
    "raw_card_text",
    "raw_sktm_text",
    "raw_desil_text",
    "raw_father_job",
    "raw_mother_job",
    "raw_description",
    "raw_indication_note",
    "penghasilan_ayah_rupiah",
    "penghasilan_ibu_rupiah",
    "penghasilan_gabungan_rupiah",
    "jumlah_tanggungan_raw",
    "anak_ke_raw",
    "status_orangtua_text",
    "status_rumah_text",
    "daya_listrik_text",
    "source_document_link",
]


def normalize_text(value: object) -> str:
    return helper_2024.normalize_text(value)


def display_text(value: object) -> str:
    return helper_2024.display_text(value)


def contains_any(text: str, fragments: list[str]) -> bool:
    return helper_2024.contains_any(text, fragments)


def parse_currency_fragment(text: str, assume_million_when_small: bool = False) -> int | None:
    return helper_2024.parse_currency_fragment(text, assume_million_when_small)


def parse_support_flags(card_text: str, sktm_text: str) -> tuple[dict[str, int], list[str]]:
    return helper_2024.parse_support_flags(card_text, sktm_text)


def write_csv(path: Path, headers: list[str], rows: list[dict[str, object]]) -> None:
    helper_2024.write_csv(path, headers, rows)


def normalize_final_label(value: object) -> str:
    normalized = normalize_text(value)

    return normalized if normalized in {"layak", "indikasi"} else ""


def resolve_decision(label: str) -> tuple[str, str]:
    normalized = normalize_final_label(label)

    if normalized == "layak":
        return "Verified", "Verified"
    if normalized == "indikasi":
        return "Rejected", "Rejected"

    return "Submitted", ""


def selection_score(row: dict[str, object]) -> int:
    score = 0

    if display_text(workbook.cell_value(row, "V")):
        score += 1
    if display_text(workbook.cell_value(row, "O")) or display_text(workbook.cell_value(row, "P")):
        score += 1
    if display_text(workbook.cell_value(row, "Q")):
        score += 1
    if display_text(workbook.cell_value(row, "R")) and display_text(workbook.cell_value(row, "S")):
        score += 1
    if display_text(workbook.cell_value(row, "T")) and display_text(workbook.cell_value(row, "U")):
        score += 1
    if display_text(workbook.cell_value(row, "X")):
        score += 1

    return score


def normalize_reference_number(value: object) -> str:
    text = display_text(value)
    if not text:
        return ""

    text = text.replace("‘", "").replace("’", "").replace("'", "")
    compact_digits = re.sub(r"[^0-9]", "", text)

    if re.fullmatch(r"[0-9]+(?:\.[0-9]+)?(?:e[+-]?[0-9]+)?", text.lower()):
        try:
            return str(Decimal(text).quantize(Decimal("1")))
        except (InvalidOperation, ValueError):
            return compact_digits or text

    return compact_digits or text


def parse_income_cell(value: object) -> int | None:
    text = display_text(value)
    if not text or text == "-":
        return None

    cleaned = text.replace("‘", "").replace("’", "").replace("'", "")

    normalized = normalize_text(cleaned)
    if contains_any(normalized, ["tidak berpenghasilan", "tak berpenghasilan", "tidak bekerja"]):
        return 0

    if re.fullmatch(r"[+-]?\d+(?:\.\d+)?(?:e[+-]?\d+)?", cleaned.lower()):
        try:
            return int(Decimal(cleaned))
        except (InvalidOperation, ValueError):
            return None

    return parse_currency_fragment(cleaned)


def parse_dependents_explicit(description: str) -> int | None:
    text = normalize_text(description)
    if not text:
        return None

    patterns = [
        r"jumlah tanggungan\s+(\d+)",
        r"tanggungan\s+(\d+)\s*(?:orang|anak|anggota keluarga)?",
        r"menanggung\s+(\d+)\s*(?:orang|anak)?",
        r"(\d+)\s+anggota keluarga",
        r"(\d+)\s+orang(?:\s+keluarga)?",
    ]

    for pattern in patterns:
        match = re.search(pattern, text)
        if match:
            return int(match.group(1))

    return None


def parse_child_order_explicit(description: str) -> int | None:
    text = normalize_text(description)
    if not text:
        return None

    match = re.search(r"anak\s+ke[- ]?(\d+)", text)
    if match:
        return int(match.group(1))

    replacements = {
        "anak pertama": 1,
        "anak kedua": 2,
        "anak ketiga": 3,
        "anak keempat": 4,
        "anak kelima": 5,
    }

    for token, value in replacements.items():
        if token in text:
            return value

    return None


def parse_parent_status_conservative(father_job: str, mother_job: str, description: str) -> str:
    father_job_text = normalize_text(father_job)
    mother_job_text = normalize_text(mother_job)
    text = normalize_text(f"{father_job} {mother_job} {description}")

    if not text:
        return ""

    father_status: str | None = None
    mother_status: str | None = None
    family_note = ""

    if contains_any(text, ["yatim piatu"]):
        father_status = "meninggal"
        mother_status = "meninggal"

    if father_status is None and contains_any(text, [
        "ayah meninggal",
        "ayah wafat",
        "ayah sudah wafat",
        "ayahnya meninggal",
        "ayahnya wafat",
        "yatim",
    ]):
        father_status = "meninggal"

    if mother_status is None and contains_any(text, [
        "ibu meninggal",
        "ibu wafat",
        "ibu sudah wafat",
        "ibunya meninggal",
        "ibunya wafat",
        "piatu",
    ]):
        mother_status = "meninggal"

    if father_status is None and father_job_text and father_job_text not in {"-", "tidak diketahui"}:
        father_status = "hidup"

    if mother_status is None and mother_job_text and mother_job_text not in {"-", "tidak diketahui"}:
        mother_status = "hidup"

    if contains_any(text, ["cerai", "pisah", "wali", "tiri", "orang tua tidak lengkap"]):
        family_note = "keluarga tidak lengkap/terpisah"

    if father_status is None or mother_status is None:
        return ""

    if family_note:
        return f"ayah={father_status}; ibu={mother_status}; catatan={family_note}"

    return f"ayah={father_status}; ibu={mother_status}"


def parse_house_status_explicit(description: str) -> str:
    text = normalize_text(description)
    if not text:
        return ""

    if contains_any(text, [
        "tidak punya rumah",
        "tidak memiliki rumah",
        "tidak mempunyai rumah",
        "belum punya rumah",
        "belum memiliki rumah",
    ]):
        return "Tidak memiliki"

    if contains_any(text, ["menumpang", "numpang"]):
        return "Menumpang"

    if contains_any(text, ["sewa", "kontrak", "rumah kontrak", "rumah sewa", "sewa bulanan"]):
        return "Sewa"

    if re.search(r"(?<!tidak )(?:milik sendiri|rumah sendiri|punya rumah sendiri)", text):
        return "Milik sendiri"

    return ""


def parse_electricity_explicit(description: str) -> str:
    text = normalize_text(description)
    if not text:
        return ""

    match = re.search(r"(?:listrik|daya listrik)\D{0,12}(\d{3,4})", text)
    if match:
        return match.group(1)

    match = re.search(r"(\d{3,4})\s*(?:va|w|watt)\b", text)
    if match:
        return match.group(1)

    return ""


def build_cleaning_notes(record: dict[str, object], support_notes: list[str]) -> list[str]:
    notes = list(support_notes)

    if record["penghasilan_ayah_rupiah"] == "":
        notes.append("penghasilan_ayah_perlu_cek_manual")
    if record["penghasilan_ibu_rupiah"] == "":
        notes.append("penghasilan_ibu_perlu_cek_manual")
    if record["penghasilan_gabungan_rupiah"] == "":
        notes.append("penghasilan_gabungan_perlu_cek_manual")
    if record["jumlah_tanggungan_raw"] == "":
        notes.append("jumlah_tanggungan_perlu_cek_manual")
    if record["anak_ke_raw"] == "":
        notes.append("anak_ke_perlu_cek_manual")
    if record["status_orangtua_text"] == "":
        notes.append("status_orangtua_perlu_cek_manual")
    if record["status_rumah_text"] == "":
        notes.append("status_rumah_perlu_isi_manual")
    if record["daya_listrik_text"] == "":
        notes.append("daya_listrik_perlu_cek_manual")

    deduped: list[str] = []
    seen: set[str] = set()
    for note in notes:
        if note in seen:
            continue
        seen.add(note)
        deduped.append(note)

    return deduped


def build_applicant_record(row: dict[str, object]) -> dict[str, object]:
    card_text = display_text(workbook.cell_value(row, "O"))
    sktm_text = display_text(workbook.cell_value(row, "P"))
    description = display_text(workbook.cell_value(row, "X"))
    father_job = display_text(workbook.cell_value(row, "R"))
    mother_job = display_text(workbook.cell_value(row, "S"))
    indication_note = display_text(workbook.cell_value(row, "Z"))
    status, admin_decision = resolve_decision(workbook.cell_value(row, "Y"))
    flags, support_notes = parse_support_flags(card_text, sktm_text)

    father_income = parse_income_cell(workbook.cell_value(row, "T"))
    mother_income = parse_income_cell(workbook.cell_value(row, "U"))
    combined_income = ""
    if father_income is not None or mother_income is not None:
        combined_income = (father_income or 0) + (mother_income or 0)

    record: dict[str, object] = {
        "schema_version": 1,
        "submission_source": "offline_admin_import",
        "student_user_id": "",
        "applicant_name": display_text(workbook.cell_value(row, "D")),
        "applicant_email": "",
        "study_program": display_text(workbook.cell_value(row, "H")),
        "faculty": display_text(workbook.cell_value(row, "I")),
        "source_reference_number": normalize_reference_number(workbook.cell_value(row, "B")),
        "source_document_link": display_text(workbook.cell_value(row, "V")),
        "source_sheet_name": SOURCE_SHEET_NAME,
        "source_row_number": row["row_number"],
        "source_label_text": display_text(workbook.cell_value(row, "Y")),
        "kip": flags["kip"],
        "pkh": flags["pkh"],
        "kks": flags["kks"],
        "dtks": flags["dtks"],
        "sktm": flags["sktm"],
        "penghasilan_ayah_rupiah": father_income if father_income is not None else "",
        "penghasilan_ibu_rupiah": mother_income if mother_income is not None else "",
        "penghasilan_gabungan_rupiah": combined_income,
        "jumlah_tanggungan_raw": parse_dependents_explicit(description) or "",
        "anak_ke_raw": parse_child_order_explicit(description) or "",
        "status_orangtua_text": parse_parent_status_conservative(father_job, mother_job, description),
        "status_rumah_text": parse_house_status_explicit(description),
        "daya_listrik_text": parse_electricity_explicit(description),
        "status": status,
        "admin_decision": admin_decision,
        "admin_decision_note": "Dibersihkan dari Verif ELIGIBLE Kementerian KIP SNBP 2025.xlsx sebelum import ke student_applications",
        "manual_review_required": 0,
        "manual_house_review": 0,
        "cleaning_notes": "",
        "raw_status_ayah": father_job,
        "raw_status_ibu": mother_job,
        "raw_status_rumah_condition": "",
        "raw_status_rumah_notes": description,
        "raw_penghasilan_ayah_cell": display_text(workbook.cell_value(row, "T")),
        "raw_penghasilan_ibu_cell": display_text(workbook.cell_value(row, "U")),
        "raw_social_docs": card_text,
        "label_detected_from_red_style": 0,
        "_raw_card_text": card_text,
        "_raw_sktm_text": sktm_text,
        "_raw_desil_text": display_text(workbook.cell_value(row, "Q")),
        "_raw_description": description,
        "_raw_indication_note": indication_note,
        "_selection_score": selection_score(row),
    }

    cleaning_notes = build_cleaning_notes(record, support_notes)
    record["cleaning_notes"] = " | ".join(cleaning_notes)
    record["manual_review_required"] = 1 if cleaning_notes else 0
    record["manual_house_review"] = 1 if record["status_rumah_text"] == "" else 0

    return record


def select_rows(workbook_rows: list[dict[str, object]]) -> list[dict[str, object]]:
    candidates: list[dict[str, object]] = []

    for row in workbook_rows[2:]:
        applicant_name = display_text(workbook.cell_value(row, "D"))
        if not applicant_name:
            continue

        final_label = normalize_final_label(workbook.cell_value(row, "Y"))
        if not final_label:
            continue

        candidates.append(
            {
                "row_number": row["row_number"],
                "final_label": final_label,
                "selection_score": selection_score(row),
                "row": row,
            }
        )

    indikasi_rows = sorted(
        [candidate for candidate in candidates if candidate["final_label"] == "indikasi"],
        key=lambda candidate: int(candidate["row_number"]),
    )
    layak_rows = sorted(
        [candidate for candidate in candidates if candidate["final_label"] == "layak"],
        key=lambda candidate: (
            -int(candidate["selection_score"]),
            int(candidate["row_number"]),
        ),
    )

    selected = indikasi_rows + layak_rows[:31]
    selected_rows = [candidate["row"] for candidate in selected]
    selected_rows.sort(key=lambda row: int(row["row_number"]))

    if len(selected_rows) != 47:
        raise RuntimeError(f"Seleksi 47 data gagal. Total terpilih: {len(selected_rows)}")

    row_numbers = [int(row["row_number"]) for row in selected_rows]
    if len(set(row_numbers)) != len(row_numbers):
        raise RuntimeError("Seleksi menghasilkan source_row_number duplikat.")

    return selected_rows


def build_review_rows(rows: list[dict[str, object]]) -> list[dict[str, object]]:
    review_rows: list[dict[str, object]] = []

    for row in rows:
        if str(row.get("manual_review_required", "")).strip() != "1":
            continue

        review_rows.append(
            {
                "source_row_number": row.get("source_row_number", ""),
                "applicant_name": row.get("applicant_name", ""),
                "study_program": row.get("study_program", ""),
                "faculty": row.get("faculty", ""),
                "status": row.get("status", ""),
                "source_label_text": row.get("source_label_text", ""),
                "manual_review_required": row.get("manual_review_required", ""),
                "manual_house_review": row.get("manual_house_review", ""),
                "cleaning_notes": row.get("cleaning_notes", ""),
                "raw_card_text": row.get("_raw_card_text", ""),
                "raw_sktm_text": row.get("_raw_sktm_text", ""),
                "raw_desil_text": row.get("_raw_desil_text", ""),
                "raw_father_job": row.get("raw_status_ayah", ""),
                "raw_mother_job": row.get("raw_status_ibu", ""),
                "raw_description": row.get("_raw_description", ""),
                "raw_indication_note": row.get("_raw_indication_note", ""),
                "penghasilan_ayah_rupiah": row.get("penghasilan_ayah_rupiah", ""),
                "penghasilan_ibu_rupiah": row.get("penghasilan_ibu_rupiah", ""),
                "penghasilan_gabungan_rupiah": row.get("penghasilan_gabungan_rupiah", ""),
                "jumlah_tanggungan_raw": row.get("jumlah_tanggungan_raw", ""),
                "anak_ke_raw": row.get("anak_ke_raw", ""),
                "status_orangtua_text": row.get("status_orangtua_text", ""),
                "status_rumah_text": row.get("status_rumah_text", ""),
                "daya_listrik_text": row.get("daya_listrik_text", ""),
                "source_document_link": row.get("source_document_link", ""),
            }
        )

    return review_rows


def build_summary(source_path: Path, rows: list[dict[str, object]], review_rows: list[dict[str, object]]) -> dict[str, object]:
    note_counts = Counter()
    for row in rows:
        for part in str(row.get("cleaning_notes", "")).split("|"):
            item = part.strip()
            if item:
                note_counts[item] += 1

    return {
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "source_file": str(source_path),
        "target_sheet": TARGET_SHEET,
        "source_sheet_name": SOURCE_SHEET_NAME,
        "selected_rows": len(rows),
        "selected_row_numbers": [int(row["source_row_number"]) for row in rows],
        "status_counts": dict(Counter(str(row["status"]) for row in rows)),
        "source_label_counts": dict(Counter(str(row["source_label_text"]) for row in rows)),
        "selection_score_counts": dict(Counter(int(row["_selection_score"]) for row in rows)),
        "support_counts": {
            key: sum(int(row[key]) for row in rows)
            for key in ["kip", "pkh", "kks", "dtks", "sktm"]
        },
        "manual_review_rows": len(review_rows),
        "manual_house_rows": sum(int(row["manual_house_review"]) for row in rows),
        "cleaning_note_counts": dict(note_counts),
        "selection_rule": "Semua label indikasi eksplisit dipilih lebih dulu, lalu 31 label layak terbaik dipilih berdasarkan kelengkapan sumber V, O/P, Q, R/S, T/U, X dengan tie-break source_row_number terkecil.",
        "assumption": "Jumlah tanggungan, anak ke, status rumah, dan daya listrik hanya diisi bila disebut eksplisit di deskripsi. Sisanya dibiarkan kosong agar dilengkapi manual setelah import.",
    }


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Export 47 applicant CSV untuk batch KIP SNBP 2025.")
    parser.add_argument(
        "--input",
        default="/Users/andiakhsanul/Downloads/Verif ELIGIBLE Kementerian KIP SNBP 2025.xlsx",
        help="Path ke file Excel mentah.",
    )
    parser.add_argument(
        "--output-dir",
        default="infra/data/processed",
        help="Direktori output CSV/JSON hasil seleksi.",
    )
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    source_path = Path(args.input).expanduser()
    output_dir = Path(args.output_dir)

    if not source_path.exists():
        print(f"File tidak ditemukan: {source_path}", file=sys.stderr)
        return 1

    workbook_rows = workbook.parse_workbook_rows(source_path, TARGET_SHEET)
    if not workbook_rows:
        print("Workbook kosong atau sheet tidak memiliki data.", file=sys.stderr)
        return 1

    selected_workbook_rows = select_rows(workbook_rows)
    applicant_rows = [build_applicant_record(row) for row in selected_workbook_rows]
    review_rows = build_review_rows(applicant_rows)
    summary = build_summary(source_path, applicant_rows, review_rows)

    applicant_path = output_dir / "kip_snbp_2025_student_applications_selected_47.csv"
    review_path = output_dir / "kip_snbp_2025_student_applications_selected_47_review.csv"
    summary_path = output_dir / "kip_snbp_2025_student_applications_selected_47_summary.json"

    write_csv(applicant_path, APPLICANT_HEADERS, applicant_rows)
    write_csv(review_path, REVIEW_HEADERS, review_rows)
    summary_path.write_text(json.dumps(summary, indent=2, ensure_ascii=False), encoding="utf-8")

    print(json.dumps(summary, indent=2, ensure_ascii=False))
    print(f"applicant_csv={applicant_path}")
    print(f"review_csv={review_path}")
    print(f"summary_json={summary_path}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
