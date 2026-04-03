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
    "theme": "http://schemas.openxmlformats.org/drawingml/2006/main",
}

TARGET_SHEET = "Verif KIP SNBP 2023"

DATASET_HEADERS = [
    "row_number",
    "no_urut_web",
    "nama_mahasiswa",
    "prodi",
    "fakultas",
    "berkas_link",
    "penghasilan_gabungan_raw",
    "penghasilan_ayah_raw",
    "penghasilan_ibu_raw",
    "jumlah_tanggungan_raw",
    "anak_ke_raw",
    "status_orangtua_raw",
    "status_rumah_raw",
    "daya_listrik_raw",
    "kip",
    "pkh",
    "kks",
    "dtks",
    "sktm",
    "penghasilan_gabungan",
    "penghasilan_ayah",
    "penghasilan_ibu",
    "jumlah_tanggungan",
    "anak_ke",
    "status_orangtua",
    "status_rumah",
    "daya_listrik",
    "label",
    "label_class",
    "raw_social_docs",
    "raw_kesimpulan",
    "label_detected_from_red_style",
    "mapping_warning",
]

FEATURE_ONLY_HEADERS = [
    "kip",
    "pkh",
    "kks",
    "dtks",
    "sktm",
    "penghasilan_gabungan",
    "penghasilan_ayah",
    "penghasilan_ibu",
    "jumlah_tanggungan",
    "anak_ke",
    "status_orangtua",
    "status_rumah",
    "daya_listrik",
    "label",
    "label_class",
]

