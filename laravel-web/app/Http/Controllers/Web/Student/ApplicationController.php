<?php

namespace App\Http\Controllers\Web\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreStudentApplicationRequest;
use App\Http\Requests\Student\UpdateStudentApplicationRequest;
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

    public function create(Request $request): View
    {
        return view('pages.student.applications.create', [
            'student' => $request->user(),
            'options' => $this->portalService->formOptions(),
            'application' => null,
            'formMode' => 'create',
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
                'message' => 'Data Anda sudah masuk ke sistem dan saat ini menunggu keputusan admin.',
            ]);
    }

    public function show(Request $request, int $application): View
    {
        $record = $this->portalService->detail($request->user(), $application);

        return view('pages.student.applications.show', [
            'student' => $request->user(),
            'application' => $record,
            'documentUrl' => $this->portalService->documentUrl($record),
            'canEdit' => $this->portalService->canEdit($record),
        ]);
    }

    public function edit(Request $request, int $application): View
    {
        $record = $this->portalService->editable($request->user(), $application);

        return view('pages.student.applications.create', [
            'student' => $request->user(),
            'options' => $this->portalService->formOptions(),
            'application' => $record,
            'formMode' => 'edit',
        ]);
    }

    public function update(UpdateStudentApplicationRequest $request, int $application): RedirectResponse
    {
        $record = $this->portalService->editable($request->user(), $application);
        $updated = $this->submissionService->update(
            $request->user(),
            $record,
            $request->validated(),
        );

        return redirect()
            ->route('student.applications.show', $updated->id)
            ->with('student_notice', [
                'type' => 'success',
                'title' => 'Pengajuan berhasil direvisi',
                'message' => 'Perubahan Anda sudah disimpan dan rekomendasi sistem telah diperbarui selama admin belum memberi keputusan final.',
            ]);
    }
}
