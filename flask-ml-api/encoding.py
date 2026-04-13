import re

def normalize_binary(value, field_name: str) -> int:
    try:
        val = int(value)
        if val in (0, 1):
            return val
    except (TypeError, ValueError):
        pass
    raise ValueError(f"{field_name} wajib bernilai 0 atau 1.")

def encode_income(value, field_name: str) -> int:
    # NULL penghasilan → fallback ke 0
    if value is None or value == "":
        value = 0
    
    try:
        income = int(float(value))
    except (TypeError, ValueError):
        income = 0
        
    if income < 1_000_000:
        return 1
    elif income < 4_000_000:
        return 2
    else:
        return 3

def encode_jumlah_tanggungan(value) -> int:
    try:
        dependents = int(float(value)) if value is not None else 0
    except (TypeError, ValueError):
        raise ValueError("jumlah_tanggungan_raw wajib berupa angka.")
        
    if dependents >= 6:
        return 1
    elif dependents >= 4:
        return 2
    else:
        return 3

def encode_anak_ke(value) -> int:
    try:
        child_order = int(float(value)) if value is not None else 1
        if child_order < 1:
            child_order = 1
    except (TypeError, ValueError):
        child_order = 1
        
    if child_order >= 5:
        return 1
    elif child_order >= 3:
        return 2
    else:
        return 3

def _normalize_text(value) -> str:
    if value is None:
        return ""
    text = str(value).strip().lower()
    return re.sub(r'\s+', ' ', text)

def _contains_any(haystack: str, needles: list[str]) -> bool:
    for needle in needles:
        if needle in haystack:
            return True
    return False

def encode_status_orangtua(value) -> int:
    normalized = _normalize_text(value)
    if not normalized:
        return 2 # Fallback
        
    if "yatim piatu" in normalized:
        return 1
        
    father_deceased = _contains_any(normalized, [
        "ayah=wafat", "ayah=meninggal", "ayah meninggal", "ayah wafat",
        "ayah=meninggal dunia"
    ])
    mother_deceased = _contains_any(normalized, [
        "ibu=wafat", "ibu=meninggal", "ibu meninggal", "ibu wafat",
        "ibu=wafar", "ibu=meninggal dunia"
    ])
    
    if father_deceased and mother_deceased:
        return 1
        
    if "yatim" in normalized or "piatu" in normalized:
        return 2
        
    if father_deceased or mother_deceased:
        return 2
        
    if "cerai" in normalized:
        return 2
        
    if _contains_any(normalized, ["tiri", "wali"]):
        return 2
        
    if _contains_any(normalized, ["tidak jelas", "ayah=;", "ibu=;"]):
        return 2
        
    if re.search(r'ayah=\s*;', normalized) or re.search(r'ibu=\s*;', normalized):
        return 2
        
    if _contains_any(normalized, ["ayah=hidup", "ibu=hidup", "lengkap", "orang tua lengkap"]):
        return 3
        
    return 2

def encode_status_rumah(value) -> int:
    normalized = _normalize_text(value)
    
    if _contains_any(normalized, ["tidak memiliki", "tidak punya rumah"]):
        return 1
        
    if _contains_any(normalized, ["sewa", "kontrak", "menumpang", "menempati", "bukan milik sendiri"]):
        return 2
        
    if _contains_any(normalized, ["milik sendiri", "rumah sendiri", "sendiri", "punya pribadi", "punya sendiri", "milik pribadi"]):
        return 3
        
    # If unmapped, default to 2 to be safe
    return 2

def encode_daya_listrik(value) -> int:
    normalized = _normalize_text(value)
    
    if _contains_any(normalized, ["tidak ada", "non pln", "non-pln", "nonpln", "tidak punya rek"]):
        return 1
        
    numbers = [int(num) for num in re.findall(r'\d+', normalized)]
    if not numbers:
        return 2
        
    max_value = max(numbers)
    if max_value <= 0:
        return 1
    elif max_value <= 900:
        return 2
    else:
        return 3

def encode_application_features(raw_data: dict) -> dict:
    """Takes raw application string/number data and encodes it using authoritative rules."""
    
    penghasilan_gabungan = raw_data.get("penghasilan_gabungan_rupiah")
    if penghasilan_gabungan is None or penghasilan_gabungan == "":
        # Fallback to sum of ayah and ibu
        p_ayah = float(raw_data.get("penghasilan_ayah_rupiah") or 0)
        p_ibu = float(raw_data.get("penghasilan_ibu_rupiah") or 0)
        penghasilan_gabungan = p_ayah + p_ibu

    return {
        "kip": normalize_binary(raw_data.get("kip"), "kip"),
        "pkh": normalize_binary(raw_data.get("pkh"), "pkh"),
        "kks": normalize_binary(raw_data.get("kks"), "kks"),
        "dtks": normalize_binary(raw_data.get("dtks"), "dtks"),
        "sktm": normalize_binary(raw_data.get("sktm"), "sktm"),
        "penghasilan_gabungan": encode_income(penghasilan_gabungan, "penghasilan_gabungan_rupiah"),
        "penghasilan_ayah": encode_income(raw_data.get("penghasilan_ayah_rupiah"), "penghasilan_ayah_rupiah"),
        "penghasilan_ibu": encode_income(raw_data.get("penghasilan_ibu_rupiah"), "penghasilan_ibu_rupiah"),
        "jumlah_tanggungan": encode_jumlah_tanggungan(raw_data.get("jumlah_tanggungan_raw")),
        "anak_ke": encode_anak_ke(raw_data.get("anak_ke_raw")),
        "status_orangtua": encode_status_orangtua(raw_data.get("status_orangtua_text")),
        "status_rumah": encode_status_rumah(raw_data.get("status_rumah_text")),
        "daya_listrik": encode_daya_listrik(raw_data.get("daya_listrik_text")),
    }

def validate_encoded_features(payload: dict) -> dict:
    """Validates that a dictionary of already-encoded features has correct ranges (1, 2, 3 or 0, 1)."""
    from config import BINARY_FEATURES, ORDINAL_FEATURES, DB_FEATURE_COLUMNS
    
    values = {}
    for feature in DB_FEATURE_COLUMNS:
        if feature not in payload:
            raise ValueError(f"Field wajib tidak lengkap: {feature}")
        try:
            parsed_value = int(payload[feature])
            if feature in BINARY_FEATURES and parsed_value not in (0, 1):
                raise ValueError(f"Field {feature} wajib bernilai 0 atau 1 (biner). Nilai: {parsed_value}")
            elif feature in ORDINAL_FEATURES and parsed_value not in (1, 2, 3):
                raise ValueError(f"Field {feature} wajib bernilai 1, 2, atau 3 (ordinal). Nilai: {parsed_value}")
            values[feature] = parsed_value
        except (TypeError, ValueError) as exc:
            raise ValueError(f"Nilai fitur {feature} harus berupa angka yang valid") from exc
            
    return values
