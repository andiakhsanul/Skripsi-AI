<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicationStatusLog;
use App\Models\StudentApplication;
use App\Services\TrainingDataSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly TrainingDataSyncService $trainingDataSyncService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string'],
            'review_priority' => ['nullable', 'string', 'in:high,normal'],
            'year' => ['nullable', 'integer', 'min:2020'],
        ]);

        $applications = StudentApplication::query()
            ->with(['student:id,name,email', 'modelSnapshot'])
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when(
                $validated['review_priority'] ?? null,
                fn ($query, $priority) => $query->whereHas(
                    'modelSnapshot',
                    fn ($snapshotQuery) => $snapshotQuery->where('review_priority', $priority)
                )
            )
            ->when($validated['year'] ?? null, fn ($query, $year) => $query->whereYear('created_at', $year))
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
                'logs',
                'modelSnapshot',
            ])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => array_merge($application->toArray(), [
                'submitted_pdf_url' => Storage::disk('public')->url($application->submitted_pdf_path),
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

    public function showTrainingData(int $id): JsonResponse
    {
        $application = StudentApplication::query()
            ->with(['student:id,name,email', 'modelSnapshot'])
            ->findOrFail($id);

        $trainingData = DB::table('spk_training_data')
            ->where('source_application_id', $id)
            ->first();

        if (! $trainingData) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data training belum tersedia. Pastikan pengajuan sudah diverifikasi.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'application_id' => $id,
                'student_name' => $application->student?->name ?? '-',
                'model_snapshot' => $application->modelSnapshot,
                'training_data' => $trainingData,
                'encoding_legend' => [
                    'binary' => 'KIP/PKH/KKS/DTKS/SKTM: 0=Tidak, 1=Ya',
                    'ordinal' => '1=Prioritas Tinggi (paling rentan), 3=Prioritas Rendah',
                    'income' => 'penghasilan: 1=<1jt, 2=1-4jt, 3=≥4jt',
                    'dependents' => 'jumlah_tanggungan: 1=≥6 orang, 2=4-5 orang, 3=0-3 orang',
                    'child' => 'anak_ke: 1=≥anak ke-5, 2=anak ke-3/4, 3=anak ke-1/2',
                    'parent' => 'status_orangtua: 1=Yatim Piatu, 2=Yatim/Piatu, 3=Lengkap',
                    'house' => 'status_rumah: 1=Tidak Punya, 2=Sewa/Menumpang, 3=Milik Sendiri',
                    'power' => 'daya_listrik: 1=Non-PLN, 2=PLN 450-900VA, 3=PLN >900VA',
                    'label' => 'label_class: 0=Layak, 1=Indikasi',
                ],
            ],
        ]);
    }

    public function updateTrainingData(Request $request, int $id): JsonResponse
    {
        StudentApplication::query()->findOrFail($id);

        $validated = $request->validate([
            'kip' => ['sometimes', 'integer', 'in:0,1'],
            'pkh' => ['sometimes', 'integer', 'in:0,1'],
            'kks' => ['sometimes', 'integer', 'in:0,1'],
            'dtks' => ['sometimes', 'integer', 'in:0,1'],
            'sktm' => ['sometimes', 'integer', 'in:0,1'],
            'penghasilan_gabungan' => ['sometimes', 'integer', 'in:1,2,3'],
            'penghasilan_ayah' => ['sometimes', 'integer', 'in:1,2,3'],
            'penghasilan_ibu' => ['sometimes', 'integer', 'in:1,2,3'],
            'jumlah_tanggungan' => ['sometimes', 'integer', 'in:1,2,3'],
            'anak_ke' => ['sometimes', 'integer', 'in:1,2,3'],
            'status_orangtua' => ['sometimes', 'integer', 'in:1,2,3'],
            'status_rumah' => ['sometimes', 'integer', 'in:1,2,3'],
            'daya_listrik' => ['sometimes', 'integer', 'in:1,2,3'],
            'label' => ['sometimes', 'string', 'in:Layak,Indikasi'],
            'correction_note' => ['nullable', 'string', 'max:500'],
        ]);

        $exists = DB::table('spk_training_data')
            ->where('source_application_id', $id)
            ->exists();

        if (! $exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data training belum tersedia untuk pengajuan ini.',
            ], 404);
        }

        $updateData = array_filter($validated, static fn ($value): bool => $value !== null);
        $updateData['admin_corrected'] = true;
        $updateData['updated_at'] = now();

        if (isset($validated['label'])) {
            $updateData['label_class'] = $validated['label'] === 'Indikasi' ? 1 : 0;
        }

        DB::table('spk_training_data')
            ->where('source_application_id', $id)
            ->update($updateData);

        return response()->json([
            'status' => 'success',
            'message' => 'Data training berhasil dikoreksi. Model akan menggunakan data ini saat retrain berikutnya.',
        ]);
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

        $application = DB::transaction(function () use ($application, $request, $validated, $status): StudentApplication {
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
                'metadata' => ['admin_decision' => $status],
            ]);

            $this->trainingDataSyncService->syncFromApplication($application);

            return $application->fresh(['modelSnapshot']);
        });

        return response()->json([
            'status' => 'success',
            'message' => "Pengajuan berhasil diubah menjadi {$status}",
            'data' => $application,
        ]);
    }
}
