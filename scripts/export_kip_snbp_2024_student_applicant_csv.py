#!/usr/bin/env python3

from __future__ import annotations

import argparse
import csv
import json
import re
import sys
from collections import Counter
from datetime import datetime, timezone
from pathlib import Path

import export_kip_snbp_2023_feature_csv as workbook
from export_kip_snbp_2023_student_applicant_csv import APPLICANT_HEADERS

TARGET_SHEET = "VERIFIKASI"

CARD_SPLIT_HEADERS = [
    "source_row_number",
    "applicant_name",
    "source_reference_number",
    "raw_card_text",
    "raw_sktm_text",
    "kip",
    "pkh",
    "kks",
    "dtks",
    "sktm",
    "unsupported_card_notes",
]

REVIEW_HEADERS = [
    "source_row_number",
    "applicant_name",
    "study_program",
    "faculty",
    "status",
    "source_label_text",
    "manual_review_required",
    "cleaning_notes",
    "raw_card_text",
    "raw_sktm_text",
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
    text = "" if value is None else str(value)
    text = text.replace("\n", " ")
    text = re.sub(r"\s+", " ", text)
    return text.strip().lower()


def display_text(value: object) -> str:
    return re.sub(r"\s+", " ", str(value or "").replace("\n", " ")).strip()


def contains_any(text: str, fragments: list[str]) -> bool:
    return any(fragment in text for fragment in fragments)


def parse_currency_fragment(text: str, assume_million_when_small: bool = False) -> int | None:
    normalized = normalize_text(text)
    if not normalized:
        return None

    normalized = normalized.replace("rp", "").replace(" ", "")
    normalized = normalized.replace("perbulan", "/bulan").replace("sebulan", "/bulan")

    if "juta" in normalized or "jt" in normalized:
        numbers = [float(token.replace(",", ".")) for token in re.findall(r"\d+[.,]?\d*", normalized)]
        return int(numbers[0] * 1_000_000) if numbers else None

    if "ribu" in normalized or re.search(r"\d+\s*rb", normalized):
        numbers = [float(token.replace(",", ".")) for token in re.findall(r"\d+[.,]?\d*", normalized)]
        return int(numbers[0] * 1_000) if numbers else None

    digits = re.sub(r"[^0-9]", "", normalized)
    if not digits:
        return None

    value = int(digits)
    # Angka kecil seperti "4" dalam konteks gaji biasanya berarti 4 juta.
    if value < 1000 and (assume_million_when_small or contains_any(normalized, ["gaji", "penghasilan", "pendapatan", "/bulan", "bln"])):
        return value * 1_000_000

    return value


def parse_money_after_context(text: str) -> int | None:
    normalized = normalize_text(text)
    context_pattern = r"(?:gaji|penghasilan|pendapatan|upah|honor)"
    for match in re.finditer(context_pattern, normalized):
        tail = normalized[match.end(): match.end() + 80]
        number_match = re.search(r"\d+(?:[.,]\d+)*(?:\s*[-–]\s*\d+(?:[.,]\d+)*)?\s*(?:jt|juta|rb|ribu|rupiah|000)?(?:/bulan|/bln| per bulan| bln| bulan)?", tail)
        if not number_match:
            continue

        before_number = tail[: number_match.start()]
        if contains_any(before_number, ["tanggungan", "anak", "orang", "org", "keluarga"]):
            continue

        value = parse_currency_fragment(number_match.group(0), assume_million_when_small=True)
        if value is not None:
            return value

    return None


def parse_income_from_description(description: str, parent: str, other_parent_is_inactive: bool) -> int | None:
    normalized = normalize_text(description)
    if not normalized:
        return None

    parent_tokens = {
        "ayah": ["ayah", "bapak", "wali"],
        "ibu": ["ibu"],
    }

    for segment in re.split(r"[.;]", normalized):
        if not contains_any(segment, parent_tokens[parent]):
            continue
        value = parse_money_after_context(segment)
        if value is not None:
            return value

    if other_parent_is_inactive:
        return parse_money_after_context(normalized)

    return None


def inactive_or_deceased(job: str, description: str, parent: str) -> bool:
    text = normalize_text(f"{job} {description}")
    parent_dead_tokens = {
        "ayah": ["ayah meninggal", "ayah wafat", "ayah sudah wafat", "ayahnya meninggal", "ayahnya wafat", "yatim"],
        "ibu": ["ibu meninggal", "ibu wafat", "ibunya meninggal", "ibunya wafat", "piatu"],
    }
    if contains_any(text, parent_dead_tokens[parent]):
        return True

    return contains_any(
        normalize_text(job),
        [
            "ibu rumah tangga",
            "rumah tangga",
            "irt",
            "tidak bekerja",
            "tidak kerja",
            "belum bekerja",
            "pengangguran",
            "tidak ada",
        ],
    )


def infer_income(job: str, description: str, parent: str, other_parent_is_inactive: bool) -> int | None:
    if inactive_or_deceased(job, description, parent):
        return 0

    return parse_income_from_description(description, parent, other_parent_is_inactive)


def parse_support_flags(card_text: str, sktm_text: str) -> tuple[dict[str, int], list[str]]:
    raw_card = normalize_text(card_text)
    raw_sktm = normalize_text(sktm_text)
    notes: list[str] = []

    flags = {
        "kip": 1 if "kip" in raw_card else 0,
        "pkh": 1 if "pkh" in raw_card else 0,
        "kks": 1 if "kks" in raw_card else 0,
        "dtks": 1 if "dtks" in raw_card or "dtks" in raw_sktm else 0,
        "sktm": 0,
    }

    if "tidak ada" in raw_sktm:
        flags["sktm"] = 0
    elif "ada" in raw_sktm:
        flags["sktm"] = 1
    elif not raw_sktm:
        notes.append("sktm_perlu_cek_manual")

    if "kis" in raw_card:
        notes.append("kis_terdeteksi_tetapi_tidak_termasuk_13_fitur")
    if "kps" in raw_card:
        notes.append("kps_terdeteksi_tetapi_tidak_termasuk_13_fitur")
    if raw_card and not any(flags[key] for key in ["kip", "pkh", "kks", "dtks"]) and "kis" not in raw_card and "kps" not in raw_card:
        notes.append("kartu_miskin_tidak_bisa_dipetakan")

    return flags, notes


def parse_dependents(description: str) -> int | None:
    text = normalize_text(description)
    patterns = [
        r"tanggungan\s+(\d+)",
        r"menanggung\s+(\d+)",
        r"tanggungan\s+keluarga\s+(\d+)",
        r"anaknya\s+(\d+)",
    ]
    for pattern in patterns:
        match = re.search(pattern, text)
        if match:
            return int(match.group(1))

    family_members = re.findall(r"(\d+)\s+(?:anak|istri|suami|mertua|adik|kakak|orang)", text)
    if family_members and contains_any(text, ["menanggung", "tanggungan"]):
        return sum(int(value) for value in family_members)

    return None


def parse_child_order(description: str) -> int | None:
    text = normalize_text(description)
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


def parse_parent_status(father_job: str, mother_job: str, description: str) -> str:
    text = normalize_text(f"{father_job} {mother_job} {description}")
    father = "hidup"
    mother = "hidup"

    if contains_any(text, ["yatim piatu"]):
        father = "meninggal"
        mother = "meninggal"
    else:
        if contains_any(text, ["ayah meninggal", "ayah wafat", "ayah sudah wafat", "ayahnya meninggal", "ayahnya wafat", "yatim"]):
            father = "meninggal"
        if contains_any(text, ["ibu meninggal", "ibu wafat", "ibunya meninggal", "ibunya wafat", "piatu"]):
            mother = "meninggal"

    if contains_any(text, ["cerai", "pisah", "minggat"]):
        return f"ayah={father}; ibu={mother}; catatan=keluarga tidak lengkap/terpisah"

    return f"ayah={father}; ibu={mother}"


def parse_house_status(description: str) -> str:
    text = normalize_text(description)
    if contains_any(text, ["tidak punya rumah", "tidak memiliki rumah"]):
        return "Tidak memiliki"
    if contains_any(text, ["sewa", "kontrak"]):
        return "Sewa"
    if contains_any(text, ["menumpang", "numpang"]):
        return "Menumpang"
    if contains_any(text, ["milik sendiri", "rumah sendiri", "punya rumah sendiri"]):
        return "Milik sendiri"
    return ""


def parse_electricity(description: str) -> str:
    text = normalize_text(description)
    match = re.search(r"(\d{3,4})\s*(?:watt|w|va)", text)
    return match.group(1) if match else ""


def resolve_decision(label: str) -> tuple[str, str]:
    normalized = normalize_text(label)
    if normalized == "layak":
        return "Verified", "Verified"
    if contains_any(normalized, ["indikasi", "tidak layak", "survey", "wawancara"]):
        return "Rejected", "Rejected"
    return "Submitted", ""


def build_applicant_record(row: dict[str, object]) -> tuple[dict[str, object] | None, dict[str, object] | None]:
    applicant_name = workbook.cell_value(row, "D")
    if not applicant_name:
        return None, None

    description = workbook.cell_value(row, "S")
    indication_note = workbook.cell_value(row, "U")
    father_job = workbook.cell_value(row, "Q")
    mother_job = workbook.cell_value(row, "R")
    card_text = workbook.cell_value(row, "O")
    sktm_text = workbook.cell_value(row, "P")

    flags, support_notes = parse_support_flags(card_text, sktm_text)
    father_inactive = inactive_or_deceased(father_job, description, "ayah")
    mother_inactive = inactive_or_deceased(mother_job, description, "ibu")
    father_income = infer_income(father_job, description, "ayah", mother_inactive)
    mother_income = infer_income(mother_job, description, "ibu", father_inactive)
    combined_income = None if father_income is None and mother_income is None else (father_income or 0) + (mother_income or 0)
    dependents = parse_dependents(description)
    child_order = parse_child_order(description)
    parent_status = parse_parent_status(father_job, mother_job, description)
    house_status = parse_house_status(description)
    electricity = parse_electricity(description)
    status, admin_decision = resolve_decision(workbook.cell_value(row, "T"))

    notes = support_notes.copy()
    if father_income is None:
        notes.append("penghasilan_ayah_perlu_cek_manual")
    if mother_income is None:
        notes.append("penghasilan_ibu_perlu_cek_manual")
    if combined_income is None:
        notes.append("penghasilan_gabungan_perlu_cek_manual")
    if dependents is None:
        notes.append("jumlah_tanggungan_perlu_cek_manual")
    if child_order is None:
        notes.append("anak_ke_perlu_cek_manual")
    if not house_status:
        notes.append("status_rumah_perlu_isi_manual")
    if not electricity:
        notes.append("daya_listrik_perlu_cek_manual")
    if status == "Submitted":
        notes.append("hasil_verifikasi_belum_final")
    if indication_note:
        notes.append("ada_catatan_indikasi")

    record = {
        "schema_version": 1,
        "submission_source": "offline_admin_import",
        "student_user_id": "",
        "applicant_name": applicant_name,
        "applicant_email": "",
        "study_program": workbook.cell_value(row, "G"),
        "faculty": workbook.cell_value(row, "H"),
        "source_reference_number": workbook.cell_value(row, "B"),
        "source_document_link": workbook.cell_value(row, "N"),
        "source_sheet_name": TARGET_SHEET,
        "source_row_number": row["row_number"],
        "source_label_text": workbook.cell_value(row, "T"),
        "kip": flags["kip"],
        "pkh": flags["pkh"],
        "kks": flags["kks"],
        "dtks": flags["dtks"],
        "sktm": flags["sktm"],
        "penghasilan_ayah_rupiah": father_income if father_income is not None else "",
        "penghasilan_ibu_rupiah": mother_income if mother_income is not None else "",
        "penghasilan_gabungan_rupiah": combined_income if combined_income is not None else "",
        "jumlah_tanggungan_raw": dependents if dependents is not None else "",
        "anak_ke_raw": child_order if child_order is not None else "",
        "status_orangtua_text": parent_status,
        "status_rumah_text": house_status,
        "daya_listrik_text": electricity,
        "status": status,
        "admin_decision": admin_decision,
        "admin_decision_note": "Draft pembersihan dari VERIFIKASI KIPK SNBP 2024 sebelum import ke student_applications",
        "manual_review_required": 1 if notes else 0,
        "manual_house_review": 1 if "status_rumah_perlu_isi_manual" in notes else 0,
        "cleaning_notes": " | ".join(dict.fromkeys(notes)),
        "raw_status_ayah": father_job,
        "raw_status_ibu": mother_job,
        "raw_status_rumah_condition": "",
        "raw_status_rumah_notes": description,
        "raw_penghasilan_ayah_cell": "",
        "raw_penghasilan_ibu_cell": "",
        "raw_social_docs": card_text,
        "label_detected_from_red_style": 0,
        "_raw_sktm_text": sktm_text,
        "_raw_indication_note": indication_note,
    }

    card_split = {
        "source_row_number": row["row_number"],
        "applicant_name": applicant_name,
        "source_reference_number": workbook.cell_value(row, "B"),
        "raw_card_text": card_text,
        "raw_sktm_text": sktm_text,
        "kip": flags["kip"],
        "pkh": flags["pkh"],
        "kks": flags["kks"],
        "dtks": flags["dtks"],
        "sktm": flags["sktm"],
        "unsupported_card_notes": " | ".join(support_notes),
    }

    return record, card_split


def write_csv(path: Path, headers: list[str], rows: list[dict[str, object]]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", newline="", encoding="utf-8") as handle:
        writer = csv.DictWriter(handle, fieldnames=headers)
        writer.writeheader()
        for row in rows:
            writer.writerow({key: row.get(key, "") for key in headers})


def build_review_rows(rows: list[dict[str, object]]) -> list[dict[str, object]]:
    review_rows = []
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
                "cleaning_notes": row.get("cleaning_notes", ""),
                "raw_card_text": row.get("raw_social_docs", ""),
                "raw_sktm_text": row.get("_raw_sktm_text", ""),
                "raw_father_job": row.get("raw_status_ayah", ""),
                "raw_mother_job": row.get("raw_status_ibu", ""),
                "raw_description": row.get("raw_status_rumah_notes", ""),
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


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Export draft CSV student_applications dari workbook KIPK SNBP 2024.")
    parser.add_argument(
        "--input",
        default="/Users/andiakhsanul/Downloads/VERIFIKASI KIPK SNBP 2024 - PENGUMPULAN BERKAS KIPK 2024 (Jawaban).xlsx",
    )
    parser.add_argument("--output-dir", default="infra/data/processed")
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    source_path = Path(args.input).expanduser()
    output_dir = Path(args.output_dir)

    if not source_path.exists():
        print(f"File tidak ditemukan: {source_path}", file=sys.stderr)
        return 1

    workbook_rows = workbook.parse_workbook_rows(source_path, TARGET_SHEET)
    applicant_rows: list[dict[str, object]] = []
    card_rows: list[dict[str, object]] = []

    for row in workbook_rows[1:]:
        record, card_split = build_applicant_record(row)
        if record is None or card_split is None:
            continue
        applicant_rows.append(record)
        card_rows.append(card_split)

    review_rows = build_review_rows(applicant_rows)
    note_counts = Counter()
    for row in applicant_rows:
        for part in str(row.get("cleaning_notes", "")).split("|"):
            item = part.strip()
            if item:
                note_counts[item] += 1

    summary = {
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "source_file": str(source_path),
        "target_sheet": TARGET_SHEET,
        "total_applicant_rows": len(applicant_rows),
        "status_counts": dict(Counter(str(row["status"]) for row in applicant_rows)),
        "source_label_counts": dict(Counter(str(row["source_label_text"]) for row in applicant_rows)),
        "support_counts": {
            key: sum(int(row[key]) for row in applicant_rows)
            for key in ["kip", "pkh", "kks", "dtks", "sktm"]
        },
        "manual_review_rows": len(review_rows),
        "cleaning_note_counts": dict(note_counts),
        "assumption": "KIP/PKH/KKS dibaca dari kolom Kartu Miskin, DTKS dari Kartu Miskin atau SKTM, SKTM dari kolom SKTM. LAYAK dipetakan ke Verified; INDIKASI/Tidak Layak/SURVEY/WAWANCARA dipetakan ke Rejected karena sudah dianggap Indikasi oleh admin offline.",
    }

    applicant_path = output_dir / "kip_snbp_2024_student_applications_draft.csv"
    card_path = output_dir / "kip_snbp_2024_card_split.csv"
    review_path = output_dir / "kip_snbp_2024_student_applications_review.csv"
    summary_path = output_dir / "kip_snbp_2024_student_applications_summary.json"

    write_csv(applicant_path, APPLICANT_HEADERS, applicant_rows)
    write_csv(card_path, CARD_SPLIT_HEADERS, card_rows)
    write_csv(review_path, REVIEW_HEADERS, review_rows)
    summary_path.write_text(json.dumps(summary, indent=2, ensure_ascii=False), encoding="utf-8")

    print(json.dumps(summary, indent=2, ensure_ascii=False))
    print(f"applicant_csv={applicant_path}")
    print(f"card_split_csv={card_path}")
    print(f"review_csv={review_path}")
    print(f"summary_json={summary_path}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
