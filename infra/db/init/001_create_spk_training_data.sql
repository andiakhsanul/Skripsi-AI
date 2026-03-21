-- Tabel ini menyimpan data training hasil validasi admin Laravel.
CREATE TABLE IF NOT EXISTS spk_training_data (
    id BIGSERIAL PRIMARY KEY,
    kip_sma INTEGER,
    kip INTEGER NOT NULL DEFAULT 0,
    pkh INTEGER NOT NULL DEFAULT 0,
    kks INTEGER NOT NULL DEFAULT 0,
    dtks INTEGER NOT NULL DEFAULT 0,
    sktm INTEGER NOT NULL DEFAULT 0,
    penghasilan_gabungan INTEGER NOT NULL,
    penghasilan_ayah INTEGER NOT NULL DEFAULT 3,
    penghasilan_ibu INTEGER NOT NULL DEFAULT 3,
    jumlah_tanggungan INTEGER NOT NULL DEFAULT 3,
    anak_ke INTEGER NOT NULL DEFAULT 3,
    status_orangtua INTEGER NOT NULL DEFAULT 3,
    status_rumah INTEGER NOT NULL DEFAULT 3,
    daya_listrik INTEGER NOT NULL,
    label VARCHAR(30) NOT NULL,
    label_class INTEGER,
    schema_version INTEGER NOT NULL DEFAULT 1,
    source_application_id BIGINT UNIQUE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT NOW()
);

-- Data awal dummy agar endpoint retrain bisa langsung diuji.
INSERT INTO spk_training_data (
    kip_sma, kip, pkh, kks, dtks, sktm,
    penghasilan_gabungan, penghasilan_ayah, penghasilan_ibu,
    jumlah_tanggungan, anak_ke, status_orangtua, status_rumah,
    daya_listrik, label, label_class, schema_version, is_active
)
VALUES
    (1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 'Layak', 0, 1, TRUE),
    (1, 1, 0, 1, 1, 1, 1, 2, 2, 2, 3, 2, 2, 2, 'Layak', 0, 1, TRUE),
    (0, 0, 0, 0, 0, 0, 3, 3, 3, 3, 1, 3, 3, 3, 'Indikasi', 1, 1, TRUE),
    (0, 0, 0, 0, 0, 0, 2, 3, 3, 3, 2, 3, 3, 3, 'Indikasi', 1, 1, TRUE)
ON CONFLICT DO NOTHING;
