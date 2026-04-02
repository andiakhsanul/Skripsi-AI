#!/usr/bin/env python3

from __future__ import annotations

import argparse
import csv
import json
import re
import sys
import zipfile
import xml.etree.ElementTree as ET
from collections import Counter
from pathlib import Path


XML_NS = {
    "main": "http://schemas.openxmlformats.org/spreadsheetml/2006/main",
    "rel": "http://schemas.openxmlformats.org/officeDocument/2006/relationships",
    "pkgrel": "http://schemas.openxmlformats.org/package/2006/relationships",
}

TARGET_SHEET = "Verif KIP SNBP 2023"

MODEL_READY_HEADERS = [
    "row_number",
    "no_urut_web",
    "nama_mahasiswa",
    "prodi",
    "fakultas",
    "berkas_link",
    "kip",
    "pkh",
    "kks",
    "dtks",
    "sktm",
    "penghasilan_gabungan_raw",
    "penghasilan_ayah_raw",
    "penghasilan_ibu_raw",
    "penghasilan_gabungan",
    "penghasilan_ayah",
    "penghasilan_ibu",
    "jumlah_tanggungan_raw",
    "jumlah_tanggungan",
    "anak_ke_raw",
    "anak_ke",
    "status_orangtua_raw",
    "status_orangtua",
    "status_rumah_raw",
    "status_rumah",
    "daya_listrik_raw",
    "daya_listrik",
    "label",
    "label_class",
    "raw_social_docs",
    "raw_kesimpulan",
    "mapping_warning",
]

REVIEW_HEADERS = [
    "row_number",
    "nama_mahasiswa",
    "prodi",
    "fakultas",
    "review_reason",
    "raw_social_docs",
    "raw_penghasilan_ayah",
    "raw_penghasilan_ibu",
    "raw_status_ayah",
    "raw_status_ibu",
    "raw_anak_ke",
    "raw_jumlah_tanggungan",
    "raw_daya_listrik",
    "raw_kondisi_rumah",
    "raw_uraian",
    "raw_kesimpulan",
]

ASSUMPTIONS = [
    "Fitur bantuan sosial diambil dari kolom T. Kolom H tidak dipakai karena lebih dekat ke dokumen KIP Kuliah, bukan indikator sosial inti penelitian.",
    "Baris dengan label campuran atau tidak eksplisit (mis. hanya catatan wawancara) tidak dimasukkan ke dataset model-ready.",
    "Jika penghasilan kosong tetapi status orang tua wafat atau pekerjaan eksplisit tidak bekerja/IRT, nilai penghasilan diimputasi 0.",
    "Status rumah hanya diambil jika ownership cukup jelas dari kolom uraian/kondisi rumah. Baris yang tidak jelas dipindah ke review queue.",
    "Status orang tua cerai/wali/tiri dipetakan ke level 2 agar tetap terwakili sebagai kondisi non-lengkap.",
]


def normalize_text(value: object) -> str:
    text = "" if value is None else str(value)
    text = text.replace("\n", " ")
    text = re.sub(r"\s+", " ", text)
    return text.strip().lower()


def contains_any(text: str, fragments: list[str]) -> bool:
    return any(fragment in text for fragment in fragments)


def parse_workbook_rows(path: Path, target_sheet: str) -> list[dict[str, str]]:
    with zipfile.ZipFile(path) as archive:
        shared_strings = read_shared_strings(archive)
        sheet_target = resolve_sheet_target(archive, target_sheet)
        root = ET.fromstring(archive.read(sheet_target))
        sheet_data = root.find("main:sheetData", XML_NS)
        if sheet_data is None:
            return []

        rows: list[dict[str, str]] = []
        for row in sheet_data.findall("main:row", XML_NS):
            row_data: dict[str, str] = {}
            for cell in row.findall("main:c", XML_NS):
                ref = cell.attrib.get("r", "")
                col = "".join(char for char in ref if char.isalpha())
                row_data[col] = read_cell_value(cell, shared_strings)
            rows.append(row_data)

        return rows


