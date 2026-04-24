"""Authoritative encoder untuk fitur aplikasi KIP-K.

Seluruh encoding aplikasi dilakukan di Flask ML. Laravel hanya menyimpan
raw data (student_applications) — Flask yang menerjemahkan teks/angka
mentah menjadi kode ordinal/biner yang dipakai model.

Konvensi ordinal: kode tinggi = kondisi lebih sejahtera / risiko indikasi lebih rendah.
  penghasilan:      1 (tidak ada) .. 5 (>=4jt)
  jumlah_tanggungan:1 (>=6)       .. 5 (<2)
  anak_ke:          1 (>=5)       .. 5 (anak pertama)
  status_orangtua:  1 (yatim piatu) .. 3 (lengkap)
  status_rumah:     1 (tidak punya) .. 4 (milik sendiri)
  daya_listrik:     1 (tidak ada) .. 5 (>1300VA)

Binary (kip/pkh/kks/dtks/sktm): 1 = punya bantuan / indikator kurang mampu.
"""

import re
from typing import Any

from config import BINARY_FEATURES, DB_FEATURE_COLUMNS, ORDINAL_FEATURES

RAW_APPLICANT_FIELDS = [
    "kip", "pkh", "kks", "dtks", "sktm",
    "penghasilan_ayah_rupiah", "penghasilan_ibu_rupiah", "penghasilan_gabungan_rupiah",
    "jumlah_tanggungan_raw", "anak_ke_raw",
    "status_orangtua_text", "status_rumah_text", "daya_listrik_text",
]


def _normalize_text(value: Any) -> str:
    if value is None:
        return ""
    text = str(value).strip().lower()
    return re.sub(r"\s+", " ", text)


def _contains_any(haystack: str, needles: list[str]) -> bool:
    return any(needle in haystack for needle in needles)


def normalize_binary(value: Any, field_name: str) -> int:
    try:
        parsed = int(float(value))
    except (TypeError, ValueError):
        raise ValueError(f"{field_name} wajib bernilai 0 atau 1.")
    if parsed not in (0, 1):
        raise ValueError(f"{field_name} wajib bernilai 0 atau 1.")
    return parsed


def encode_income(value: Any) -> int:
    if value is None or value == "":
        return 1
    try:
        income = int(float(value))
    except (TypeError, ValueError):
        return 1
    if income <= 0:
        return 1
    if income < 1_000_000:
        return 2
    if income < 2_000_000:
        return 3
    if income < 4_000_000:
        return 4
    return 5


def encode_jumlah_tanggungan(value: Any) -> int:
    try:
        dependents = int(float(value)) if value not in (None, "") else 0
    except (TypeError, ValueError):
        raise ValueError("jumlah_tanggungan_raw wajib berupa angka.")
    if dependents >= 6:
        return 1
    if dependents >= 5:
        return 2
    if dependents >= 4:
        return 3
    if dependents >= 2:
        return 4
    return 5


def encode_anak_ke(value: Any) -> int:
    try:
        child_order = int(float(value)) if value not in (None, "") else 0
    except (TypeError, ValueError):
        return 3
    if child_order <= 0:
        return 3
    if child_order >= 5:
        return 1
    if child_order == 4:
        return 2
    if child_order == 3:
        return 3
    if child_order == 2:
        return 4
    return 5


def encode_status_orangtua(value: Any) -> int:
    normalized = _normalize_text(value)
    if not normalized:
        return 2

    if "yatim piatu" in normalized:
        return 1

    father_deceased = _contains_any(normalized, [
        "ayah=wafat", "ayah=meninggal", "ayah meninggal", "ayah wafat",
    ])
    mother_deceased = _contains_any(normalized, [
        "ibu=wafat", "ibu=meninggal", "ibu meninggal", "ibu wafat", "ibu=wafar",
    ])

    if father_deceased and mother_deceased:
        return 1

    if father_deceased or mother_deceased:
        return 2

    if _contains_any(normalized, [
        "yatim", "piatu", "cerai", "tiri", "wali",
        "tidak jelas", "tidak lengkap", "terpisah",
    ]):
        return 2

    if re.search(r"(ayah|ibu)=\s*(;|$)", normalized):
        return 2

    if _contains_any(normalized, ["ayah=hidup", "ibu=hidup", "lengkap", "orang tua lengkap"]):
        return 3

    return 2


def encode_status_rumah(value: Any) -> int:
    normalized = _normalize_text(value)
    if not normalized:
        return 2

    if _contains_any(normalized, ["tidak memiliki", "tidak punya rumah", "tidak punya"]):
        return 1

    # "Sewa / Menumpang" — gabungan, perlakukan sebagai menumpang (lebih rawan)
    if "sewa / menumpang" in normalized or "sewa/menumpang" in normalized:
        return 2

    if "menumpang" in normalized:
        return 2

    if _contains_any(normalized, ["sewa", "kontrak"]):
        return 3

    if _contains_any(normalized, [
        "milik sendiri", "rumah sendiri", "milik pribadi",
        "punya sendiri", "punya pribadi",
    ]):
        return 4

    return 2


