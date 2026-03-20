<?php

namespace App\Http\Controllers;

use App\Models\ApplicationStatusLog;
use App\Models\StudentApplication;
use App\Services\TrainingDataSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminApplicationController extends Controller
{
    public function __construct(
        private readonly TrainingDataSyncService $trainingDataSyncService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string'],
        ]);

        $applications = StudentApplication::query()
            ->with('student:id,name,email')
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $applications,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $application = StudentApplication::query()
            ->with([
                'student:id,name,email',
                'adminDecider:id,name,email',
                'logs.actor:id,name,email',
            ])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $application,
        ]);
    }

    public function verify(Request $request, int $id): JsonResponse
    {
        return $this->finalize($request, $id, 'Verified');
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        return $this->finalize($request, $id, 'Rejected');
    }

    private function finalize(Request $request, int $id, string $status): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $application = StudentApplication::query()->findOrFail($id);

        if (! in_array($application->status, ['Submitted'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pengajuan hanya dapat diputuskan dari status Submitted',
            ], 409);
        }

        $previousStatus = $application->status;
        $application->forceFill([
            'status' => $status,
            'admin_decision' => $status,
            'admin_decided_by' => $request->user()->id,
            'admin_decision_note' => $validated['note'] ?? null,
            'admin_decided_at' => now(),
        ])->save();

        ApplicationStatusLog::query()->create([
            'application_id' => $application->id,
            'actor_user_id' => $request->user()->id,
            'from_status' => $previousStatus,
            'to_status' => $status,
            'action' => strtolower($status),
            'note' => $validated['note'] ?? null,
            'metadata' => [
                'admin_decision' => $status,
            ],
        ]);

        $this->trainingDataSyncService->syncFromApplication($application);

        return response()->json([
            'status' => 'success',
            'message' => "Pengajuan berhasil diubah menjadi {$status}",
            'data' => $application,
        ]);
    }
}
