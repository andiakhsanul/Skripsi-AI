<?php

namespace App\Services;

use App\Models\StudentApplication;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class StudentDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(User $student): array
    {
        $baseQuery = StudentApplication::query()
            ->where('student_user_id', $student->id);

        $total = (clone $baseQuery)->count();
        $submitted = (clone $baseQuery)->where('status', 'Submitted')->count();
        $verified = (clone $baseQuery)->where('status', 'Verified')->count();
        $rejected = (clone $baseQuery)->where('status', 'Rejected')->count();

        $latestApplication = (clone $baseQuery)
            ->with('modelSnapshot')
            ->latest('created_at')
            ->first();

        $latestDecisionStatus = $latestApplication?->admin_decision;

        if ($latestDecisionStatus === null && in_array($latestApplication?->status, ['Verified', 'Rejected'], true)) {
            $latestDecisionStatus = $latestApplication?->status;
        }

        return [
            'total' => $total,
            'submitted' => $submitted,
            'verified' => $verified,
            'rejected' => $rejected,
            'latest_status' => $latestApplication?->status,
            'latest_submitted_at' => $latestApplication?->created_at,
            'has_documents' => (clone $baseQuery)->whereNotNull('submitted_pdf_path')->exists(),
            'latest_application' => $latestApplication,
            'latest_recommendation' => $latestApplication?->modelSnapshot?->final_recommendation,
            'latest_decision_ready' => $latestDecisionStatus !== null,
            'latest_decision_status' => $latestDecisionStatus,
            'latest_decision_at' => $latestApplication?->admin_decided_at,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function paginateApplications(User $student, array $filters, int $perPage = 8): LengthAwarePaginator
    {
        return StudentApplication::query()
            ->with('modelSnapshot')
            ->where('student_user_id', $student->id)
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, string $status) => $query->where('status', $status)
            )
            ->when(
                $filters['q'] ?? null,
                function (Builder $query, string $term): void {
                    if (is_numeric($term)) {
                        $query->where('id', (int) $term);

                        return;
                    }

                    $query->where('status', 'like', "%{$term}%");
                }
            )
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
