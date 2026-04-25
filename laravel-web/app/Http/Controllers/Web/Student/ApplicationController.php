<?php

namespace App\Http\Controllers\Web\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreStudentApplicationRequest;
use App\Services\StudentApplicationPortalService;
use App\Services\StudentApplicationSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly StudentApplicationPortalService $portalService,
        private readonly StudentApplicationSubmissionService $submissionService,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        $existing = $this->portalService->latestForStudent($request->user());

        if ($existing !== null) {
            return redirect()
                ->route('student.applications.show', $existing->id)
                ->with('student_notice', [
                    'type' => 'info',
                    'title' => 'Pengajuan sudah pernah dikirim',
                    'message' => 'Anda hanya dapat mengajukan KIP-K satu kali. Silakan cek status pengajuan Anda di halaman ini.',
                ]);
        }

        return view('pages.student.applications.create', [
            'student' => $request->user(),
            'options' => $this->portalService->formOptions(),
        ]);
    }

    public function store(StoreStudentApplicationRequest $request): RedirectResponse
    {
        $application = $this->submissionService->submit(
            $request->user(),
            $request->validated(),
        );

        return redirect()
            ->route('student.applications.show', $application->id)
            ->with('student_notice', [
                'type' => 'success',
                'title' => 'Pengajuan berhasil dikirim',
                'message' => 'Data Anda sudah masuk ke sistem dan saat ini menunggu keputusan admin. Silakan cek halaman ini secara berkala.',
            ]);
    }

    public function show(Request $request, int $application): View
    {
        $record = $this->portalService->detail($request->user(), $application);

        return view('pages.student.applications.show', [
            'student' => $request->user(),
            'application' => $record,
            'documentUrl' => $this->portalService->documentUrl($record),
        ]);
    }
}
