<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

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
        ]);

        return view('admin.dashboard', [
            'admin' => $request->user(),
            'summary' => $this->adminDashboardService->summary(),
            'applications' => $this->adminDashboardService->paginateApplications($filters, 10),
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $filters['status'] ?? '',
                'priority' => $filters['priority'] ?? '',
            ],
        ]);
    }
}
