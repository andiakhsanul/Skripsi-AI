<?php

namespace App\Http\Controllers;

use App\Models\ApplicationStatusLog;
use App\Models\ParameterSchemaVersion;
use App\Models\StudentApplication;
use App\Services\EncodingService;
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
        private readonly EncodingService $encodingService,
    ) {}

    /**
     * Submit pengajuan baru.
     * Mahasiswa mengirim nilai RAW (Rupiah, jumlah orang, string status).
     * Server melakukan encoding lalu meneruskan ke Flask ML API.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Biner (Ya/Tidak)
            'kip'  => ['required', 'boolean'],
            'pkh'  => ['required', 'boolean'],
            'kks'  => ['required', 'boolean'],
            'dtks' => ['required', 'boolean'],
            'sktm' => ['required', 'boolean'],

            // Penghasilan RAW (Rupiah)
            'penghasilan_gabungan_raw' => ['required', 'integer', 'min:0'],
            'penghasilan_ayah_raw'     => ['required', 'integer', 'min:0'],
            'penghasilan_ibu_raw'      => ['required', 'integer', 'min:0'],

            // Tanggungan & urutan anak
            'jumlah_tanggungan_raw' => ['required', 'integer', 'min:0', 'max:20'],
            'anak_ke_raw'           => ['required', 'integer', 'min:1', 'max:20'],

            // Status string
            'status_orangtua_raw' => ['required', 'string', 'in:Lengkap,Yatim,Piatu,Yatim Piatu'],
            'status_rumah_raw'    => ['required', 'string', 'in:Milik Sendiri,Sewa,Menumpang,Tidak Punya'],
            'daya_listrik_raw'    => ['required', 'string', 'in:PLN >900VA,PLN 450-900VA,Non-PLN'],

            // PDF Ditmawa wajib
            'ditmawa_pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],

            // Opsional
            'schema_version'   => ['nullable', 'integer', 'min:1'],
            'parameters_extra' => ['nullable', 'array'],
        ]);

        // 1. Encode raw → angka (1/2/3 dan 0/1)
        $encoded = $this->encodingService->encode($validated);

        // 2. Resolve schema version
        $schemaVersion = $this->schemaService->resolveSchemaVersion($validated['schema_version'] ?? null);
        $schema = ParameterSchemaVersion::query()->where('version', $schemaVersion)->first();

        // 3. Upload PDF Ditmawa (wajib)
        $pdfPath = $request->file('ditmawa_pdf')->store('ditmawa-documents', 'public');

        // 4. Prediksi ML menggunakan nilai encoded
        $inference = $this->mlGateway->predictOrFallback($encoded);

        // 5. Rule scoring menggunakan nilai encoded
        $extraParameters = $validated['parameters_extra'] ?? [];
        $ruleScore = $this->ruleScoringService->score($encoded, $extraParameters, $schema);

        // 6. Hitung review priority gabungan (ML + rule)
        $reviewPriority = $inference['review_priority'] ?? 'normal';
        if (
            isset($ruleScore['rule_recommendation'], $inference['catboost_label'])
            && $ruleScore['rule_recommendation'] !== null
            && $ruleScore['rule_recommendation'] !== ($inference['catboost_label'] ?? null)
        ) {
            $reviewPriority = 'high';
        }

        // 7. Simpan ke student_applications (raw + encoded + AI result)
        $application = StudentApplication::query()->create([
            'student_user_id' => $request->user()->id,
            'schema_version'  => $schemaVersion,

            // Raw input
            'penghasilan_gabungan_raw' => (int) $validated['penghasilan_gabungan_raw'],
            'penghasilan_ayah_raw'     => (int) $validated['penghasilan_ayah_raw'],
            'penghasilan_ibu_raw'      => (int) $validated['penghasilan_ibu_raw'],
            'jumlah_tanggungan_raw'    => (int) $validated['jumlah_tanggungan_raw'],
            'anak_ke_raw'              => (int) $validated['anak_ke_raw'],
            'status_orangtua_raw'      => $validated['status_orangtua_raw'],
            'status_rumah_raw'         => $validated['status_rumah_raw'],
            'daya_listrik_raw'         => $validated['daya_listrik_raw'],

            // Encoded
            ...$encoded,

            // PDF Ditmawa
            'ditmawa_pdf_path'        => $pdfPath,
            'ditmawa_pdf_uploaded_at' => now(),

            // Metadata
            'parameters_extra' => $extraParameters,
            'status'           => 'Submitted',

            // AI result
            'model_ready'          => (bool) ($inference['model_ready'] ?? false),
            'catboost_label'       => $inference['catboost_label'] ?? null,
            'catboost_confidence'  => $inference['catboost_confidence'] ?? null,
            'naive_bayes_label'    => $inference['naive_bayes_label'] ?? null,
            'naive_bayes_confidence' => $inference['naive_bayes_confidence'] ?? null,
            'disagreement_flag'    => (bool) ($inference['disagreement_flag'] ?? false),
            'final_recommendation' => $inference['final_recommendation'] ?? null,
            'review_priority'      => $reviewPriority,

            // Rule scoring
            'rule_score'           => $ruleScore['rule_score'] ?? null,
            'rule_recommendation'  => $ruleScore['rule_recommendation'] ?? null,
        ]);

        // 8. Set link dokumen
        $application->forceFill([
            'document_submission_link' => url("/api/student/applications/{$application->id}/document"),
        ])->save();

        // 9. Log status
        ApplicationStatusLog::query()->create([
            'application_id' => $application->id,
            'actor_user_id'  => $request->user()->id,
            'from_status'    => null,
            'to_status'      => 'Submitted',
            'action'         => 'submitted',
            'metadata'       => [
                'final_recommendation' => $application->final_recommendation,
                'model_ready'          => $application->model_ready,
                'rule_score'           => $application->rule_score,
            ],
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Pengajuan berhasil dikirim. Silakan tunggu hasil verifikasi dari admin.',
            'data'    => [
                'id'                      => $application->id,
                'status'                  => $application->status,
                'ditmawa_pdf_uploaded_at' => $application->ditmawa_pdf_uploaded_at,
                'created_at'              => $application->created_at,
            ],
        ], 201);
    }

    /**
     * Daftar pengajuan milik mahasiswa yang sedang login.
     * Hanya menampilkan status — bukan hasil AI.
     */
    public function index(Request $request): JsonResponse
    {
        $applications = StudentApplication::query()
            ->where('student_user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get([
                'id', 'status', 'review_priority',
                'ditmawa_pdf_uploaded_at',
                'admin_decision_note', 'admin_decided_at',
                'created_at', 'updated_at',
            ]);

        return response()->json([
            'status' => 'success',
            'data'   => $applications,
        ]);
    }

    /**
     * Detail satu pengajuan — mahasiswa hanya melihat status & log perubahan.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $application = StudentApplication::query()
            ->with('logs')
            ->where('student_user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'                     => $application->id,
                'status'                 => $application->status,
                'review_priority'        => $application->review_priority,
                'admin_decision_note'    => $application->admin_decision_note,
                'admin_decided_at'       => $application->admin_decided_at,
                'ditmawa_pdf_uploaded'   => $application->hasDitmawaPdf(),
                'ditmawa_pdf_uploaded_at' => $application->ditmawa_pdf_uploaded_at,
                'created_at'             => $application->created_at,
                'logs'                   => $application->logs->map(fn ($log) => [
                    'from_status' => $log->from_status,
                    'to_status'   => $log->to_status,
                    'action'      => $log->action,
                    'created_at'  => $log->created_at,
                ]),
            ],
        ]);
    }
}