REVIEW_HEADERS = [
    "row_number",
    "nama_mahasiswa",
    "prodi",
    "fakultas",
    "review_reason",
    "berkas_link",
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


def normalize_text(value: object) -> str:
    text = "" if value is None else str(value)
    text = text.replace("\n", " ")
    text = re.sub(r"\s+", " ", text)
    return text.strip().lower()


def contains_any(text: str, fragments: list[str]) -> bool:
    return any(fragment in text for fragment in fragments)


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
        texts = [node.text or "" for node in cell.iterfind(".//main:t", XML_NS)]
        return "".join(texts).strip()

    value_node = cell.find("main:v", XML_NS)
    if value_node is None or value_node.text is None:
        return ""

    raw_value = value_node.text.strip()
    if cell_type == "s":
        index = int(raw_value)
        return shared_strings[index].strip() if index < len(shared_strings) else raw_value

    return raw_value


def read_theme_colors(archive: zipfile.ZipFile) -> list[str]:
    if "xl/theme/theme1.xml" not in archive.namelist():
        return []

    root = ET.fromstring(archive.read("xl/theme/theme1.xml"))
    color_scheme = root.find("theme:themeElements/theme:clrScheme", XML_NS)
    if color_scheme is None:
        return []

    colors: list[str] = []
    for child in list(color_scheme):
        srgb = child.find("theme:srgbClr", XML_NS)
        if srgb is not None and srgb.attrib.get("val"):
            colors.append(srgb.attrib["val"][-6:].upper())
            continue

        sys_clr = child.find("theme:sysClr", XML_NS)
        if sys_clr is not None and sys_clr.attrib.get("lastClr"):
            colors.append(sys_clr.attrib["lastClr"][-6:].upper())

    return colors


def resolve_color(color_node: ET.Element | None, theme_colors: list[str]) -> str | None:
    if color_node is None:
        return None

    if "rgb" in color_node.attrib:
        return color_node.attrib["rgb"][-6:].upper()

    if "theme" in color_node.attrib:
        index = int(color_node.attrib["theme"])
        return theme_colors[index] if index < len(theme_colors) else None

    return None


def read_styles(archive: zipfile.ZipFile) -> list[dict[str, str | None]]:
    if "xl/styles.xml" not in archive.namelist():
        return []

    theme_colors = read_theme_colors(archive)
    root = ET.fromstring(archive.read("xl/styles.xml"))

    fonts: list[str | None] = []
    fonts_root = root.find("main:fonts", XML_NS)
    if fonts_root is not None:
        for font in fonts_root.findall("main:font", XML_NS):
            fonts.append(resolve_color(font.find("main:color", XML_NS), theme_colors))

    fills: list[str | None] = []
    fills_root = root.find("main:fills", XML_NS)
    if fills_root is not None:
        for fill in fills_root.findall("main:fill", XML_NS):
            pattern_fill = fill.find("main:patternFill", XML_NS)
            fg = pattern_fill.find("main:fgColor", XML_NS) if pattern_fill is not None else None
            bg = pattern_fill.find("main:bgColor", XML_NS) if pattern_fill is not None else None
            fills.append(resolve_color(fg, theme_colors) or resolve_color(bg, theme_colors))

    styles: list[dict[str, str | None]] = []
    cell_xfs = root.find("main:cellXfs", XML_NS)
    if cell_xfs is None:
        return styles

    for xf in cell_xfs.findall("main:xf", XML_NS):
        font_id = int(xf.attrib.get("fontId", 0))
        fill_id = int(xf.attrib.get("fillId", 0))
        styles.append(
            {
                "font_rgb": fonts[font_id] if font_id < len(fonts) else None,
                "fill_rgb": fills[fill_id] if fill_id < len(fills) else None,
            }
        )

    return styles


def is_red_like(hex_color: str | None) -> bool:
    if not hex_color or not re.fullmatch(r"[A-Fa-f0-9]{6}", hex_color):
        return False

    red = int(hex_color[0:2], 16)
    green = int(hex_color[2:4], 16)
    blue = int(hex_color[4:6], 16)

    return red >= 180 and red > green + 35 and red > blue + 35 and green <= 150 and blue <= 150


def parse_workbook_rows(path: Path, target_sheet: str) -> list[dict[str, object]]:
    with zipfile.ZipFile(path) as archive:
        shared_strings = read_shared_strings(archive)
        styles = read_styles(archive)
        sheet_target = resolve_sheet_target(archive, target_sheet)
        root = ET.fromstring(archive.read(sheet_target))
        sheet_data = root.find("main:sheetData", XML_NS)
        if sheet_data is None:
            return []

        rows: list[dict[str, object]] = []
        for row in sheet_data.findall("main:row", XML_NS):
            row_data: dict[str, str] = {}
            style_data: dict[str, int] = {}
            for cell in row.findall("main:c", XML_NS):
                ref = cell.attrib.get("r", "")
                col = "".join(char for char in ref if char.isalpha())
                row_data[col] = read_cell_value(cell, shared_strings)
                style_data[col] = int(cell.attrib.get("s", 0))

            rows.append(
                {
                    "row_number": int(row.attrib.get("r", 0)),
                    "cells": row_data,
                    "styles": style_data,
                    "style_palette": styles,
                }
            )

        return rows


def cell_value(row: dict[str, object], column: str) -> str:
    return str((row.get("cells") or {}).get(column, "")).strip()


def cell_is_red(row: dict[str, object], column: str) -> bool:
    styles = row.get("style_palette") or []
    style_map = row.get("styles") or {}
    style_index = int(style_map.get(column, 0))
    if style_index >= len(styles):
        return False

    style = styles[style_index]
    return is_red_like(style.get("font_rgb")) or is_red_like(style.get("fill_rgb"))


def parse_plain_number(text: str) -> int | None:
    text = normalize_text(text)
    if not text:
        return None

    if re.fullmatch(r"\d+(\.0+)?", text):
        return int(float(text))

    return None


def parse_compact_integer(text: str) -> int | None:
    text = normalize_text(text)
    if not text:
        return None

    if re.fullmatch(r"\d+(\.0+)?", text):
        return int(float(text))

    digits = re.sub(r"[^0-9]", "", text)
    return int(digits) if digits else None


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

    if text in replacements:
        return replacements[text]

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

    if contains_any(combined, ["sewa", "kontrak", "menumpang", "numpang", "menempati", "bukan milik sendiri"]):
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


def normalize_label(value: object, decision_is_red: bool) -> str | None:
    if decision_is_red:
        return "Indikasi"

    text = normalize_text(value)
    if not text:
        return None

    if contains_any(text, ["tidak layak", "tdk layak"]):
        return "Indikasi"

    has_layak = "layak" in text
    has_indikasi = "indikasi" in text

    if has_indikasi and not has_layak:
        return "Indikasi"
    if has_layak and not has_indikasi:
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


def build_review_record(row: dict[str, object], reason: str) -> dict[str, object]:
    return {
        "row_number": row["row_number"],
        "nama_mahasiswa": cell_value(row, "D"),
        "prodi": cell_value(row, "E"),
        "fakultas": cell_value(row, "F"),
        "review_reason": reason,
        "berkas_link": cell_value(row, "G"),
        "raw_social_docs": cell_value(row, "T"),
        "raw_penghasilan_ayah": cell_value(row, "P"),
        "raw_penghasilan_ibu": cell_value(row, "Q"),
        "raw_status_ayah": cell_value(row, "K"),
        "raw_status_ibu": cell_value(row, "L"),
        "raw_anak_ke": cell_value(row, "M"),
        "raw_jumlah_tanggungan": cell_value(row, "N"),
        "raw_daya_listrik": cell_value(row, "O"),
        "raw_kondisi_rumah": cell_value(row, "R"),
        "raw_uraian": cell_value(row, "S"),
        "raw_kesimpulan": cell_value(row, "Y"),
    }


def build_dataset_record(row: dict[str, object]) -> tuple[dict[str, object] | None, dict[str, object] | None]:
    name = cell_value(row, "D")
    if not name:
        return None, None

    decision_is_red = cell_is_red(row, "Y")
    label = normalize_label(cell_value(row, "Y"), decision_is_red)
    if label is None:
        return None, build_review_record(row, "label_tidak_jelas_atau_bercampur")

    social_flags, warnings = extract_social_flags(cell_value(row, "T"))
    father_income_raw = infer_income(cell_value(row, "P"), cell_value(row, "I"), cell_value(row, "K"))
    mother_income_raw = infer_income(cell_value(row, "Q"), cell_value(row, "J"), cell_value(row, "L"))
    if father_income_raw is None or mother_income_raw is None:
        return None, build_review_record(row, "penghasilan_tidak_bisa_dipastikan")

    child_order_raw = parse_child_order(cell_value(row, "M"))
    dependents_raw = parse_dependents(cell_value(row, "N"))
    parent_status, parent_status_raw = encode_parent_status(cell_value(row, "K"), cell_value(row, "L"))
    house_status, house_status_raw = encode_house_status(cell_value(row, "S"), cell_value(row, "R"))
    electricity_status = encode_electricity(cell_value(row, "O"))

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
    record = {
        "row_number": row["row_number"],
        "no_urut_web": cell_value(row, "C"),
        "nama_mahasiswa": name,
        "prodi": cell_value(row, "E"),
        "fakultas": cell_value(row, "F"),
        "berkas_link": cell_value(row, "G"),
        "penghasilan_gabungan_raw": total_income_raw,
        "penghasilan_ayah_raw": father_income_raw,
        "penghasilan_ibu_raw": mother_income_raw,
        "jumlah_tanggungan_raw": dependents_raw,
        "anak_ke_raw": child_order_raw,
        "status_orangtua_raw": parent_status_raw,
        "status_rumah_raw": house_status_raw,
        "daya_listrik_raw": cell_value(row, "O"),
        **social_flags,
        "penghasilan_gabungan": encode_income_level(total_income_raw),
        "penghasilan_ayah": encode_income_level(father_income_raw),
        "penghasilan_ibu": encode_income_level(mother_income_raw),
        "jumlah_tanggungan": encode_dependents_level(dependents_raw),
        "anak_ke": encode_child_order_level(child_order_raw),
        "status_orangtua": parent_status,
        "status_rumah": house_status,
        "daya_listrik": electricity_status,
        "label": label,
        "label_class": 0 if label == "Layak" else 1,
        "raw_social_docs": cell_value(row, "T"),
        "raw_kesimpulan": cell_value(row, "Y"),
        "label_detected_from_red_style": 1 if decision_is_red else 0,
        "mapping_warning": " | ".join(warnings),
    }

    required_fields = [
        "kip",
        "pkh",
        "kks",
        "dtks",
        "sktm",
        "penghasilan_gabungan",
        "penghasilan_ayah",
        "penghasilan_ibu",
        "jumlah_tanggungan",
        "anak_ke",
        "status_orangtua",
        "status_rumah",
        "daya_listrik",
    ]
    if any(record[field] is None for field in required_fields):
        return None, build_review_record(row, "encoding_fitur_tidak_lengkap")

    return record, None


def write_csv(path: Path, headers: list[str], rows: list[dict[str, object]]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", newline="", encoding="utf-8") as handle:
        writer = csv.DictWriter(handle, fieldnames=headers)
        writer.writeheader()
        for row in rows:
            writer.writerow({key: row.get(key, "") for key in headers})


def build_summary(source_path: Path, dataset_rows: list[dict[str, object]], review_rows: list[dict[str, object]]) -> dict[str, object]:
    label_counts = Counter(str(row["label"]) for row in dataset_rows)
    review_counts = Counter(str(row["review_reason"]) for row in review_rows)
    red_detected = sum(int(row["label_detected_from_red_style"]) for row in dataset_rows)

    return {
        "source_file": str(source_path),
        "target_sheet": TARGET_SHEET,
        "dataset_rows": len(dataset_rows),
        "review_rows": len(review_rows),
        "label_counts": dict(label_counts),
        "review_reason_counts": dict(review_counts),
        "red_style_indikasi_rows": red_detected,
        "note": "penghasilan_gabungan dihitung dari penghasilan_ayah_raw + penghasilan_ibu_raw lalu di-encode ke 1/2/3",
    }


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Export dataset 13 fitur KIP SNBP 2023 ke CSV siap model.")
    parser.add_argument(
        "--input",
        default="/Users/andiakhsanul/Downloads/Verifikasi KIP SNBP 2023.xlsx",
        help="Path ke file Excel mentah.",
    )
    parser.add_argument(
        "--output-dir",
        default="infra/data/processed",
        help="Direktori output CSV/JSON hasil ekstraksi.",
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
    dataset_rows: list[dict[str, object]] = []
    review_rows: list[dict[str, object]] = []

    for row in data_rows:
        record, review_record = build_dataset_record(row)
        if record is not None:
            dataset_rows.append(record)
            continue
        if review_record is not None:
            review_rows.append(review_record)

    feature_only_rows = [
        {key: row[key] for key in FEATURE_ONLY_HEADERS}
        for row in dataset_rows
    ]

    dataset_path = output_dir / "kip_snbp_2023_ml_dataset.csv"
    feature_only_path = output_dir / "kip_snbp_2023_ml_features_only.csv"
    review_path = output_dir / "kip_snbp_2023_ml_review_queue.csv"
    summary_path = output_dir / "kip_snbp_2023_ml_summary.json"

    write_csv(dataset_path, DATASET_HEADERS, dataset_rows)
    write_csv(feature_only_path, FEATURE_ONLY_HEADERS, feature_only_rows)
    write_csv(review_path, REVIEW_HEADERS, review_rows)

    summary = build_summary(source_path, dataset_rows, review_rows)
    summary_path.write_text(json.dumps(summary, indent=2, ensure_ascii=False), encoding="utf-8")

    print(json.dumps(summary, indent=2, ensure_ascii=False))
    print(f"dataset_csv={dataset_path}")
    print(f"feature_only_csv={feature_only_path}")
    print(f"review_csv={review_path}")
    print(f"summary_json={summary_path}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
