<?php

namespace App\Services;

/**
 * EncodingService
 *
 * Mengkonversi nilai RAW yang diinput mahasiswa ke dalam kode numerik
 * sesuai aturan penelitian SPK KIP-K.
 *
 * Aturan encoding:
 * --- Variabel Biner (0/1) ---
 * KIP, PKH, KKS, DTKS, SKTM:
 *   Ya  = 1 (Prioritas 1 / paling rentan)
 *   Tidak = 0
 *
 * --- Variabel Ordinal (1/2/3) ---
 * 1 = Prioritas 1 (paling rentan)
 * 3 = Prioritas 3 (kerentanan lebih rendah)
 *
 * Penghasilan Gabungan / Ayah / Ibu (Rupiah):
 *   < 1.000.000       → 1
 *   1.000.000 – 3.999.999 → 2
 *   ≥ 4.000.000       → 3
 *
 * Jumlah Tanggungan (orang):
 *   ≥ 6   → 1
 *   4–5   → 2
 *   0–3   → 3
 *
 * Anak Ke- (urutan):
 *   ≥ 5   → 1
 *   3–4   → 2
 *   1–2   → 3
 *
 * Status Orang Tua:
 *   Yatim Piatu → 1
 *   Yatim / Piatu → 2
 *   Lengkap     → 3
 *
 * Status Rumah:
 *   Tidak Punya → 1
 *   Sewa / Menumpang → 2
 *   Milik Sendiri → 3
 *
 * Daya Listrik:
 *   Tidak Ada / Non-PLN → 1
 *   PLN 450–900 VA → 2
 *   PLN > 900 VA  → 3
 */
class EncodingService
{
    /**
     * Encode semua fitur dari raw input mahasiswa ke nilai numerik.
     *
     * @param array<string, mixed> $raw
     * @return array<string, int>
     */
    public function encode(array $raw): array
    {
        return [
            'kip'  => $this->encodeBinary($raw['kip'] ?? false),
            'pkh'  => $this->encodeBinary($raw['pkh'] ?? false),
            'kks'  => $this->encodeBinary($raw['kks'] ?? false),
            'dtks' => $this->encodeBinary($raw['dtks'] ?? false),
            'sktm' => $this->encodeBinary($raw['sktm'] ?? false),

            'penghasilan_gabungan' => $this->encodeIncome($raw['penghasilan_gabungan_raw'] ?? 0),
            'penghasilan_ayah'     => $this->encodeIncome($raw['penghasilan_ayah_raw'] ?? 0),
            'penghasilan_ibu'      => $this->encodeIncome($raw['penghasilan_ibu_raw'] ?? 0),

            'jumlah_tanggungan' => $this->encodeDependents((int) ($raw['jumlah_tanggungan_raw'] ?? 0)),
            'anak_ke'           => $this->encodeChildOrder((int) ($raw['anak_ke_raw'] ?? 1)),

            'status_orangtua' => $this->encodeParentStatus((string) ($raw['status_orangtua_raw'] ?? '')),
            'status_rumah'    => $this->encodeHousingStatus((string) ($raw['status_rumah_raw'] ?? '')),
            'daya_listrik'    => $this->encodePower((string) ($raw['daya_listrik_raw'] ?? '')),
        ];
    }

