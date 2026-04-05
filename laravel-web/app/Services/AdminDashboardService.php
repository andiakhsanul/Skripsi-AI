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

        $modelReady = DB::table('application_model_snapshots')
            ->where('model_ready', true)
            ->count();

        $trainingCount = DB::table('spk_training_data')
            ->where('is_active', true)
            ->count();

        $adminCorrectedCount = DB::table('spk_training_data')
            ->where('admin_corrected', true)
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
                'model_ready' => $modelReady,
            ],
            'training_data' => [
                'total_active' => $trainingCount,
                'admin_corrected' => $adminCorrectedCount,
            ],
            'active_schema' => $activeSchema,
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
            ->with(['student:id,name,email', 'modelSnapshot'])
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
