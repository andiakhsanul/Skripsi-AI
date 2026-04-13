<?php

namespace App\Services;

use App\Models\ApplicationStatusLog;
use App\Models\StudentApplication;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AdminHouseStatusReviewService
{
    /**
     * @return list<string>
     */
    private function requiredRawFields(): array
    {
        return [
            'penghasilan_ayah_rupiah',
            'penghasilan_ibu_rupiah',
            'penghasilan_gabungan_rupiah',
            'jumlah_tanggungan_raw',
            'anak_ke_raw',
            'status_orangtua_text',
            'status_rumah_text',
            'daya_listrik_text',
        ];
    }

    /**
     * @return list<string>
     */
    public function houseStatusOptions(): array
    {
        return [
            'Tidak memiliki',
            'Sewa',
            'Menumpang',
            'Milik sendiri',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function binaryOptions(): array
    {
        return [
            0 => 'Tidak',
            1 => 'Ya',
        ];
    }

    /**
     * @return array<string, int>
     */
    public function summary(): array
    {
        $baseQuery = StudentApplication::query()
            ->where('submission_source', 'offline_admin_import');

        $total = (clone $baseQuery)->count();
        $pending = $this->applyIncompleteRawDataScope(clone $baseQuery)->count();

        return [
            'total_offline' => $total,
            'pending_house_review' => $pending,
            'completed_house_review' => max(0, $total - $pending),
        ];
    }

    /**
     * @param array<string, mixed> $summary
     * @return array<string, mixed>
     */
    public function viewPayload(array $summary): array
    {
        return [
            'summary_cards' => [
                [
                    'label' => 'Data Import Offline',
                    'value' => number_format($summary['total_offline']),
                    'hint' => 'Total applicant hasil impor admin',
                    'tone' => 'bg-primary-container text-primary',
                    'icon' => 'dataset',
                ],
                [
                    'label' => 'Perlu Dilengkapi',
                    'value' => number_format($summary['pending_house_review']),
                    'hint' => 'Ada data mentah wajib yang masih kosong',
                    'tone' => 'bg-error-container text-error',
                    'icon' => 'edit_note',
                ],
                [
                    'label' => 'Sudah Lengkap',
                    'value' => number_format($summary['completed_house_review']),
                    'hint' => 'Siap dipakai ke langkah encoding berikutnya',
                    'tone' => 'bg-emerald-50 text-emerald-700',
                    'icon' => 'task_alt',
                ],
            ],
            'filter_options' => [
                '' => 'Semua data',
                'missing' => 'Perlu dilengkapi',
                'filled' => 'Data wajib lengkap',
            ],
            'binary_options' => $this->binaryOptions(),
            'house_status_options' => $this->houseStatusOptions(),
            'guides' => [
                'Halaman ini hanya memperbaiki data mentah, belum menyentuh data training.',
                'Lengkapi kartu pendukung, penghasilan, tanggungan, status orang tua, rumah, dan listrik berdasarkan dokumen pendukung.',
                'Draft isian disimpan di browser. Jika session habis sebelum submit, nilai yang belum tersimpan akan dipulihkan saat halaman dibuka lagi.',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function paginateApplications(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return StudentApplication::query()
            ->with([
                'modelSnapshot:id,application_id',
                'currentEncoding:id,application_id,is_current',
                'trainingRows:id,source_application_id',
            ])
            ->where('submission_source', 'offline_admin_import')
            ->when(
                $filters['q'] ?? null,
                function (Builder $query, string $term): void {
                    $query->where(function (Builder $scopedQuery) use ($term): void {
                        $scopedQuery
                            ->where('applicant_name', 'like', "%{$term}%")
                            ->orWhere('study_program', 'like', "%{$term}%")
                            ->orWhere('faculty', 'like', "%{$term}%")
                            ->orWhere('source_reference_number', 'like', "%{$term}%");
                    });
                }
            )
            ->when(
                ($filters['house_state'] ?? '') === 'missing',
                fn (Builder $query) => $this->applyIncompleteRawDataScope($query)
            )
            ->when(
                ($filters['house_state'] ?? '') === 'filled',
                fn (Builder $query) => $this->applyCompleteRawDataScope($query)
            )
            ->orderByRaw($this->missingSortExpression())
            ->orderBy('source_row_number')
            ->paginate($perPage)
            ->withQueryString();
    }

    private function applyIncompleteRawDataScope(Builder $query): Builder
    {
        return $query->where(function (Builder $scopedQuery): void {
            foreach ($this->requiredRawFields() as $field) {
                $scopedQuery->orWhereNull($field);

                if (str_ends_with($field, '_text')) {
                    $scopedQuery->orWhere($field, '');
                }
            }
        });
    }

    private function applyCompleteRawDataScope(Builder $query): Builder
    {
        foreach ($this->requiredRawFields() as $field) {
            $query->whereNotNull($field);

            if (str_ends_with($field, '_text')) {
                $query->where($field, '!=', '');
            }
        }

        return $query;
    }

    private function missingSortExpression(): string
    {
        return collect($this->requiredRawFields())
            ->map(function (string $field): string {
                if (str_ends_with($field, '_text')) {
                    return "CASE WHEN {$field} IS NULL OR {$field} = '' THEN 0 ELSE 1 END";
                }

                return "CASE WHEN {$field} IS NULL THEN 0 ELSE 1 END";
            })
            ->implode(' + ');
    }

    public function updateHouseStatus(StudentApplication $application, ?string $statusRumahText, int $actorUserId): StudentApplication
    {
        return $this->applyHouseStatusUpdate($application, $statusRumahText, $actorUserId)['application'];
    }

    /**
     * @param  array<int, array<string, mixed>>  $updates
     * @return array{submitted:int, updated:int, unchanged:int, missing:int, cleared_training_rows:int, cleared_encodings:int, cleared_snapshots:int}
     */
    public function batchUpdateRawData(array $updates, int $actorUserId): array
    {
        $applicationIds = collect($updates)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $applications = StudentApplication::query()
            ->where('submission_source', 'offline_admin_import')
            ->whereIn('id', $applicationIds)
            ->get()
            ->keyBy('id');

        $summary = [
            'submitted' => count($updates),
            'updated' => 0,
            'unchanged' => 0,
            'missing' => 0,
            'cleared_training_rows' => 0,
            'cleared_encodings' => 0,
            'cleared_snapshots' => 0,
        ];

        foreach ($updates as $update) {
            $application = $applications->get((int) $update['id']);

            if (! $application instanceof StudentApplication) {
                $summary['missing']++;
                continue;
            }

            $result = $this->applyHouseStatusUpdate(
                $application,
                $update,
                $actorUserId,
            );

            if (! $result['changed']) {
                $summary['unchanged']++;
                continue;
            }

            $summary['updated']++;
            $summary['cleared_training_rows'] += $result['deleted_training_rows'];
            $summary['cleared_encodings'] += $result['deleted_encodings'];
            $summary['cleared_snapshots'] += $result['deleted_snapshot'] ? 1 : 0;
        }

        return $summary;
    }

    /**
     * @return array{application:StudentApplication, changed:bool, deleted_training_rows:int, deleted_encodings:int, deleted_snapshot:bool}
     */
    private function applyHouseStatusUpdate(StudentApplication $application, array|string|null $payload, int $actorUserId): array
    {
        $newValues = is_array($payload)
            ? $this->normalizeRawDataPayload($payload)
            : ['status_rumah_text' => blank($payload) ? null : trim((string) $payload)];

        $oldValues = [];
        foreach (array_keys($newValues) as $field) {
            $oldValues[$field] = $application->{$field};
        }

        if (array_key_exists('penghasilan_ayah_rupiah', $newValues) || array_key_exists('penghasilan_ibu_rupiah', $newValues)) {
            $fatherIncome = $newValues['penghasilan_ayah_rupiah'] ?? $application->penghasilan_ayah_rupiah;
            $motherIncome = $newValues['penghasilan_ibu_rupiah'] ?? $application->penghasilan_ibu_rupiah;
            $newValues['penghasilan_gabungan_rupiah'] = $fatherIncome !== null && $motherIncome !== null
                ? (int) $fatherIncome + (int) $motherIncome
                : null;
            $oldValues['penghasilan_gabungan_rupiah'] = $application->penghasilan_gabungan_rupiah;
        }

        $changedValues = [];
        foreach ($newValues as $field => $newValue) {
            $oldValue = $oldValues[$field] ?? null;
            if ($this->sameRawValue($oldValue, $newValue)) {
                continue;
            }
            $changedValues[$field] = [
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        if ($changedValues === []) {
            return [
                'application' => $application->fresh(),
                'changed' => false,
                'deleted_training_rows' => 0,
                'deleted_encodings' => 0,
                'deleted_snapshot' => false,
            ];
        }

        $deletedTrainingRows = 0;
        $deletedEncodings = 0;
        $hadSnapshot = false;

        DB::transaction(function () use ($application, $newValues, $changedValues, $actorUserId, &$deletedTrainingRows, &$deletedEncodings, &$hadSnapshot): void {
            $deletedTrainingRows = $application->trainingRows()->count();
            $deletedEncodings = $application->featureEncodings()->count();
            $hadSnapshot = $application->modelSnapshot()->exists();
            $changedFieldNames = array_keys($changedValues);
            $houseOnlyUpdate = $changedFieldNames === ['status_rumah_text'];

            $application->forceFill($newValues)->save();

            $application->modelSnapshot()->delete();
            $application->trainingRows()->delete();
            $application->featureEncodings()->delete();

            ApplicationStatusLog::query()->create([
                'application_id' => $application->id,
                'actor_user_id' => $actorUserId,
                'from_status' => $application->status,
                'to_status' => $application->status,
                'action' => $houseOnlyUpdate ? 'updated_house_status' : 'updated_raw_applicant_data',
                'note' => $houseOnlyUpdate
                    ? 'Admin memperbarui status rumah pada data mentah.'
                    : 'Admin memperbarui data mentah applicant offline.',
                'metadata' => [
                    'changed_fields' => $changedValues,
                    'old_status_rumah_text' => $changedValues['status_rumah_text']['old'] ?? null,
                    'new_status_rumah_text' => $changedValues['status_rumah_text']['new'] ?? null,
                    'deleted_training_rows' => $deletedTrainingRows,
                    'deleted_encodings' => $deletedEncodings,
                    'deleted_snapshot' => $hadSnapshot,
                ],
            ]);
        });

        return [
            'application' => $application->fresh([
                'modelSnapshot',
                'currentEncoding',
                'trainingRows',
            ]),
            'changed' => true,
            'deleted_training_rows' => $deletedTrainingRows,
            'deleted_encodings' => $deletedEncodings,
            'deleted_snapshot' => $hadSnapshot,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeRawDataPayload(array $payload): array
    {
        $integerFields = [
            'kip',
            'pkh',
            'kks',
            'dtks',
            'sktm',
            'penghasilan_ayah_rupiah',
            'penghasilan_ibu_rupiah',
            'jumlah_tanggungan_raw',
            'anak_ke_raw',
        ];
        $stringFields = [
            'status_orangtua_text',
            'status_rumah_text',
            'daya_listrik_text',
        ];
        $normalized = [];

        foreach ($integerFields as $field) {
            if (! array_key_exists($field, $payload)) {
                continue;
            }
            $normalized[$field] = $this->nullableInt($payload[$field]);
        }

        foreach ($stringFields as $field) {
            if (! array_key_exists($field, $payload)) {
                continue;
            }
            $normalized[$field] = $this->nullableString($payload[$field]);
        }

        return $normalized;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = trim((string) $value);

        return $stringValue === '' ? null : $stringValue;
    }

    private function sameRawValue(mixed $oldValue, mixed $newValue): bool
    {
        if ($oldValue === null && $newValue === null) {
            return true;
        }

        return (string) $oldValue === (string) $newValue;
    }
}
