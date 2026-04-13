<?php

namespace App\Services;

use App\Models\ApplicationFeatureEncoding;
use App\Models\StudentApplication;
use Illuminate\Validation\ValidationException;

class ApplicationFeatureEncodingService
{
    public function syncFromApplication(
        StudentApplication $application,
        ?int $encodedByUserId = null,
        int $encodingVersion = 1,
    ): ApplicationFeatureEncoding {
        $features = $this->encodeApplication($application);

        ApplicationFeatureEncoding::query()
            ->where('application_id', $application->id)
            ->where('is_current', true)
            ->where(function ($query) use ($application, $encodingVersion): void {
                $query
                    ->where('schema_version', '!=', $application->schema_version)
                    ->orWhere('encoding_version', '!=', $encodingVersion);
            })
            ->update(['is_current' => false]);

        return ApplicationFeatureEncoding::query()->updateOrCreate(
            [
                'application_id' => $application->id,
                'schema_version' => $application->schema_version,
                'encoding_version' => $encodingVersion,
            ],
            [
                'encoded_by_user_id' => $encodedByUserId,
                'is_current' => true,
                'validation_errors' => null,
                ...$features,
                'encoded_at' => now(),
            ],
        );
    }

    /**
     * @return array<string, int>
     */
    public function encodeApplication(StudentApplication $application): array
    {
        // Hitung penghasilan_gabungan: gunakan nilai eksplisit, fallback ke ayah + ibu
        $penghasilanGabungan = $application->penghasilan_gabungan_rupiah;
        if ($penghasilanGabungan === null || $penghasilanGabungan === '') {
            $penghasilanGabungan = ((int) ($application->penghasilan_ayah_rupiah ?? 0))
                + ((int) ($application->penghasilan_ibu_rupiah ?? 0));
        }

        return [
            'kip' => $this->normalizeBinary($application->kip, 'kip'),
            'pkh' => $this->normalizeBinary($application->pkh, 'pkh'),
            'kks' => $this->normalizeBinary($application->kks, 'kks'),
            'dtks' => $this->normalizeBinary($application->dtks, 'dtks'),
            'sktm' => $this->normalizeBinary($application->sktm, 'sktm'),
            'penghasilan_gabungan' => $this->encodeIncome($penghasilanGabungan, 'penghasilan_gabungan_rupiah'),
            'penghasilan_ayah' => $this->encodeIncome($application->penghasilan_ayah_rupiah, 'penghasilan_ayah_rupiah'),
            'penghasilan_ibu' => $this->encodeIncome($application->penghasilan_ibu_rupiah, 'penghasilan_ibu_rupiah'),
            'jumlah_tanggungan' => $this->encodeJumlahTanggungan($application->jumlah_tanggungan_raw),
            'anak_ke' => $this->encodeAnakKe($application->anak_ke_raw),
            'status_orangtua' => $this->encodeStatusOrangtua($application->status_orangtua_text),
            'status_rumah' => $this->encodeStatusRumah($application->status_rumah_text),
            'daya_listrik' => $this->encodeDayaListrik($application->daya_listrik_text),
        ];
    }

    private function normalizeBinary(mixed $value, string $field): int
    {
        if ($value === null || ! in_array((int) $value, [0, 1], true)) {
            throw ValidationException::withMessages([
                $field => ["{$field} wajib bernilai 0 atau 1."],
            ]);
        }

        return (int) $value;
    }

    private function encodeIncome(mixed $value, string $field): int
    {
        // NULL penghasilan → fallback ke 0 (orangtua tidak berpenghasilan/tidak diketahui)
        if ($value === null || $value === '' || ! is_numeric($value)) {
            $value = 0;
        }

        $income = (int) $value;

        if ($income === 0) {
            return 1;
        }

        return match (true) {
            $income < 1_000_000 => 2,
            $income < 2_000_000 => 3,
            $income < 4_000_000 => 4,
            default => 5,
        };
    }

    private function encodeJumlahTanggungan(mixed $value): int
    {
        if ($value === null || ! is_numeric($value)) {
            throw ValidationException::withMessages([
                'jumlah_tanggungan_raw' => ['jumlah_tanggungan_raw wajib berupa angka.'],
            ]);
        }

        $dependents = (int) $value;

        return match (true) {
            $dependents >= 6 => 1,
            $dependents >= 5 => 2,
            $dependents >= 4 => 3,
            $dependents >= 2 => 4,
            default => 5,
        };
    }

    private function encodeAnakKe(mixed $value): int
    {
        // NULL atau 0 → fallback ke 3 (tengah-tengah)
        if ($value === null || ! is_numeric($value) || (int) $value <= 0) {
            return 3;
        }

        $childOrder = (int) $value;

        return match (true) {
            $childOrder >= 5 => 1,
            $childOrder === 4 => 2,
            $childOrder === 3 => 3,
            $childOrder === 2 => 4,
            default => 5,
        };
    }

