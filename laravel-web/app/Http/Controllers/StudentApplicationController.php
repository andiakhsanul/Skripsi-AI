<?php

namespace App\Http\Controllers;

use App\Models\ApplicationStatusLog;
use App\Models\ParameterSchemaVersion;
use App\Models\StudentApplication;
use App\Services\MlGatewayService;
use App\Services\ParameterSchemaService;
use App\Services\RuleScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StudentApplicationController extends Controller
{
    public function __construct(
        private readonly ParameterSchemaService $schemaService,
        private readonly MlGatewayService $mlGateway,
        private readonly RuleScoringService $ruleScoringService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'schema_version' => ['nullable', 'integer', 'min:1'],
            'kip' => ['required', 'integer', 'in:0,1'],
            'pkh' => ['required', 'integer', 'in:0,1'],
            'kks' => ['required', 'integer', 'in:0,1'],
            'dtks' => ['required', 'integer', 'in:0,1'],
            'sktm' => ['required', 'integer', 'in:0,1'],
            'penghasilan_gabungan' => ['required', 'integer', 'in:1,2,3'],
            'penghasilan_ayah' => ['required', 'integer', 'in:1,2,3'],
            'penghasilan_ibu' => ['required', 'integer', 'in:1,2,3'],
            'jumlah_tanggungan' => ['required', 'integer', 'in:1,2,3'],
            'anak_ke' => ['required', 'integer', 'in:1,2,3'],
            'status_orangtua' => ['required', 'integer', 'in:1,2,3'],
            'status_rumah' => ['required', 'integer', 'in:1,2,3'],
            'daya_listrik' => ['required', 'integer', 'in:1,2,3'],
            'parameters_extra' => ['nullable', 'array'],
            'supporting_document_url' => ['nullable', 'url', 'max:2048'],
            'supporting_document_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $schemaVersion = $this->schemaService->resolveSchemaVersion($validated['schema_version'] ?? null);
        $schema = ParameterSchemaVersion::query()->where('version', $schemaVersion)->first();

        $coreParameters = [
            'kip' => (int) $validated['kip'],
            'pkh' => (int) $validated['pkh'],
            'kks' => (int) $validated['kks'],
            'dtks' => (int) $validated['dtks'],
            'sktm' => (int) $validated['sktm'],
            'penghasilan_gabungan' => (int) $validated['penghasilan_gabungan'],
            'penghasilan_ayah' => (int) $validated['penghasilan_ayah'],
            'penghasilan_ibu' => (int) $validated['penghasilan_ibu'],
            'jumlah_tanggungan' => (int) $validated['jumlah_tanggungan'],
            'anak_ke' => (int) $validated['anak_ke'],
            'status_orangtua' => (int) $validated['status_orangtua'],
            'status_rumah' => (int) $validated['status_rumah'],
            'daya_listrik' => (int) $validated['daya_listrik'],
        ];

        $extraParameters = $validated['parameters_extra'] ?? [];

        $this->schemaService->validateApplicationPayload($coreParameters, $extraParameters, $schema);
        $inference = $this->mlGateway->predictOrFallback($coreParameters);
        $ruleScore = $this->ruleScoringService->score($coreParameters, $extraParameters, $schema);

        $reviewPriority = $inference['review_priority'] ?? 'normal';
        if (
            isset($ruleScore['rule_recommendation'], $inference['catboost_label'])
            && $ruleScore['rule_recommendation'] !== null
            && $ruleScore['rule_recommendation'] !== ($inference['catboost_label'] ?? null)
        ) {
            $reviewPriority = 'high';
        }

        $documentPath = null;
        $documentUrl = $validated['supporting_document_url'] ?? null;
        if ($request->hasFile('supporting_document_pdf')) {
            $documentPath = $request->file('supporting_document_pdf')->store('application-documents', 'public');
            $documentUrl = Storage::disk('public')->url($documentPath);
        }

        $application = StudentApplication::query()->create([
            'student_user_id' => $request->user()->id,
            'schema_version' => $schemaVersion,
            ...$coreParameters,
            'parameters_extra' => $extraParameters,
            'status' => 'Submitted',
            'model_ready' => (bool) ($inference['model_ready'] ?? false),
            'catboost_label' => $inference['catboost_label'] ?? null,
            'catboost_confidence' => $inference['catboost_confidence'] ?? null,
            'naive_bayes_label' => $inference['naive_bayes_label'] ?? null,
            'naive_bayes_confidence' => $inference['naive_bayes_confidence'] ?? null,
            'disagreement_flag' => (bool) ($inference['disagreement_flag'] ?? false),
            'rule_score' => $ruleScore['rule_score'] ?? null,
            'rule_recommendation' => $ruleScore['rule_recommendation'] ?? null,
            'final_recommendation' => $inference['final_recommendation'] ?? ($ruleScore['rule_recommendation'] ?? null),
            'review_priority' => $reviewPriority,
            'supporting_document_url' => $documentUrl,
            'supporting_document_path' => $documentPath,
        ]);

        $application->forceFill([
            'document_submission_link' => url("/api/student/applications/{$application->id}/document"),
        ])->save();

        ApplicationStatusLog::query()->create([
            'application_id' => $application->id,
            'actor_user_id' => $request->user()->id,
            'from_status' => null,
            'to_status' => 'Submitted',
            'action' => 'submitted',
            'metadata' => [
                'final_recommendation' => $application->final_recommendation,
                'model_ready' => $application->model_ready,
                'rule_score' => $application->rule_score,
            ],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan berhasil dikirim',
            'data' => $application,
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $applications = StudentApplication::query()
            ->where('student_user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $applications,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $application = StudentApplication::query()
            ->with('logs')
            ->where('student_user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $application,
        ]);
    }

    public function uploadDocument(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'supporting_document_pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $application = StudentApplication::query()
            ->where('student_user_id', $request->user()->id)
            ->findOrFail($id);

        if ($application->supporting_document_path) {
            Storage::disk('public')->delete($application->supporting_document_path);
        }

        $path = $validated['supporting_document_pdf']->store('application-documents', 'public');
        $url = Storage::disk('public')->url($path);

        $application->forceFill([
            'supporting_document_path' => $path,
            'supporting_document_url' => $url,
        ])->save();

        ApplicationStatusLog::query()->create([
            'application_id' => $application->id,
            'actor_user_id' => $request->user()->id,
            'from_status' => $application->status,
            'to_status' => $application->status,
            'action' => 'upload_document',
            'metadata' => [
                'supporting_document_url' => $url,
            ],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Dokumen PDF berhasil diunggah',
            'data' => [
                'id' => $application->id,
                'supporting_document_url' => $application->supporting_document_url,
                'document_submission_link' => $application->document_submission_link,
            ],
        ]);
    }
}