    /**
     * Encode nilai biner: Ya=1, Tidak=0.
     */
    public function encodeBinary(mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'ya', 'yes', 'iya'], true) ? 1 : 0;
    }

    /**
     * Encode penghasilan (Rupiah) → 1/2/3.
     */
    public function encodeIncome(mixed $amount): int
    {
        $value = (int) $amount;

        if ($value < 1_000_000) {
            return 1;
        }

        if ($value < 4_000_000) {
            return 2;
        }

        return 3;
    }

    /**
     * Encode jumlah tanggungan (orang) → 1/2/3.
     */
    public function encodeDependents(int $count): int
    {
        if ($count >= 6) {
            return 1;
        }

        if ($count >= 4) {
            return 2;
        }

        return 3;
    }

    /**
     * Encode urutan anak → 1/2/3.
     */
    public function encodeChildOrder(int $order): int
    {
        if ($order >= 5) {
            return 1;
        }

        if ($order >= 3) {
            return 2;
        }

        return 3;
    }

    /**
     * Encode status orang tua → 1/2/3.
     * Yatim Piatu = 1, Yatim atau Piatu = 2, Lengkap = 3.
     */
    public function encodeParentStatus(string $status): int
    {
        $normalized = strtolower(trim($status));

        if (str_contains($normalized, 'yatim piatu') || $normalized === 'yatim_piatu') {
            return 1;
        }

        if (str_contains($normalized, 'yatim') || str_contains($normalized, 'piatu')) {
            return 2;
        }

        // Lengkap atau default
        return 3;
    }

    /**
     * Encode status rumah → 1/2/3.
     * Tidak Punya = 1, Sewa/Menumpang = 2, Milik Sendiri = 3.
     */
    public function encodeHousingStatus(string $status): int
    {
        $normalized = strtolower(trim($status));

        $noHome = ['tidak punya', 'tidak_punya', 'tidak memiliki', 'tidak ada'];
        $rental = ['sewa', 'menumpang', 'kontrak', 'kos', 'numpang'];

        foreach ($noHome as $match) {
            if (str_contains($normalized, $match)) {
                return 1;
            }
        }

        foreach ($rental as $match) {
            if (str_contains($normalized, $match)) {
                return 2;
            }
        }

        // Milik sendiri / default
        return 3;
    }

    /**
     * Encode daya listrik → 1/2/3.
     * Tidak Ada/Non-PLN = 1, PLN 450–900 VA = 2, PLN >900 VA = 3.
     */
    public function encodePower(string $daya): int
    {
        $normalized = strtolower(trim($daya));

        $noElec = ['tidak ada', 'tidak_ada', 'non-pln', 'non pln', 'tanpa listrik', '0'];
        $low = ['450', '900', '450-900', '450va', '900va', 'rendah'];

        foreach ($noElec as $match) {
            if (str_contains($normalized, $match)) {
                return 1;
            }
        }

        foreach ($low as $match) {
            if (str_contains($normalized, $match)) {
                return 2;
            }
        }

        // PLN >900 VA / default
        return 3;
    }

    /**
     * Daftar opsi dropdown untuk frontend — menampilkan pilihan human-readable.
     *
     * @return array<string, array<array{value: string, label: string}>>
     */
    public function getDropdownOptions(): array
    {
        return [
            'status_orangtua' => [
                ['value' => 'Lengkap', 'label' => 'Lengkap (Ayah & Ibu masih ada)'],
                ['value' => 'Yatim', 'label' => 'Yatim (Ayah telah meninggal)'],
                ['value' => 'Piatu', 'label' => 'Piatu (Ibu telah meninggal)'],
                ['value' => 'Yatim Piatu', 'label' => 'Yatim Piatu (Keduanya telah meninggal)'],
            ],
            'status_rumah' => [
                ['value' => 'Milik Sendiri', 'label' => 'Milik Sendiri'],
                ['value' => 'Sewa', 'label' => 'Sewa / Kontrak'],
                ['value' => 'Menumpang', 'label' => 'Menumpang (di rumah keluarga/orang lain)'],
                ['value' => 'Tidak Punya', 'label' => 'Tidak Memiliki Rumah'],
            ],
            'daya_listrik' => [
                ['value' => 'PLN >900VA', 'label' => 'PLN di atas 900 VA'],
                ['value' => 'PLN 450-900VA', 'label' => 'PLN 450 VA atau 900 VA'],
                ['value' => 'Non-PLN', 'label' => 'Tidak Ada Listrik / Non-PLN'],
            ],
        ];
    }
}
