<?php

namespace App\Services\OfflineImport;

use App\Models\ApplicationStatusLog;
use App\Models\ParameterSchemaVersion;
use App\Models\StudentApplication;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CsvStudentApplicationImporter
{
    /**
     * @return array<string, mixed>
     */
    public function import(string $csvPath, bool $refreshExisting = false): array
    {
        if (! is_file($csvPath)) {
            throw new RuntimeException("CSV tidak ditemukan: {$csvPath}");
        }

        $handle = fopen($csvPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('CSV tidak bisa dibuka untuk proses import.');
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            throw new RuntimeException('CSV kosong atau header tidak terbaca.');
        }

        $schemaVersion = (int) (ParameterSchemaVersion::query()->max('version') ?? 1);
        $importedAt = now();
        $sourceSheetName = 'Verif KIP SNBP 2023';
        $inserted = 0;
        $updated = 0;
        $deletedExisting = 0;
        $applicationIds = [];

        DB::beginTransaction();

        try {
            if ($refreshExisting) {
                $deletedExisting = $this->deleteExistingOfflineRows($sourceSheetName);
            }

            while (($row = fgetcsv($handle)) !== false) {
                $data = array_combine($header, $row);
                if ($data === false) {
                    continue;
                }

                $decision = ($data['label'] ?? '') === 'Layak' ? 'Verified' : 'Rejected';
                $fatherIncome = $this->parseNullableInt($data['penghasilan_ayah_raw'] ?? null);
                $motherIncome = $this->parseNullableInt($data['penghasilan_ibu_raw'] ?? null);
                $combinedIncome = $this->resolveCombinedIncome(
                    $fatherIncome,
                    $motherIncome,
                    $this->parseNullableInt($data['penghasilan_gabungan_raw'] ?? null),
                );

                $application = StudentApplication::query()->firstOrNew([
                    'submission_source' => 'offline_admin_import',
                    'source_sheet_name' => $sourceSheetName,
                    'source_row_number' => (int) ($data['row_number'] ?? 0),
                ]);

                $wasExisting = $application->exists;
                $previousStatus = $application->status;

                $application->forceFill([
                    'student_user_id' => null,
                    'schema_version' => $schemaVersion,
                    'submission_source' => 'offline_admin_import',
                    'applicant_name' => $data['nama_mahasiswa'] ?: null,
                    'applicant_email' => null,
                    'study_program' => $data['prodi'] ?: null,
                    'faculty' => $data['fakultas'] ?: null,
                    'source_reference_number' => $data['no_urut_web'] ?: null,
                    'source_document_link' => $data['berkas_link'] ?: null,
                    'source_sheet_name' => $sourceSheetName,
                    'source_row_number' => (int) ($data['row_number'] ?? 0),
                    'source_label_text' => $data['raw_kesimpulan'] ?: null,
                    'imported_at' => $importedAt,
                    'kip' => (int) $data['kip'],
                    'pkh' => (int) $data['pkh'],
                    'kks' => (int) $data['kks'],
                    'dtks' => (int) $data['dtks'],
                    'sktm' => (int) $data['sktm'],
                    'penghasilan_gabungan_rupiah' => $combinedIncome,
                    'penghasilan_ayah_rupiah' => $fatherIncome,
                    'penghasilan_ibu_rupiah' => $motherIncome,
                    'jumlah_tanggungan_raw' => $this->parseNullableInt($data['jumlah_tanggungan_raw'] ?? null),
                    'anak_ke_raw' => $this->parseNullableInt($data['anak_ke_raw'] ?? null),
                    'status_orangtua_text' => $this->parseNullableString($data['status_orangtua_raw'] ?? null),
                    'status_rumah_text' => $this->parseNullableString($data['status_rumah_raw'] ?? null),
                    'daya_listrik_text' => $this->parseNullableString($data['daya_listrik_raw'] ?? null),
                    'submitted_pdf_path' => null,
                    'submitted_pdf_original_name' => null,
                    'submitted_pdf_uploaded_at' => null,
                    'status' => $decision,
                    'admin_decision' => $decision,
                    'admin_decided_by' => null,
                    'admin_decision_note' => 'Imported from Verifikasi KIP SNBP 2023.xlsx',
                    'admin_decided_at' => $importedAt,
                ]);
                $application->save();

                ApplicationStatusLog::query()->create([
                    'application_id' => $application->id,
                    'actor_user_id' => null,
                    'from_status' => $wasExisting ? $previousStatus : null,
                    'to_status' => $decision,
                    'action' => $wasExisting ? 'reimported_offline' : 'imported_offline',
                    'note' => 'Imported from processed KIP SNBP CSV dataset.',
                    'metadata' => [
                        'label' => $data['label'] ?? null,
                        'label_class' => isset($data['label_class']) ? (int) $data['label_class'] : null,
                        'source_document_link' => $data['berkas_link'] ?? null,
                        'penghasilan_gabungan_rupiah' => $combinedIncome,
                        'penghasilan_ayah_rupiah' => $fatherIncome,
                        'penghasilan_ibu_rupiah' => $motherIncome,
                        'jumlah_tanggungan_raw' => $this->parseNullableInt($data['jumlah_tanggungan_raw'] ?? null),
                        'anak_ke_raw' => $this->parseNullableInt($data['anak_ke_raw'] ?? null),
                        'status_orangtua_text' => $this->parseNullableString($data['status_orangtua_raw'] ?? null),
                        'status_rumah_text' => $this->parseNullableString($data['status_rumah_raw'] ?? null),
                        'daya_listrik_text' => $this->parseNullableString($data['daya_listrik_raw'] ?? null),
                        'raw_kesimpulan' => $data['raw_kesimpulan'] ?? null,
                    ],
                ]);

                if ($wasExisting) {
                    $updated++;
                } else {
                    $inserted++;
                }

                $applicationIds[] = $application->id;
            }

            fclose($handle);
            DB::commit();
        } catch (\Throwable $throwable) {
            fclose($handle);
            DB::rollBack();
            throw $throwable;
        }

        return [
            'schema_version' => $schemaVersion,
            'deleted_existing' => $deletedExisting,
            'inserted' => $inserted,
            'updated' => $updated,
            'total_processed' => $inserted + $updated,
            'application_ids' => $applicationIds,
        ];
    }

    private function deleteExistingOfflineRows(string $sourceSheetName): int
    {
        $query = StudentApplication::query()
            ->where('submission_source', 'offline_admin_import')
            ->where('source_sheet_name', $sourceSheetName);

        $applicationIds = $query->pluck('id');

        if ($applicationIds->isEmpty()) {
            return 0;
        }

        DB::table('spk_training_data')
            ->whereIn('source_application_id', $applicationIds->all())
            ->delete();

        return $query->delete();
    }

    private function parseNullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $stringValue = trim((string) $value);
        if ($stringValue === '') {
            return null;
        }

        return (int) preg_replace('/[^0-9]/', '', $stringValue);
    }

    private function parseNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = trim((string) $value);

        return $stringValue === '' ? null : $stringValue;
    }

    private function resolveCombinedIncome(?int $fatherIncome, ?int $motherIncome, ?int $fallbackCombined): ?int
    {
        if ($fatherIncome !== null || $motherIncome !== null) {
            return (int) (($fatherIncome ?? 0) + ($motherIncome ?? 0));
        }

        return $fallbackCombined;
    }
}
