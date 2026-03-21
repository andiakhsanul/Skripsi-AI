<?php

namespace App\Http\Controllers;

use App\Models\StudentApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminStatsController extends Controller
{
    public function index(): JsonResponse
    {
        $total     = StudentApplication::query()->count();
        $submitted = StudentApplication::query()->where('status', 'Submitted')->count();
        $verified  = StudentApplication::query()->where('status', 'Verified')->count();
        $rejected  = StudentApplication::query()->where('status', 'Rejected')->count();

        $highPriority = StudentApplication::query()
            ->where('review_priority', 'high')
            ->where('status', 'Submitted')
            ->count();

        $modelReady = StudentApplication::query()
            ->where('model_ready', true)
            ->count();

        $trainingCount = DB::table('spk_training_data')
            ->where('is_active', true)
            ->count();

        $adminCorrectedCount = DB::table('spk_training_data')
            ->where('admin_corrected', true)
            ->count();

        // Pengajuan per tahun
        $perYear = StudentApplication::query()
            ->selectRaw('EXTRACT(YEAR FROM created_at)::integer AS year, COUNT(*) AS total')
            ->groupBy(DB::raw('EXTRACT(YEAR FROM created_at)'))
            ->orderByDesc('year')
            ->pluck('total', 'year');

        return response()->json([
            'status' => 'success',
            'data'   => [
                'applications' => [
                    'total'        => $total,
                    'submitted'    => $submitted,
                    'verified'     => $verified,
                    'rejected'     => $rejected,
                    'high_priority_pending' => $highPriority,
                    'model_ready'  => $modelReady,
                ],
                'training_data' => [
                    'total_active'    => $trainingCount,
                    'admin_corrected' => $adminCorrectedCount,
                ],
                'applications_per_year' => $perYear,
            ],
        ]);
    }
}