def read_shared_strings(archive: zipfile.ZipFile) -> list[str]:
    if "xl/sharedStrings.xml" not in archive.namelist():
        return []

    root = ET.fromstring(archive.read("xl/sharedStrings.xml"))
    shared_strings: list[str] = []
    for node in root.findall("main:si", XML_NS):
        text = "".join((part.text or "") for part in node.iterfind(".//main:t", XML_NS))
        shared_strings.append(text)

    return shared_strings


def resolve_sheet_target(archive: zipfile.ZipFile, target_sheet: str) -> str:
    workbook = ET.fromstring(archive.read("xl/workbook.xml"))
    relationships = ET.fromstring(archive.read("xl/_rels/workbook.xml.rels"))
    rel_map = {
        rel.attrib["Id"]: rel.attrib["Target"]
        for rel in relationships.findall("pkgrel:Relationship", XML_NS)
    }

    sheets = workbook.find("main:sheets", XML_NS)
    if sheets is None:
        raise FileNotFoundError("Workbook tidak memiliki definisi sheets.")

    for sheet in sheets.findall("main:sheet", XML_NS):
        if sheet.attrib.get("name") != target_sheet:
            continue

        rel_id = sheet.attrib.get(f"{{{XML_NS['rel']}}}id")
        if rel_id is None:
            break

        target = rel_map[rel_id]
        return target if target.startswith("xl/") else f"xl/{target}"

    raise FileNotFoundError(f"Sheet '{target_sheet}' tidak ditemukan di workbook.")


def read_cell_value(cell: ET.Element, shared_strings: list[str]) -> str:
    cell_type = cell.attrib.get("t")

    if cell_type == "inlineStr":
        node = cell.find("main:is/main:t", XML_NS)
        return (node.text or "").strip() if node is not None else ""

    value_node = cell.find("main:v", XML_NS)
    if value_node is None or value_node.text is None:
        return ""

    raw_value = value_node.text.strip()
    if cell_type == "s":
        index = int(raw_value)
        return shared_strings[index].strip() if index < len(shared_strings) else raw_value

    return raw_value


def parse_plain_number(text: str) -> int | None:
    text = normalize_text(text)
    if not text:
        return None

    if re.fullmatch(r"\d+(\.0+)?", text):
        return int(float(text))

    return None


def parse_currency(value: object) -> int | None:
    text = normalize_text(value)
    if not text or text in {"-", "tidak jelas", "unknown"}:
        return None

    text = text.replace("rp", "").replace(" ", "")
    text = text.replace("s/d", "-").replace("sd", "-")
    text = text.replace("–", "-").replace("—", "-")

    plain_number = parse_plain_number(text)
    if plain_number is not None:
        return plain_number

    if "juta" in text or "jt" in text:
        numbers = [float(token.replace(",", ".")) for token in re.findall(r"\d+[.,]?\d*", text)]
        if numbers:
            return int(sum(numbers) / len(numbers) * 1_000_000)

    if "rb" in text or "ribu" in text:
        numbers = [float(token.replace(",", ".")) for token in re.findall(r"\d+[.,]?\d*", text)]
        if numbers:
            return int(sum(numbers) / len(numbers) * 1_000)

    if "-" in text:
        parts = [part for part in re.split(r"-+", text) if part]
        numbers = [parse_compact_integer(part) for part in parts]
        numbers = [number for number in numbers if number is not None]
        if numbers:
            return int(sum(numbers) / len(numbers))

    return parse_compact_integer(text)


def parse_compact_integer(text: str) -> int | None:
    text = normalize_text(text)
    if not text:
        return None

    if re.fullmatch(r"\d+(\.0+)?", text):
        return int(float(text))

    digits = re.sub(r"[^0-9]", "", text)
    return int(digits) if digits else None


def infer_income(raw_income: object, occupation: object, status: object) -> int | None:
    parsed = parse_currency(raw_income)
    if parsed is not None:
        return parsed

    occupation_text = normalize_text(occupation)
    status_text = normalize_text(status)

    if contains_any(status_text, ["wafat", "meninggal", "wafar"]):
        return 0

    if contains_any(
        occupation_text,
        [
            "ibu rumah tangga",
            "rumah tangga",
            "irt",
            "i.r.t",
            "tidak bekerja",
            "tidak kerja",
            "belum bekerja",
            "pengangguran",
            "tidak ada",
        ],
    ):
        return 0

    return None