def encode_daya_listrik(value: Any) -> int:
    normalized = _normalize_text(value)
    if not normalized:
        return 2

    if _contains_any(normalized, [
        "tidak ada", "non pln", "non-pln", "nonpln", "tidak punya",
    ]):
        return 1

    # "2.200 VA" / "1.300 va" → hilangkan titik/koma pemisah ribuan
    cleaned = re.sub(r"(\d)[.,](\d)", r"\1\2", normalized)
    numbers = [int(num) for num in re.findall(r"\d+", cleaned)]
    if not numbers:
        return 2

    max_value = max(numbers)
    if max_value <= 0:
        return 1
    if max_value <= 450:
        return 2
    if max_value <= 900:
        return 3
    if max_value <= 1300:
        return 4
    return 5


def _coalesce_penghasilan_gabungan(raw: dict) -> Any:
    gabungan = raw.get("penghasilan_gabungan_rupiah")
    if gabungan not in (None, ""):
        return gabungan
    ayah = raw.get("penghasilan_ayah_rupiah") or 0
    ibu = raw.get("penghasilan_ibu_rupiah") or 0
    try:
        return float(ayah) + float(ibu)
    except (TypeError, ValueError):
        return 0


def encode_application_features(raw: dict) -> dict:
    """Terima raw applicant data → kembalikan 13 fitur terkode siap model."""
    return {
        "kip": normalize_binary(raw.get("kip"), "kip"),
        "pkh": normalize_binary(raw.get("pkh"), "pkh"),
        "kks": normalize_binary(raw.get("kks"), "kks"),
        "dtks": normalize_binary(raw.get("dtks"), "dtks"),
        "sktm": normalize_binary(raw.get("sktm"), "sktm"),
        "penghasilan_gabungan": encode_income(_coalesce_penghasilan_gabungan(raw)),
        "penghasilan_ayah": encode_income(raw.get("penghasilan_ayah_rupiah")),
        "penghasilan_ibu": encode_income(raw.get("penghasilan_ibu_rupiah")),
        "jumlah_tanggungan": encode_jumlah_tanggungan(raw.get("jumlah_tanggungan_raw")),
        "anak_ke": encode_anak_ke(raw.get("anak_ke_raw")),
        "status_orangtua": encode_status_orangtua(raw.get("status_orangtua_text")),
        "status_rumah": encode_status_rumah(raw.get("status_rumah_text")),
        "daya_listrik": encode_daya_listrik(raw.get("daya_listrik_text")),
    }


def _looks_like_raw_payload(payload: dict) -> bool:
    """Raw payload terdeteksi jika ada kolom bersufiks _rupiah/_raw/_text."""
    raw_markers = {
        "penghasilan_ayah_rupiah", "penghasilan_ibu_rupiah", "penghasilan_gabungan_rupiah",
        "jumlah_tanggungan_raw", "anak_ke_raw",
        "status_orangtua_text", "status_rumah_text", "daya_listrik_text",
    }
    return any(key in payload for key in raw_markers)


def encode_or_validate(payload: dict) -> dict:
    """Masuk raw → encode; masuk pre-encoded → validasi range. Selalu output 13 kolom int."""
    if _looks_like_raw_payload(payload):
        return encode_application_features(payload)
    return validate_encoded_features(payload)


def validate_encoded_features(payload: dict) -> dict:
    """Fallback: validasi payload yang sudah terkode (backward compat)."""
    values = {}
    for feature in DB_FEATURE_COLUMNS:
        if feature not in payload:
            raise ValueError(f"Field wajib tidak lengkap: {feature}")
        try:
            parsed = int(payload[feature])
        except (TypeError, ValueError) as exc:
            raise ValueError(f"Nilai fitur {feature} harus berupa angka yang valid") from exc

        if feature in BINARY_FEATURES and parsed not in (0, 1):
            raise ValueError(f"Field {feature} wajib bernilai 0 atau 1. Nilai: {parsed}")
        if feature == "status_rumah" and parsed not in (1, 2, 3, 4):
            raise ValueError(f"Field {feature} wajib bernilai 1..4. Nilai: {parsed}")
        if feature == "status_orangtua" and parsed not in (1, 2, 3):
            raise ValueError(f"Field {feature} wajib bernilai 1..3. Nilai: {parsed}")
        if (
            feature in ORDINAL_FEATURES
            and feature not in ("status_rumah", "status_orangtua")
            and parsed not in (1, 2, 3, 4, 5)
        ):
            raise ValueError(f"Field {feature} wajib bernilai 1..5. Nilai: {parsed}")
        values[feature] = parsed
    return values
