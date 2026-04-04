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
        return [
            'kip' => $this->normalizeBinary($application->kip, 'kip'),
            'pkh' => $this->normalizeBinary($application->pkh, 'pkh'),
            'kks' => $this->normalizeBinary($application->kks, 'kks'),
            'dtks' => $this->normalizeBinary($application->dtks, 'dtks'),
            'sktm' => $this->normalizeBinary($application->sktm, 'sktm'),
            'penghasilan_gabungan' => $this->encodeIncome($application->penghasilan_gabungan_rupiah, 'penghasilan_gabungan_rupiah'),
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
        if ($value === null || ! is_numeric($value)) {
            throw ValidationException::withMessages([
                $field => ["{$field} wajib berupa nominal rupiah yang valid."],
            ]);
        }

        $income = (int) $value;

        return match (true) {
            $income < 1_000_000 => 1,
            $income < 4_000_000 => 2,
            default => 3,
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
            $dependents >= 4 => 2,
            default => 3,
        };
    }

    private function encodeAnakKe(mixed $value): int
    {
        if ($value === null || ! is_numeric($value) || (int) $value < 1) {
            throw ValidationException::withMessages([
                'anak_ke_raw' => ['anak_ke_raw wajib berupa angka minimal 1.'],
            ]);
        }

        $childOrder = (int) $value;

        return match (true) {
            $childOrder >= 5 => 1,
            $childOrder >= 3 => 2,
            default => 3,
        };
    }

    private function encodeStatusOrangtua(?string $value): int
    {
        $normalized = $this->normalizeText($value, 'status_orangtua_text');

        if (str_contains($normalized, 'yatim piatu')) {
            return 1;
        }

        if (str_contains($normalized, 'yatim') || str_contains($normalized, 'piatu')) {
            return 2;
        }

        $fatherDeceased = $this->containsAny($normalized, ['ayah=wafat', 'ayah=meninggal', 'ayah meninggal', 'ayah wafat']);
        $motherDeceased = $this->containsAny($normalized, ['ibu=wafat', 'ibu=meninggal', 'ibu meninggal', 'ibu wafat', 'ibu=wafar']);

        if ($fatherDeceased && $motherDeceased) {
            return 1;
        }

        if ($fatherDeceased || $motherDeceased) {
            return 2;
        }

        if ($this->containsAny($normalized, ['ayah=hidup', 'ibu=hidup', 'lengkap', 'orang tua lengkap'])) {
            return 3;
        }

        throw ValidationException::withMessages([
            'status_orangtua_text' => ['status_orangtua_text tidak bisa dipetakan ke kategori encoding.'],
        ]);
    }

    private function encodeStatusRumah(?string $value): int
    {
        $normalized = $this->normalizeText($value, 'status_rumah_text');

        if ($this->containsAny($normalized, ['tidak memiliki', 'tidak punya rumah'])) {
            return 1;
        }

        if ($this->containsAny($normalized, ['sewa', 'kontrak', 'menumpang', 'menempati', 'bukan milik sendiri'])) {
            return 2;
        }

        if ($this->containsAny($normalized, ['milik sendiri', 'rumah sendiri', 'sendiri', 'punya pribadi', 'punya sendiri', 'milik pribadi'])) {
            return 3;
        }

        throw ValidationException::withMessages([
            'status_rumah_text' => ['status_rumah_text tidak bisa dipetakan ke kategori encoding.'],
        ]);
    }

    private function encodeDayaListrik(?string $value): int
    {
        $normalized = $this->normalizeText($value, 'daya_listrik_text');

        if ($this->containsAny($normalized, ['tidak ada', 'non pln', 'non-pln', 'nonpln'])) {
            return 1;
        }

        preg_match_all('/\d+/', $normalized, $matches);
        $numbers = array_map(static fn (string $number): int => (int) $number, $matches[0] ?? []);

        if ($numbers === []) {
            throw ValidationException::withMessages([
                'daya_listrik_text' => ['daya_listrik_text tidak mengandung nilai daya yang valid.'],
            ]);
        }

        $maxValue = max($numbers);

        return match (true) {
            $maxValue <= 0 => 1,
            $maxValue <= 900 => 2,
            default => 3,
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
