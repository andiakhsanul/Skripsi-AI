<?php

namespace App\Http\Controllers\Web\Student;

use App\Http\Controllers\Controller;
use App\Services\StudentDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly StudentDashboardService $studentDashboardService,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:Submitted,Verified,Rejected'],
        ]);

        $student = $request->user();

        return view('pages.student.dashboard', [
            'student' => $student,
            'summary' => $this->studentDashboardService->summary($student),
            'applications' => $this->studentDashboardService->paginateApplications($student, $filters, 8),
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
        ]);
    }
}
