<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApplicationListController extends Controller
{
    public function __construct(
        private readonly AdminDashboardService $adminDashboardService,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(['Submitted', 'Verified', 'Rejected'])],
            'decision' => ['nullable', 'string', Rule::in(['decided', 'undecided'])],
            'source' => ['nullable', 'string', Rule::in(['online_student', 'offline_admin_import'])],
            'priority' => ['nullable', 'string', Rule::in(['high', 'normal'])],
            'recommendation' => ['nullable', 'string', Rule::in(['Layak', 'Indikasi'])],
            'disagreement' => ['nullable', 'string', Rule::in(['true', 'false'])],
        ]);

        $viewFilters = [
            'q' => $filters['q'] ?? '',
            'status' => $filters['status'] ?? '',
            'decision' => $filters['decision'] ?? '',
            'source' => $filters['source'] ?? '',
            'priority' => $filters['priority'] ?? '',
            'recommendation' => $filters['recommendation'] ?? '',
            'disagreement' => $filters['disagreement'] ?? '',
        ];

        $applications = $this->adminDashboardService->paginateApplications($viewFilters, 15);
        $applications->appends($viewFilters);
        $summary = $this->adminDashboardService->summary();

        return view('pages.admin.applications.index', [
            'admin' => $request->user(),
            'summary' => $summary,
            'page' => $this->adminDashboardService->viewPayload($summary),
            'applications' => $applications,
            'filters' => $viewFilters,
        ]);
    }
}
