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
     * @return array<string, int>
     */
    public function summary(): array
    {
        $baseQuery = StudentApplication::query()
            ->where('submission_source', 'offline_admin_import');

        $total = (clone $baseQuery)->count();
        $pending = (clone $baseQuery)
            ->where(function (Builder $query): void {
                $query->whereNull('status_rumah_text')
                    ->orWhere('status_rumah_text', '');
            })
            ->count();

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
                    'label' => 'Perlu Koreksi Rumah',
                    'value' => number_format($summary['pending_house_review']),
                    'hint' => 'Status rumah masih kosong dan perlu Anda isi',
                    'tone' => 'bg-error-container text-error',
                    'icon' => 'home_work',
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
                '' => 'Semua status rumah',
                'missing' => 'Perlu dilengkapi',
                'filled' => 'Sudah diisi',
            ],
            'house_status_options' => $this->houseStatusOptions(),
            'guides' => [
                'Halaman ini hanya memperbaiki data mentah, belum menyentuh data training.',
                'Pilih status rumah yang paling sesuai berdasarkan dokumen pendukung mahasiswa.',
                'Jika status rumah diubah setelah ada encoding atau snapshot, sistem akan menghapus artefak lama agar sinkron kembali.',
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
                fn (Builder $query) => $query->where(function (Builder $scopedQuery): void {
                    $scopedQuery->whereNull('status_rumah_text')
                        ->orWhere('status_rumah_text', '');
                })
            )
            ->when(
                ($filters['house_state'] ?? '') === 'filled',
                fn (Builder $query) => $query->whereNotNull('status_rumah_text')->where('status_rumah_text', '!=', '')
            )
            ->orderByRaw("CASE WHEN status_rumah_text IS NULL OR status_rumah_text = '' THEN 0 ELSE 1 END ASC")
            ->orderBy('source_row_number')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function updateHouseStatus(StudentApplication $application, ?string $statusRumahText, int $actorUserId): StudentApplication
    {
        $newValue = blank($statusRumahText) ? null : trim((string) $statusRumahText);
        $oldValue = blank($application->status_rumah_text) ? null : trim((string) $application->status_rumah_text);

        if ($oldValue === $newValue) {
            return $application->fresh();
        }

        DB::transaction(function () use ($application, $newValue, $oldValue, $actorUserId): void {
            $deletedTrainingRows = $application->trainingRows()->count();
            $deletedEncodings = $application->featureEncodings()->count();
            $hadSnapshot = $application->modelSnapshot()->exists();

            $application->forceFill([
                'status_rumah_text' => $newValue,
            ])->save();

            $application->modelSnapshot()->delete();
            $application->trainingRows()->delete();
            $application->featureEncodings()->delete();

            ApplicationStatusLog::query()->create([
                'application_id' => $application->id,
                'actor_user_id' => $actorUserId,
                'from_status' => $application->status,
                'to_status' => $application->status,
                'action' => 'updated_house_status',
                'note' => 'Admin memperbarui status rumah pada data mentah.',
                'metadata' => [
                    'old_status_rumah_text' => $oldValue,
                    'new_status_rumah_text' => $newValue,
                    'deleted_training_rows' => $deletedTrainingRows,
                    'deleted_encodings' => $deletedEncodings,
                    'deleted_snapshot' => $hadSnapshot,
                ],
            ]);
        });

        return $application->fresh([
            'modelSnapshot',
            'currentEncoding',
            'trainingRows',
        ]);
    }
}