def parse_child_order(value: object) -> int | None:
    text = normalize_text(value)
    if not text or text == "-":
        return None

    replacements = {
        "pertama": 1,
        "ke satu": 1,
        "kesatu": 1,
        "kedua": 2,
        "ke dua": 2,
        "ketiga": 3,
        "ke tiga": 3,
        "keempat": 4,
        "ke empat": 4,
        "kelima": 5,
        "ke lima": 5,
    }

    for token, number in replacements.items():
        if token == text:
            return number

    text = re.sub(r"(\d+)\.0\b", r"\1", text)
    match = re.search(r"(\d+)\s*(?:dari|dr)", text)
    if match:
        return int(match.group(1))

    match = re.search(r"(\d+)", text)
    return int(match.group(1)) if match else None


def parse_dependents(value: object) -> int | None:
    text = normalize_text(value)
    if not text or text == "-":
        return None

    plain_number = parse_plain_number(text)
    if plain_number is not None:
        return plain_number

    text = re.sub(r"(\d+)\.0\b", r"\1", text)
    numbers = [int(token) for token in re.findall(r"\d+", text)]
    if not numbers:
        return None

    if contains_any(text, ["dan", "lain", "nenek", "kakek"]):
        return sum(numbers)

    return numbers[0]


def encode_income_level(amount: int | None) -> int | None:
    if amount is None:
        return None
    if amount < 1_000_000:
        return 1
    if amount < 4_000_000:
        return 2
    return 3


def encode_dependents_level(count: int | None) -> int | None:
    if count is None:
        return None
    if count >= 6:
        return 1
    if count >= 4:
        return 2
    return 3


def encode_child_order_level(order: int | None) -> int | None:
    if order is None:
        return None
    if order >= 5:
        return 1
    if order >= 3:
        return 2
    return 3


def encode_parent_status(father_status: object, mother_status: object) -> tuple[int | None, str]:
    father = normalize_text(father_status)
    mother = normalize_text(mother_status)

    dead_father = contains_any(father, ["wafat", "meninggal", "wafar"])
    dead_mother = contains_any(mother, ["wafat", "meninggal", "wafar"])
    fragile_father = dead_father or contains_any(father, ["cerai", "wali", "tiri", "tidak jelas"])
    fragile_mother = dead_mother or contains_any(mother, ["cerai", "wali", "tiri", "tidak jelas"])

    raw_value = f"ayah={father_status or ''}; ibu={mother_status or ''}"
    if dead_father and dead_mother:
        return 1, raw_value
    if fragile_father or fragile_mother:
        return 2, raw_value
    if "hidup" in father and "hidup" in mother:
        return 3, raw_value

    return None, raw_value


def encode_house_status(ownership_notes: object, house_condition: object) -> tuple[int | None, str]:
    ownership = normalize_text(ownership_notes)
    condition = normalize_text(house_condition)
    combined = " ".join(part for part in [ownership, condition] if part).strip()
    raw_value = f"uraian={ownership_notes or ''}; kondisi={house_condition or ''}"

    if not combined:
        return None, raw_value

    if contains_any(combined, ["tidak punya rumah", "tidak memiliki rumah"]):
        return 1, raw_value

    if contains_any(
        combined,
        ["sewa", "kontrak", "menumpang", "numpang", "menempati", "bukan milik sendiri"],
    ):
        return 2, raw_value

    if contains_any(combined, ["sendiri", "milik sendiri", "pribadi", "punya pribadi"]):
        return 3, raw_value

    return None, raw_value


def encode_electricity(value: object) -> int | None:
    text = normalize_text(value)
    if not text or text in {"-", "unknown", "token", "tulisan hilang"}:
        return None

    if contains_any(text, ["non pln", "non-pln", "tidak ada listrik", "tidak ada"]):
        return 1

    watt = parse_plain_number(text)
    if watt is None:
        match = re.search(r"(\d+)", text)
        watt = int(match.group(1)) if match else None

    if watt is None:
        return None
    if watt <= 0:
        return 1
    if watt <= 900:
        return 2
    return 3


