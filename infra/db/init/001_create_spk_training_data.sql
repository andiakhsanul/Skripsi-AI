-- Tabel ini menyimpan data training hasil validasi admin Laravel.
CREATE TABLE IF NOT EXISTS spk_training_data (
    id BIGSERIAL PRIMARY KEY,
    kip_sma INTEGER NOT NULL,
    penghasilan_gabungan NUMERIC(14, 2) NOT NULL,
    daya_listrik INTEGER NOT NULL,
    label VARCHAR(30) NOT NULL,
    schema_version INTEGER NOT NULL DEFAULT 1,
    source_application_id BIGINT UNIQUE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT NOW()
);

-- Data awal dummy agar endpoint retrain bisa langsung diuji.
INSERT INTO spk_training_data (kip_sma, penghasilan_gabungan, daya_listrik, label, schema_version, is_active)
VALUES
    (1, 1200000, 900, 'Layak', 1, TRUE),
    (1, 900000, 450, 'Layak', 1, TRUE),
    (0, 4200000, 2200, 'Tidak Layak', 1, TRUE),
    (0, 3500000, 1300, 'Tidak Layak', 1, TRUE)
ON CONFLICT DO NOTHING;