    private function encodeStatusOrangtua(?string $value): int
    {
        $normalized = $this->normalizeText($value, 'status_orangtua_text');

        // ── 1 = Yatim Piatu (kedua orangtua meninggal/wafat) ──
        if (str_contains($normalized, 'yatim piatu')) {
            return 1;
        }

        // Deteksi status ayah
        $fatherDeceased = $this->containsAny($normalized, [
            'ayah=wafat', 'ayah=meninggal', 'ayah meninggal', 'ayah wafat',
            'ayah=meninggal dunia',
        ]);
        $motherDeceased = $this->containsAny($normalized, [
            'ibu=wafat', 'ibu=meninggal', 'ibu meninggal', 'ibu wafat',
            'ibu=wafar', 'ibu=meninggal dunia',
        ]);

        if ($fatherDeceased && $motherDeceased) {
            return 1;
        }

        // ── 2 = Keluarga tidak lengkap (yatim/piatu/cerai/tiri/wali/tidak jelas) ──
        if (str_contains($normalized, 'yatim') || str_contains($normalized, 'piatu')) {
            return 2;
        }

        if ($fatherDeceased || $motherDeceased) {
            return 2;
        }

        // Cerai (cerai hidup, cerai mati, dll) → keluarga tidak lengkap
        if (str_contains($normalized, 'cerai')) {
            return 2;
        }

        // Tiri, Wali → keluarga tidak lengkap secara biologis
        if ($this->containsAny($normalized, ['tiri', 'wali'])) {
            return 2;
        }

        // Tidak jelas, kosong di salah satu sisi → asumsi tidak lengkap
        if ($this->containsAny($normalized, ['tidak jelas', 'ayah=;', 'ibu=;'])) {
            return 2;
        }

        // Ayah atau ibu kosong (misal "ayah=; ibu=Hidup")
        if (preg_match('/ayah=\s*;/', $normalized) || preg_match('/ibu=\s*;/', $normalized)) {
            return 2;
        }

        // ── 3 = Lengkap (kedua orangtua hidup) ──
        if ($this->containsAny($normalized, ['ayah=hidup', 'ibu=hidup', 'lengkap', 'orang tua lengkap'])) {
            return 3;
        }

        // Fallback: jika tidak bisa dipetakan, asumsi tidak lengkap
        // untuk menghindari data loss pada training
        return 2;
    }

    private function encodeStatusRumah(?string $value): int
    {
        $normalized = $this->normalizeText($value, 'status_rumah_text');

        if ($this->containsAny($normalized, ['tidak memiliki', 'tidak punya rumah'])) {
            return 1;
        }

        if ($this->containsAny($normalized, ['sewa / menumpang'])) {
            return 3;
        }

        if ($this->containsAny($normalized, ['menumpang'])) {
            return 2;
        }

        if ($this->containsAny($normalized, ['sewa', 'kontrak'])) {
            return 3;
        }

        if ($this->containsAny($normalized, ['milik sendiri', 'rumah sendiri', 'sendiri', 'punya pribadi', 'punya sendiri', 'milik pribadi'])) {
            return 4;
        }

        return 2;
    }

    private function encodeDayaListrik(?string $value): int
    {
        $normalized = $this->normalizeText($value, 'daya_listrik_text');

        // Tidak ada listrik / non-PLN
        if ($this->containsAny($normalized, ['tidak ada', 'non pln', 'non-pln', 'nonpln', 'tidak punya rek'])) {
            return 1;
        }

        // Cari angka daya di teks
        preg_match_all('/\d+/', $normalized, $matches);
        $numbers = array_map(static fn (string $number): int => (int) $number, $matches[0] ?? []);

        if ($numbers === []) {
            // Tidak ada angka → fallback ke 2 (daya rendah/subsidi)
            // Kasus: 'Token', 'unknown', '-', 'tidak ada ket', dll.
            return 2;
        }

        $maxValue = max($numbers);

        return match (true) {
            $maxValue <= 0 => 1,
            $maxValue <= 450 => 2,
            $maxValue <= 900 => 3,
            $maxValue <= 1300 => 4,
            default => 5,
        };
    }

    private function normalizeText(?string $value, string $field): string
    {
        $normalized = mb_strtolower(trim((string) $value));
        $normalized = preg_replace('/\s+/', ' ', $normalized ?? '');

        if ($normalized === null || $normalized === '') {
            throw ValidationException::withMessages([
                $field => ["{$field} wajib diisi."],
            ]);
        }

        return $normalized;
    }

    /**
     * @param list<string> $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
