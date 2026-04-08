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

        $importedAt = now();
        $inserted = 0;
        $updated = 0;
        $deletedExisting = 0;
        $applicationIds = [];
        $sourceSheetName = null;
        $existingRowsRefreshed = false;
        $preservedHouseStatusByRow = [];
        $preservedHouseStatusCount = 0;

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle)) !== false) {
                if (! $this->rowHasContent($row)) {
                    continue;
                }

                $data = array_combine($header, $row);
                if ($data === false) {
                    continue;
                }

                $sourceSheetName ??= $this->parseNullableString($data['source_sheet_name'] ?? null) ?? 'Verif KIP SNBP 2023';

                if ($refreshExisting && ! $existingRowsRefreshed) {
                    $preservedHouseStatusByRow = $this->loadExistingHouseStatusByRow($sourceSheetName);
                    $deletedExisting = $this->deleteExistingOfflineRows($sourceSheetName);
                    $existingRowsRefreshed = true;
                }

                $sourceRowNumber = $this->parseNullableInt($data['source_row_number'] ?? null) ?? 0;

                $schemaVersion = $this->resolveSchemaVersion($data['schema_version'] ?? null);
                $decision = $this->resolveDecisionStatus(
                    $data['status'] ?? null,
                    $data['admin_decision'] ?? null,
                );
                $fatherIncome = $this->parseNullableInt($data['penghasilan_ayah_rupiah'] ?? null);
                $motherIncome = $this->parseNullableInt($data['penghasilan_ibu_rupiah'] ?? null);
                $combinedIncome = $this->resolveCombinedIncome(
                    $fatherIncome,
                    $motherIncome,
                    $this->parseNullableInt($data['penghasilan_gabungan_rupiah'] ?? null),
                );

                $application = StudentApplication::query()->firstOrNew([
                    'submission_source' => $this->parseNullableString($data['submission_source'] ?? null) ?? 'offline_admin_import',
                    'source_sheet_name' => $sourceSheetName,
                    'source_row_number' => $sourceRowNumber,
                ]);

                $wasExisting = $application->exists;
                $previousStatus = $application->status;
                $incomingHouseStatus = $this->parseNullableString($data['status_rumah_text'] ?? null);
                $resolvedHouseStatus = $this->resolveHouseStatusText(
                    $incomingHouseStatus,
                    $application->status_rumah_text,
                    $preservedHouseStatusByRow[$sourceRowNumber] ?? null,
                );

                if ($resolvedHouseStatus !== $incomingHouseStatus) {
                    $preservedHouseStatusCount++;
                }

                $application->forceFill([
                    'student_user_id' => null,
                    'schema_version' => $schemaVersion,
                    'submission_source' => $this->parseNullableString($data['submission_source'] ?? null) ?? 'offline_admin_import',
                    'applicant_name' => $this->parseNullableString($data['applicant_name'] ?? null),
                    'applicant_email' => $this->parseNullableString($data['applicant_email'] ?? null),
                    'study_program' => $this->parseNullableString($data['study_program'] ?? null),
                    'faculty' => $this->parseNullableString($data['faculty'] ?? null),
                    'source_reference_number' => $this->parseNullableString($data['source_reference_number'] ?? null),
                    'source_document_link' => $this->parseNullableString($data['source_document_link'] ?? null),
                    'source_sheet_name' => $sourceSheetName,
                    'source_row_number' => $sourceRowNumber,
                    'source_label_text' => $this->parseNullableString($data['source_label_text'] ?? null),
                    'imported_at' => $importedAt,
                    'kip' => $this->parseBinaryValue($data['kip'] ?? null, 'kip'),
                    'pkh' => $this->parseBinaryValue($data['pkh'] ?? null, 'pkh'),
                    'kks' => $this->parseBinaryValue($data['kks'] ?? null, 'kks'),
                    'dtks' => $this->parseBinaryValue($data['dtks'] ?? null, 'dtks'),
                    'sktm' => $this->parseBinaryValue($data['sktm'] ?? null, 'sktm'),
                    'penghasilan_gabungan_rupiah' => $combinedIncome,
                    'penghasilan_ayah_rupiah' => $fatherIncome,
                    'penghasilan_ibu_rupiah' => $motherIncome,
                    'jumlah_tanggungan_raw' => $this->parseNullableInt($data['jumlah_tanggungan_raw'] ?? null),
                    'anak_ke_raw' => $this->parseNullableInt($data['anak_ke_raw'] ?? null),
                    'status_orangtua_text' => $this->parseNullableString($data['status_orangtua_text'] ?? null),
                    'status_rumah_text' => $resolvedHouseStatus,
                    'daya_listrik_text' => $this->parseNullableString($data['daya_listrik_text'] ?? null),
                    'submitted_pdf_path' => null,
                    'submitted_pdf_original_name' => null,
                    'submitted_pdf_uploaded_at' => null,
                    'status' => $decision,
                    'admin_decision' => $decision,
                    'admin_decided_by' => null,
                    'admin_decision_note' => $this->parseNullableString($data['admin_decision_note'] ?? null)
                        ?? 'Imported from Verifikasi KIP SNBP 2023.xlsx',
                    'admin_decided_at' => $importedAt,
                ]);
                $application->save();

                ApplicationStatusLog::query()->create([
                    'application_id' => $application->id,
                    'actor_user_id' => null,
                    'from_status' => $wasExisting ? $previousStatus : null,
                    'to_status' => $decision,
                    'action' => $wasExisting ? 'reimported_offline' : 'imported_offline',
                    'note' => 'Imported from cleaned student applicant CSV dataset.',
                    'metadata' => [
                        'source_document_link' => $data['source_document_link'] ?? null,
                        'penghasilan_gabungan_rupiah' => $combinedIncome,
                        'penghasilan_ayah_rupiah' => $fatherIncome,
                        'penghasilan_ibu_rupiah' => $motherIncome,
                        'jumlah_tanggungan_raw' => $this->parseNullableInt($data['jumlah_tanggungan_raw'] ?? null),
                        'anak_ke_raw' => $this->parseNullableInt($data['anak_ke_raw'] ?? null),
                        'status_orangtua_text' => $this->parseNullableString($data['status_orangtua_text'] ?? null),
                        'status_rumah_text' => $resolvedHouseStatus,
                        'source_status_rumah_text' => $incomingHouseStatus,
                        'preserved_existing_house_status' => $resolvedHouseStatus !== $incomingHouseStatus,
                        'daya_listrik_text' => $this->parseNullableString($data['daya_listrik_text'] ?? null),
                        'source_label_text' => $data['source_label_text'] ?? null,
                        'manual_review_required' => $this->parseNullableInt($data['manual_review_required'] ?? null),
                        'manual_house_review' => $this->parseNullableInt($data['manual_house_review'] ?? null),
                        'cleaning_notes' => $this->parseNullableString($data['cleaning_notes'] ?? null),
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
            'preserved_house_status' => $preservedHouseStatusCount,
            'application_ids' => $applicationIds,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function loadExistingHouseStatusByRow(string $sourceSheetName): array
    {
        return StudentApplication::query()
            ->where('submission_source', 'offline_admin_import')
            ->where('source_sheet_name', $sourceSheetName)
            ->whereNotNull('status_rumah_text')
            ->where('status_rumah_text', '!=', '')
            ->pluck('status_rumah_text', 'source_row_number')
            ->all();
    }

    private function deleteExistingOfflineRows(string $sourceSheetName): int
    {
        $query = StudentApplication::query()
            ->where('submission_source', 'offline_admin_import')
            ->where('source_sheet_name', $sourceSheetName);

        return $query->delete();
    }

    private function resolveSchemaVersion(mixed $value): int
    {
        $parsedValue = $this->parseNullableInt($value);

        if ($parsedValue !== null && $parsedValue >= 1) {
            return $parsedValue;
        }

        return (int) (ParameterSchemaVersion::query()->max('version') ?? 1);
    }

    private function resolveDecisionStatus(mixed $status, mixed $adminDecision): string
    {
        $candidates = [
            $this->parseNullableString($adminDecision),
            $this->parseNullableString($status),
        ];

        foreach ($candidates as $candidate) {
            if (in_array($candidate, ['Submitted', 'Verified', 'Rejected'], true)) {
                return $candidate;
            }
        }

        return 'Submitted';
    }

    private function parseBinaryValue(mixed $value, string $field): int
    {
        $parsedValue = $this->parseNullableInt($value);

        if ($parsedValue === null || ! in_array($parsedValue, [0, 1], true)) {
            throw new RuntimeException("Kolom {$field} wajib bernilai 0 atau 1.");
        }

        return $parsedValue;
    }

    /**
     * @param array<int, mixed> $row
     */
    private function rowHasContent(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
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

    private function resolveHouseStatusText(
        ?string $incomingHouseStatus,
        ?string $existingHouseStatus,
        ?string $preservedHouseStatus,
    ): ?string {
        if ($incomingHouseStatus !== null) {
            return $incomingHouseStatus;
        }

        $currentHouseStatus = $this->parseNullableString($existingHouseStatus);
        if ($currentHouseStatus !== null) {
            return $currentHouseStatus;
        }

        return $this->parseNullableString($preservedHouseStatus);
    }

    private function resolveCombinedIncome(?int $fatherIncome, ?int $motherIncome, ?int $fallbackCombined): ?int
    {
        if ($fatherIncome !== null || $motherIncome !== null) {
            return (int) (($fatherIncome ?? 0) + ($motherIncome ?? 0));
        }

        return $fallbackCombined;
    }
}
