<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ApplicationStatusLog;
use App\Models\StudentApplication;
use App\Services\ApplicationInferenceService;
use App\Services\ParameterSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly ParameterSchemaService $schemaService,
        private readonly ApplicationInferenceService $applicationInferenceService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateStoreRequest($request);
        $schemaVersion = $this->schemaService->resolveSchemaVersion($validated['schema_version'] ?? null);
        $pdfPath = $validated['submitted_pdf']->store('student-application-pdfs', 'public');

        $application = DB::transaction(function () use (
            $request,
            $schemaVersion,
            $validated,
            $pdfPath,
        ): StudentApplication {
            $application = StudentApplication::query()->create([
                'student_user_id' => $request->user()->id,
                'schema_version' => $schemaVersion,
                'submission_source' => 'online_student',
                'applicant_name' => $request->user()->name,
                'applicant_email' => $request->user()->email,
                'kip' => (int) $validated['kip'],
                'pkh' => (int) $validated['pkh'],
                'kks' => (int) $validated['kks'],
                'dtks' => (int) $validated['dtks'],
                'sktm' => (int) $validated['sktm'],
                'penghasilan_ayah_rupiah' => (int) $validated['penghasilan_ayah_rupiah'],
                'penghasilan_ibu_rupiah' => (int) $validated['penghasilan_ibu_rupiah'],
                'penghasilan_gabungan_rupiah' => $this->resolveCombinedIncome(
                    (int) $validated['penghasilan_ayah_rupiah'],
                    (int) $validated['penghasilan_ibu_rupiah'],
                ),
                'jumlah_tanggungan_raw' => (int) $validated['jumlah_tanggungan_raw'],
                'anak_ke_raw' => (int) $validated['anak_ke_raw'],
                'status_orangtua_text' => $validated['status_orangtua_text'],
                'status_rumah_text' => $validated['status_rumah_text'],
                'daya_listrik_text' => $validated['daya_listrik_text'],
                'submitted_pdf_path' => $pdfPath,
                'submitted_pdf_original_name' => $validated['submitted_pdf']->getClientOriginalName(),
                'submitted_pdf_uploaded_at' => now(),
                'status' => 'Submitted',
            ]);

            $this->applicationInferenceService->syncPredictionSnapshot($application, $request->user()->id);

            ApplicationStatusLog::query()->create([
                'application_id' => $application->id,
                'actor_user_id' => $request->user()->id,
                'from_status' => null,
                'to_status' => 'Submitted',
                'action' => 'submitted',
                'metadata' => [
                    'schema_version' => $application->schema_version,
                    'submitted_pdf_original_name' => $application->submitted_pdf_original_name,
                ],
            ]);

            return $application->fresh(['currentEncoding', 'modelSnapshot']);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan berhasil dikirim dan sedang menunggu keputusan admin.',
            'data' => [
                'id' => $application->id,
                'status' => $application->status,
                'submitted_pdf_uploaded_at' => $application->submitted_pdf_uploaded_at,
                'created_at' => $application->created_at,
            ],
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $applications = StudentApplication::query()
            ->where('student_user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get([
                'id',
                'status',
                'admin_decision',
                'admin_decision_note',
                'admin_decided_at',
                'submitted_pdf_uploaded_at',
                'created_at',
                'updated_at',
            ]);

        return response()->json([
            'status' => 'success',
            'data' => $applications,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $application = StudentApplication::query()
            ->with(['logs', 'currentEncoding', 'modelSnapshot'])
            ->where('student_user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $application->id,
                'status' => $application->status,
                'admin_decision' => $application->admin_decision,
                'admin_decision_note' => $application->admin_decision_note,
                'admin_decided_at' => $application->admin_decided_at,
                'submitted_pdf_uploaded_at' => $application->submitted_pdf_uploaded_at,
                'submitted_pdf_url' => Storage::disk('public')->url($application->submitted_pdf_path),
                'encoding' => $application->currentEncoding,
                'model_snapshot' => $application->modelSnapshot,
                'created_at' => $application->created_at,
                'logs' => $application->logs->map(static fn ($log) => [
                    'from_status' => $log->from_status,
                    'to_status' => $log->to_status,
                    'action' => $log->action,
                    'created_at' => $log->created_at,
                ]),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateStoreRequest(Request $request): array
    {
        return $request->validate([
            'kip' => ['required', 'integer', 'in:0,1'],
            'pkh' => ['required', 'integer', 'in:0,1'],
            'kks' => ['required', 'integer', 'in:0,1'],
            'dtks' => ['required', 'integer', 'in:0,1'],
            'sktm' => ['required', 'integer', 'in:0,1'],
            'penghasilan_ayah_rupiah' => ['required', 'integer', 'min:0'],
            'penghasilan_ibu_rupiah' => ['required', 'integer', 'min:0'],
            'jumlah_tanggungan_raw' => ['required', 'integer', 'min:0'],
            'anak_ke_raw' => ['required', 'integer', 'min:1'],
            'status_orangtua_text' => ['required', 'string', 'max:255'],
            'status_rumah_text' => ['required', 'string', 'max:255'],
            'daya_listrik_text' => ['required', 'string', 'max:255'],
            'submitted_pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'schema_version' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    private function resolveCombinedIncome(int $fatherIncome, int $motherIncome): int
    {
        return $fatherIncome + $motherIncome;
    }
}
