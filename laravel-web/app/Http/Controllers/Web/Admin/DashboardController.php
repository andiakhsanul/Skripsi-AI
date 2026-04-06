<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AdminDashboardService $adminDashboardService,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(['Submitted', 'Verified', 'Rejected'])],
            'priority' => ['nullable', 'string', Rule::in(['high', 'normal'])],
            'recommendation' => ['nullable', 'string', Rule::in(['Layak', 'Indikasi'])],
            'disagreement' => ['nullable', 'string', Rule::in(['true', 'false'])],
            'scope' => ['nullable', 'string', Rule::in(['all'])],
        ]);

        $defaultFocusApplied = false;

        if ($this->shouldApplyDefaultIndikasiFocus($request, $filters)) {
            $filters['status'] = 'Submitted';
            $filters['recommendation'] = 'Indikasi';
            $defaultFocusApplied = true;
        }

        $viewFilters = [
            'q' => $filters['q'] ?? '',
            'status' => $filters['status'] ?? '',
            'priority' => $filters['priority'] ?? '',
            'recommendation' => $filters['recommendation'] ?? '',
            'disagreement' => $filters['disagreement'] ?? '',
            'scope' => $filters['scope'] ?? '',
        ];

        $applications = $this->adminDashboardService->paginateApplications($viewFilters, 10);
        $applications->appends($viewFilters);
        $summary = $this->adminDashboardService->summary();

        return view('pages.admin.dashboard', [
            'admin' => $request->user(),
            'summary' => $summary,
            'page' => $this->adminDashboardService->viewPayload($summary),
            'applications' => $applications,
            'filters' => $viewFilters,
            'defaultFocusApplied' => $defaultFocusApplied,
        ]);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function shouldApplyDefaultIndikasiFocus(Request $request, array $filters): bool
    {
        if (($filters['scope'] ?? null) === 'all') {
            return false;
        }

        $query = $request->query();
        unset($query['page']);

        return $query === [];
    }
}
