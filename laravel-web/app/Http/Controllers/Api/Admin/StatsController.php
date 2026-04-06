<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    public function __construct(
        private readonly AdminDashboardService $adminDashboardService,
    ) {}

    public function index(): JsonResponse
    {
        $data = $this->adminDashboardService->summary();
        $data['applications_per_year'] = $this->adminDashboardService->applicationsPerYear();

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }
}
