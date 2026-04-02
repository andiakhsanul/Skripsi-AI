<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ApplicationStatusLog;
use App\Models\ParameterSchemaVersion;
use App\Models\StudentApplication;
use App\Services\ApplicationModelSnapshotService;
use App\Services\MlGatewayService;
use App\Services\ParameterSchemaService;
use App\Services\RuleScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly ParameterSchemaService $schemaService,
        private readonly MlGatewayService $mlGateway,
        private readonly RuleScoringService $ruleScoringService,
        private readonly ApplicationModelSnapshotService $modelSnapshotService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateStoreRequest($request);
        $schemaVersion = $this->schemaService->resolveSchemaVersion($validated['schema_version'] ?? null);
        $schema = ParameterSchemaVersion::query()->where('version', $schemaVersion)->first();
        $features = Arr::only($validated, StudentApplication::featureColumns());

        $this->schemaService->validateApplicationPayload($features, [], $schema);

        $pdfPath = $validated['submitted_pdf']->store('student-application-pdfs', 'public');
        $inference = $this->mlGateway->predictOrFallback($features);
        $ruleScore = $this->ruleScoringService->score($features, [], $schema);
        $reviewPriority = $this->resolveReviewPriority($inference, $ruleScore);

        $application = DB::transaction(function () use (
            $request,
            $schemaVersion,
            $validated,
            $features,
            $pdfPath,
            $inference,
            $ruleScore,
            $reviewPriority,
        ): StudentApplication {
            $application = StudentApplication::query()->create([
                'student_user_id' => $request->user()->id,
                'schema_version' => $schemaVersion,
                ...$features,
                'submitted_pdf_path' => $pdfPath,
                'submitted_pdf_original_name' => $validated['submitted_pdf']->getClientOriginalName(),
                'submitted_pdf_uploaded_at' => now(),
                'status' => 'Submitted',
            ]);

            $this->modelSnapshotService->syncFromApplication(
                $application,
                $features,
                $inference,
                $ruleScore,
                $reviewPriority,
            );

            ApplicationStatusLog::query()->create([
                'application_id' => $application->id,
                'actor_user_id' => $request->user()->id,
                'from_status' => null,
                'to_status' => 'Submitted',
                'action' => 'submitted',
                'metadata' => [
                    'schema_version' => $application->schema_version,
                    'submitted_pdf_original_name' => $application->submitted_pdf_original_name,
                    'review_priority' => $reviewPriority,
                ],
            ]);

            return $application;
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
            ->with('logs')
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
            'penghasilan_gabungan' => ['required', 'integer', 'in:1,2,3'],
            'penghasilan_ayah' => ['required', 'integer', 'in:1,2,3'],
            'penghasilan_ibu' => ['required', 'integer', 'in:1,2,3'],
            'jumlah_tanggungan' => ['required', 'integer', 'in:1,2,3'],
            'anak_ke' => ['required', 'integer', 'in:1,2,3'],
            'status_orangtua' => ['required', 'integer', 'in:1,2,3'],
            'status_rumah' => ['required', 'integer', 'in:1,2,3'],
            'daya_listrik' => ['required', 'integer', 'in:1,2,3'],
            'submitted_pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'schema_version' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    /**
     * @param array<string, mixed> $inference
     * @param array<string, mixed> $ruleScore
     */
    private function resolveReviewPriority(array $inference, array $ruleScore): string
    {
        $reviewPriority = (string) ($inference['review_priority'] ?? 'normal');

        if (
            isset($ruleScore['rule_recommendation'], $inference['catboost_label'])
            && $ruleScore['rule_recommendation'] !== null
            && $ruleScore['rule_recommendation'] !== ($inference['catboost_label'] ?? null)
        ) {
            return 'high';
        }

        return $reviewPriority;
    }
}
