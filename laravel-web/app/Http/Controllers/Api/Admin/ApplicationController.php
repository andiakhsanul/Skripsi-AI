<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentApplication;
use App\Services\AdminApplicationReviewService;
use App\Services\AdminTrainingDataReviewService;
use App\Services\TrainingDataSyncService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly TrainingDataSyncService $trainingDataSyncService,
        private readonly AdminApplicationReviewService $reviewService,
        private readonly AdminTrainingDataReviewService $trainingDataReviewService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string'],
            'review_priority' => ['nullable', 'string', 'in:high,normal'],
            'year' => ['nullable', 'integer', 'min:2020'],
        ]);

        $applications = StudentApplication::query()
            ->with(['student:id,name,email', 'currentEncoding', 'modelSnapshot'])
            ->when($validated['status'] ?? null, fn($query, $status) => $query->where('status', $status))
            ->when(
                $validated['review_priority'] ?? null,
                fn($query, $priority) => $query->whereHas(
                    'modelSnapshot',
                    fn($snapshotQuery) => $snapshotQuery->where('review_priority', $priority)
                )
            )
            ->when($validated['year'] ?? null, fn($query, $year) => $query->whereYear('created_at', $year))
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $applications,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $application = $this->reviewService->detail($id);

        return response()->json([
            'status' => 'success',
            'data' => array_merge($application->toArray(), [
                'submitted_pdf_url' => $this->reviewService->documentUrl($application),
            ]),
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

    public function runPredictions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:Submitted,Verified,Rejected'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
            'only_missing' => ['nullable', 'boolean'],
        ]);

        $result = $this->reviewService->batchRunPredictions(
            $request->user()->id,
            $validated['status'] ?? null,
            isset($validated['limit']) ? (int) $validated['limit'] : null,
            (bool) ($validated['only_missing'] ?? true),
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Batch prediction selesai diproses.',
            'data' => $result,
        ]);
    }

    public function syncFinalizedTraining(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'force_resync' => ['nullable', 'boolean'],
        ]);

        $result = $this->trainingDataSyncService->syncFinalizedApplications(
            $request->user()->id,
            (bool) ($validated['force_resync'] ?? false),
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Sinkronisasi data training selesai.',
            'data' => $result,
        ]);
    }

    public function showTrainingData(int $id): JsonResponse
    {
        try {
            $detail = $this->trainingDataReviewService->detail($id);
        } catch (ValidationException $validationException) {
            return response()->json([
                'status' => 'error',
                'message' => $validationException->getMessage(),
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'application_id' => $id,
                'student_name' => $detail['application']->student?->name ?? $detail['application']->applicant_name ?? '-',
                'current_encoding' => $detail['application']->currentEncoding,
                'model_snapshot' => $detail['application']->modelSnapshot,
                'training_data' => $detail['training_row'],
                'encoding_legend' => $detail['legend'],
            ],
        ]);
    }

    public function updateTrainingData(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'kip' => ['sometimes', 'integer', 'in:0,1'],
            'pkh' => ['sometimes', 'integer', 'in:0,1'],
            'kks' => ['sometimes', 'integer', 'in:0,1'],
            'dtks' => ['sometimes', 'integer', 'in:0,1'],
            'sktm' => ['sometimes', 'integer', 'in:0,1'],
            'penghasilan_gabungan' => ['sometimes', 'integer', 'in:1,2,3,4,5'],
            'penghasilan_ayah' => ['sometimes', 'integer', 'in:1,2,3,4,5'],
            'penghasilan_ibu' => ['sometimes', 'integer', 'in:1,2,3,4,5'],
            'jumlah_tanggungan' => ['sometimes', 'integer', 'in:1,2,3,4,5'],
            'anak_ke' => ['sometimes', 'integer', 'in:1,2,3,4,5'],
            'status_orangtua' => ['sometimes', 'integer', 'in:1,2,3'],
            'status_rumah' => ['sometimes', 'integer', 'in:1,2,3,4'],
            'daya_listrik' => ['sometimes', 'integer', 'in:1,2,3,4,5'],
            'label' => ['sometimes', 'string', 'in:Layak,Indikasi'],
            'correction_note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $trainingData = $this->trainingDataReviewService->update($id, $validated);
        } catch (ValidationException $validationException) {
            return response()->json([
                'status' => 'error',
                'message' => $validationException->getMessage(),
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data training berhasil dikoreksi. Model akan menggunakan data ini saat retrain berikutnya.',
            'data' => $trainingData,
        ]);
    }

    private function finalize(Request $request, int $id, string $status): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $application = $this->reviewService->finalize(
                $id,
                $request->user()->id,
                $status,
                $validated['note'] ?? null,
            );
        } catch (DomainException $domainException) {
            return response()->json([
                'status' => 'error',
                'message' => $domainException->getMessage(),
            ], 409);
        }

        return response()->json([
            'status' => 'success',
            'message' => "Pengajuan berhasil diubah menjadi {$status}",
            'data' => $application,
        ]);
    }
}
