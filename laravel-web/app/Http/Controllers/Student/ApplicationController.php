<?php

namespace App\Http\Controllers\Student;

use App\Http\Requests\Student\StoreStudentApplicationRequest;
use App\Http\Controllers\Controller;
use App\Models\StudentApplication;
use App\Services\StudentApplicationSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly StudentApplicationSubmissionService $submissionService,
    ) {}

    public function store(StoreStudentApplicationRequest $request): JsonResponse
    {
        $application = $this->submissionService->submit(
            $request->user(),
            $request->validated(),
        );

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

}
