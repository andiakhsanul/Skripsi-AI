<?php

namespace App\Services;

use App\Models\ParameterSchemaVersion;
use App\Models\StudentApplication;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AdminDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $total = StudentApplication::query()->count();
        $submitted = StudentApplication::query()->where('status', 'Submitted')->count();
        $verified = StudentApplication::query()->where('status', 'Verified')->count();
        $rejected = StudentApplication::query()->where('status', 'Rejected')->count();

        $highPriorityPending = StudentApplication::query()
            ->where('status', 'Submitted')
            ->whereHas('modelSnapshot', fn (Builder $query) => $query->where('review_priority', 'high'))
            ->count();

        $indikasiRecommendations = StudentApplication::query()
            ->whereHas('modelSnapshot', fn (Builder $query) => $query->where('final_recommendation', 'Indikasi'))
            ->count();

        $indikasiPending = StudentApplication::query()
            ->where('status', 'Submitted')
            ->whereHas('modelSnapshot', fn (Builder $query) => $query->where('final_recommendation', 'Indikasi'))
            ->count();

        $disagreementCases = StudentApplication::query()
            ->whereHas('modelSnapshot', fn (Builder $query) => $query->where('disagreement_flag', true))
            ->count();

        $pendingHouseReview = StudentApplication::query()
            ->where('submission_source', 'offline_admin_import')
            ->where(function (Builder $query): void {
                $query->whereNull('status_rumah_text')
                    ->orWhere('status_rumah_text', '');
            })
            ->count();

        $modelReady = DB::table('application_model_snapshots')
            ->where('model_ready', true)
            ->count();

        $trainingCount = DB::table('spk_training_data')
            ->where('is_active', true)
            ->count();

        $adminCorrectedCount = DB::table('spk_training_data')
            ->where('admin_corrected', true)
            ->count();

        $pendingConfirmationCount = DB::table('spk_training_data')
            ->where('is_active', true)
            ->where('admin_corrected', false)
            ->count();

        $activeSchema = ParameterSchemaVersion::query()
            ->where('is_active', true)
            ->orderByDesc('version')
            ->first();

        return [
            'applications' => [
                'total' => $total,
                'submitted' => $submitted,
                'verified' => $verified,
                'rejected' => $rejected,
                'high_priority_pending' => $highPriorityPending,
                'indikasi_recommendations' => $indikasiRecommendations,
                'indikasi_pending' => $indikasiPending,
                'disagreement_cases' => $disagreementCases,
                'pending_house_review' => $pendingHouseReview,
                'model_ready' => $modelReady,
            ],
            'training_data' => [
                'total_active' => $trainingCount,
                'admin_corrected' => $adminCorrectedCount,
                'pending_confirmation' => $pendingConfirmationCount,
            ],
            'active_schema' => $activeSchema,
        ];
    }

    /**
     * @param array<string, mixed> $summary
     * @return array<string, mixed>
     */
    public function viewPayload(array $summary): array
    {
        $applicationStats = $summary['applications'];
        $trainingStats = $summary['training_data'];
        $activeSchema = $summary['active_schema'];

        return [
            'filters' => [
                'status_options' => [
                    '' => 'Semua status',
                    'Submitted' => 'Menunggu',
                    'Verified' => 'Terverifikasi',
                    'Rejected' => 'Ditolak',
                ],
                'priority_options' => [
                    '' => 'Semua prioritas',
                    'high' => 'Tinggi',
                    'normal' => 'Normal',
                ],
                'recommendation_options' => [
                    '' => 'Semua rekomendasi',
                    'Indikasi' => 'Rekomendasi Indikasi',
                    'Layak' => 'Rekomendasi Layak',
                ],
                'disagreement_options' => [
                    '' => 'Semua selisih model',
                    'true' => 'Ada disagreement',
                    'false' => 'Selaras',
                ],
            ],
            'status_display_labels' => [
                'Submitted' => 'Menunggu',
                'Verified' => 'Terverifikasi',
                'Rejected' => 'Ditolak',
            ],
            'status_badge_classes' => [
                'Submitted' => 'bg-yellow-50 text-yellow-700 border border-yellow-100',
                'Verified' => 'bg-emerald-50 text-emerald-600 border border-emerald-100',
                'Rejected' => 'bg-error-container text-error border border-error/10',
            ],
            'priority_meta' => [
                'high' => [
                    'dot' => 'bg-error',
                    'text' => 'text-error font-bold',
                    'label' => 'Tinggi',
                ],
                'normal' => [
                    'dot' => 'bg-slate-300',
                    'text' => 'text-slate-500 font-medium',
                    'label' => 'Normal',
                ],
            ],
            'stat_cards' => [
                [
                    'label' => 'Total Pengajuan',
                    'value' => $applicationStats['total'],
                    'hint' => 'Semua pengajuan yang tercatat',
                    'hint_class' => 'text-slate-500',
                    'border' => 'border-primary',
                    'icon_wrap' => 'bg-primary/10 text-primary',
                    'icon' => 'groups',
                ],
                [
                    'label' => 'Menunggu',
                    'value' => $applicationStats['submitted'],
                    'hint' => 'Menunggu review admin',
                    'hint_class' => 'text-yellow-600',
                    'border' => 'border-yellow-500',
                    'icon_wrap' => 'bg-yellow-50 text-yellow-600',
                    'icon' => 'schedule',
                ],
                [
                    'label' => 'Terverifikasi',
                    'value' => $applicationStats['verified'],
                    'hint' => 'Diputuskan layak',
                    'hint_class' => 'text-emerald-600',
                    'border' => 'border-emerald-500',
                    'icon_wrap' => 'bg-emerald-50 text-emerald-600',
                    'icon' => 'check_circle',
                ],
                [
                    'label' => 'Ditolak',
                    'value' => $applicationStats['rejected'],
                    'hint' => 'Diputuskan indikasi',
                    'hint_class' => 'text-error',
                    'border' => 'border-error',
                    'icon_wrap' => 'bg-error-container text-error',
                    'icon' => 'cancel',
                ],
                [
                    'label' => 'Prioritas Tinggi',
                    'value' => $applicationStats['high_priority_pending'],
                    'hint' => 'Perlu tindakan cepat',
                    'hint_class' => 'text-primary',
                    'border' => 'border-primary-container',
                    'icon_wrap' => 'bg-primary-container text-primary',
                    'icon' => 'priority_high',
                ],
                [
                    'label' => 'Disagreement',
                    'value' => $applicationStats['disagreement_cases'],
                    'hint' => 'CatBoost dan Naive Bayes berbeda',
                    'hint_class' => 'text-yellow-700',
                    'border' => 'border-yellow-500',
                    'icon_wrap' => 'bg-secondary-container text-on-secondary-container',
                    'icon' => 'difference',
                ],
            ],
            'workflow_cards' => [
                [
                    'title' => 'Data Mahasiswa',
                    'description' => 'Mahasiswa mengirim 13 atribut utama dan dokumen pendukung melalui portal atau impor admin.',
                    'icon' => 'description',
                    'tone' => 'bg-primary/10 text-primary',
                ],
                [
                    'title' => 'Mesin Prediksi',
                    'description' => 'CatBoost menjadi rekomendasi utama, sementara Naive Bayes dipakai sebagai pembanding hasil.',
                    'icon' => 'auto_awesome',
                    'tone' => 'bg-secondary-container text-on-secondary-container',
                ],
                [
                    'title' => 'Keputusan Akhir',
                    'description' => 'Status terverifikasi atau ditolak menjadi dasar data latih ketika proses retrain dijalankan.',
                    'icon' => 'gavel',
                    'tone' => 'bg-emerald-50 text-emerald-700',
                ],
            ],
            'operation_cards' => [
                [
                    'label' => 'Skema Aktif',
                    'value' => $activeSchema ? 'v'.$activeSchema->version : 'Belum aktif',
                    'detail' => $activeSchema?->source_file_name ?? 'Menunggu pengaturan skema aktif',
                ],
                [
                    'label' => 'Data Siap Dilatih',
                    'value' => number_format($trainingStats['total_active']),
                    'detail' => 'Baris training aktif yang siap dipakai retrain',
                ],
                [
                    'label' => 'Kelengkapan Data',
                    'value' => number_format($applicationStats['pending_house_review']),
                    'detail' => 'Applicant offline dengan data mentah wajib yang masih kosong',
                ],
                [
                    'label' => 'Snapshot Siap Pakai',
                    'value' => number_format($applicationStats['model_ready']),
                    'detail' => 'Pengajuan yang sudah punya rekomendasi sistem',
                ],
                [
                    'label' => 'Koreksi Training',
                    'value' => number_format($trainingStats['admin_corrected']),
                    'detail' => 'Baris training yang sudah dikonfirmasi admin',
                ],
                [
                    'label' => 'Menunggu Konfirmasi',
                    'value' => number_format($trainingStats['pending_confirmation'] ?? 0),
                    'detail' => 'Baris training yang belum dikonfirmasi admin',
                ],
            ],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function applicationsPerYear(): array
    {
        return StudentApplication::query()
            ->selectRaw('EXTRACT(YEAR FROM created_at)::integer AS year, COUNT(*) AS total')
            ->groupBy(DB::raw('EXTRACT(YEAR FROM created_at)'))
            ->orderByDesc('year')
            ->pluck('total', 'year')
            ->map(static fn (mixed $value): int => (int) $value)
            ->all();
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function paginateApplications(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return StudentApplication::query()
            ->with(['student:id,name,email', 'modelSnapshot', 'latestTrainingRow'])
            ->when(
                $filters['q'] ?? null,
                function (Builder $query, string $term): void {
                    $query->where(function (Builder $scopedQuery) use ($term): void {
                        $scopedQuery
                            ->whereHas('student', function (Builder $studentQuery) use ($term): void {
                                $studentQuery
                                    ->where('name', 'like', "%{$term}%")
                                    ->orWhere('email', 'like', "%{$term}%");
                            })
                            ->orWhere('applicant_name', 'like', "%{$term}%")
                            ->orWhere('applicant_email', 'like', "%{$term}%")
                            ->orWhere('source_reference_number', 'like', "%{$term}%");
                    });
                }
            )
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when(
                $filters['priority'] ?? null,
                fn (Builder $query, string $priority) => $query->whereHas(
                    'modelSnapshot',
                    fn (Builder $snapshotQuery) => $snapshotQuery->where('review_priority', $priority)
                )
            )
            ->when(
                $filters['recommendation'] ?? null,
                fn (Builder $query, string $recommendation) => $query->whereHas(
                    'modelSnapshot',
                    fn (Builder $snapshotQuery) => $snapshotQuery->where('final_recommendation', $recommendation)
                )
            )
            ->when(
                array_key_exists('disagreement', $filters) && $filters['disagreement'] !== '',
                function (Builder $query) use ($filters): void {
                    $enabled = filter_var($filters['disagreement'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                    if ($enabled === null) {
                        return;
                    }

                    $query->whereHas(
                        'modelSnapshot',
                        fn (Builder $snapshotQuery) => $snapshotQuery->where('disagreement_flag', $enabled)
                    );
                }
            )
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