def normalize_label(value: object) -> str | None:
    text = normalize_text(value)
    if not text:
        return None

    if contains_any(text, ["tidak layak", "tdk layak"]):
        return "Indikasi"

    has_layak = "layak" in text
    has_indikasi = "indikasi" in text

    if has_layak and has_indikasi:
        return None
    if has_indikasi:
        return "Indikasi"
    if has_layak:
        return "Layak"

    return None


def extract_social_flags(value: object) -> tuple[dict[str, int], list[str]]:
    text = normalize_text(value)
    warnings: list[str] = []

    if contains_any(text, ["mbr", "dinsos", "pks"]):
        warnings.append("kolom bantuan sosial memuat istilah tambahan yang tidak dipetakan langsung")

    flags = {
        "kip": 1 if "kip" in text else 0,
        "pkh": 1 if "pkh" in text else 0,
        "kks": 1 if "kks" in text else 0,
        "dtks": 1 if "dtks" in text else 0,
        "sktm": 1 if "sktm" in text else 0,
    }

    return flags, warnings


def build_model_record(row: dict[str, str]) -> tuple[dict[str, object] | None, dict[str, object] | None]:
    name = (row.get("D") or "").strip()
    if not name:
        return None, None

    label = normalize_label(row.get("Y"))
    if label is None:
        return None, build_review_record(row, "label_tidak_jelas_atau_bercampur")

    social_flags, warnings = extract_social_flags(row.get("T"))

    father_income_raw = infer_income(row.get("P"), row.get("I"), row.get("K"))
    mother_income_raw = infer_income(row.get("Q"), row.get("J"), row.get("L"))
    if father_income_raw is None or mother_income_raw is None:
        return None, build_review_record(row, "penghasilan_tidak_bisa_dipastikan")

    child_order_raw = parse_child_order(row.get("M"))
    dependents_raw = parse_dependents(row.get("N"))
    parent_status, parent_status_raw = encode_parent_status(row.get("K"), row.get("L"))
    house_status, house_status_raw = encode_house_status(row.get("S"), row.get("R"))
    electricity_status = encode_electricity(row.get("O"))

    if child_order_raw is None:
        return None, build_review_record(row, "anak_ke_tidak_terbaca")
    if dependents_raw is None:
        return None, build_review_record(row, "jumlah_tanggungan_tidak_terbaca")
    if parent_status is None:
        return None, build_review_record(row, "status_orangtua_tidak_jelas")
    if house_status is None:
        return None, build_review_record(row, "status_rumah_tidak_jelas")
    if electricity_status is None:
        return None, build_review_record(row, "daya_listrik_tidak_jelas")

    total_income_raw = father_income_raw + mother_income_raw
    encoded_record = {
        "row_number": row.get("A", ""),
        "no_urut_web": row.get("C", ""),
        "nama_mahasiswa": name,
        "prodi": row.get("E", ""),
        "fakultas": row.get("F", ""),
        "berkas_link": row.get("G", ""),
        **social_flags,
        "penghasilan_gabungan_raw": total_income_raw,
        "penghasilan_ayah_raw": father_income_raw,
        "penghasilan_ibu_raw": mother_income_raw,
        "penghasilan_gabungan": encode_income_level(total_income_raw),
        "penghasilan_ayah": encode_income_level(father_income_raw),
        "penghasilan_ibu": encode_income_level(mother_income_raw),
        "jumlah_tanggungan_raw": dependents_raw,
        "jumlah_tanggungan": encode_dependents_level(dependents_raw),
        "anak_ke_raw": child_order_raw,
        "anak_ke": encode_child_order_level(child_order_raw),
        "status_orangtua_raw": parent_status_raw,
        "status_orangtua": parent_status,
        "status_rumah_raw": house_status_raw,
        "status_rumah": house_status,
        "daya_listrik_raw": row.get("O", ""),
        "daya_listrik": electricity_status,
        "label": label,
        "label_class": 0 if label == "Layak" else 1,
        "raw_social_docs": row.get("T", ""),
        "raw_kesimpulan": row.get("Y", ""),
        "mapping_warning": " | ".join(warnings),
    }

    if any(encoded_record[field] is None for field in [
        "penghasilan_gabungan",
        "penghasilan_ayah",
        "penghasilan_ibu",
        "jumlah_tanggungan",
        "anak_ke",
        "status_orangtua",
        "status_rumah",
        "daya_listrik",
    ]):
        return None, build_review_record(row, "encoding_fitur_tidak_lengkap")

    return encoded_record, None


def build_review_record(row: dict[str, str], reason: str) -> dict[str, object]:
    return {
        "row_number": row.get("A", ""),
        "nama_mahasiswa": row.get("D", ""),
        "prodi": row.get("E", ""),
        "fakultas": row.get("F", ""),
        "review_reason": reason,
        "raw_social_docs": row.get("T", ""),
        "raw_penghasilan_ayah": row.get("P", ""),
        "raw_penghasilan_ibu": row.get("Q", ""),
        "raw_status_ayah": row.get("K", ""),
        "raw_status_ibu": row.get("L", ""),
        "raw_anak_ke": row.get("M", ""),
        "raw_jumlah_tanggungan": row.get("N", ""),
        "raw_daya_listrik": row.get("O", ""),
        "raw_kondisi_rumah": row.get("R", ""),
        "raw_uraian": row.get("S", ""),
        "raw_kesimpulan": row.get("Y", ""),
    }


def write_csv(path: Path, headers: list[str], rows: list[dict[str, object]]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", newline="", encoding="utf-8") as handle:
        writer = csv.DictWriter(handle, fieldnames=headers)
        writer.writeheader()
        for row in rows:
            writer.writerow(row)


def build_summary(
    source_path: Path,
    named_rows: int,
    model_ready_rows: list[dict[str, object]],
    review_rows: list[dict[str, object]],
) -> dict[str, object]:
    label_counts = Counter(row["label"] for row in model_ready_rows)
    review_counts = Counter(row["review_reason"] for row in review_rows)

    return {
        "source_file": str(source_path),
        "target_sheet": TARGET_SHEET,
        "named_rows": named_rows,
        "model_ready_rows": len(model_ready_rows),
        "review_rows": len(review_rows),
        "label_counts": dict(label_counts),
        "review_reason_counts": dict(review_counts),
        "assumptions": ASSUMPTIONS,
    }


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Extract model-ready KIP SNBP 2023 rows into encoded CSV files."
    )
    parser.add_argument(
        "--input",
        default="/Users/andiakhsanul/Downloads/Verifikasi KIP SNBP 2023.xlsx",
        help="Path ke file Excel mentah.",
    )
    parser.add_argument(
        "--output-dir",
        default="infra/data/processed",
        help="Direktori output untuk CSV hasil ekstraksi.",
    )
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    source_path = Path(args.input).expanduser()
    output_dir = Path(args.output_dir)

    if not source_path.exists():
        print(f"File tidak ditemukan: {source_path}", file=sys.stderr)
        return 1

    workbook_rows = parse_workbook_rows(source_path, TARGET_SHEET)
    if not workbook_rows:
        print("Workbook kosong atau sheet tidak memiliki data.", file=sys.stderr)
        return 1

    data_rows = workbook_rows[1:]
    model_ready_rows: list[dict[str, object]] = []
    review_rows: list[dict[str, object]] = []

    for row in data_rows:
        record, review_record = build_model_record(row)
        if record is not None:
            model_ready_rows.append(record)
            continue
        if review_record is not None:
            review_rows.append(review_record)

    named_rows = sum(1 for row in data_rows if (row.get("D") or "").strip())

    model_path = output_dir / "kip_snbp_2023_model_ready.csv"
    review_path = output_dir / "kip_snbp_2023_review_queue.csv"
    summary_path = output_dir / "kip_snbp_2023_summary.json"

    write_csv(model_path, MODEL_READY_HEADERS, model_ready_rows)
    write_csv(review_path, REVIEW_HEADERS, review_rows)

    summary = build_summary(source_path, named_rows, model_ready_rows, review_rows)
    summary_path.parent.mkdir(parents=True, exist_ok=True)
    summary_path.write_text(json.dumps(summary, indent=2, ensure_ascii=False), encoding="utf-8")

    print(json.dumps(summary, indent=2, ensure_ascii=False))
    print(f"model_ready_csv={model_path}")
    print(f"review_queue_csv={review_path}")
    print(f"summary_json={summary_path}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
