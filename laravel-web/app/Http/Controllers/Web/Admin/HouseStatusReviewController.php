<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentApplication;
use App\Services\AdminHouseStatusReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HouseStatusReviewController extends Controller
{
    public function __construct(
        private readonly AdminHouseStatusReviewService $houseStatusReviewService,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'house_state' => ['nullable', 'string', Rule::in(['missing', 'filled'])],
        ]);

        $viewFilters = [
            'q' => $filters['q'] ?? '',
            'house_state' => $filters['house_state'] ?? 'missing',
        ];

        $summary = $this->houseStatusReviewService->summary();

        return view('pages.admin.applications.house-review', [
            'admin' => $request->user(),
            'filters' => $viewFilters,
            'summary' => $summary,
            'page' => $this->houseStatusReviewService->viewPayload($summary),
            'applications' => $this->houseStatusReviewService->paginateApplications($viewFilters, 20),
            'notice' => session('admin_notice'),
        ]);
    }

    public function update(Request $request, StudentApplication $application): RedirectResponse
    {
        abort_unless($application->isOfflineImport(), 404);

        $validated = $request->validate([
            'status_rumah_text' => ['nullable', 'string', 'max:255', Rule::in($this->houseStatusReviewService->houseStatusOptions())],
        ]);

        $updatedApplication = $this->houseStatusReviewService->updateHouseStatus(
            $application,
            $validated['status_rumah_text'] ?? null,
            $request->user()->id,
        );

        $message = blank($updatedApplication->status_rumah_text)
            ? 'Status rumah dikosongkan kembali. Data training tetap belum disentuh.'
            : 'Status rumah berhasil diperbarui pada data mentah. Artefak model lama untuk pengajuan ini dibersihkan bila sebelumnya sudah ada.';

        return redirect()
            ->route('admin.applications.house-review', $request->query())
            ->with('admin_notice', [
                'type' => 'success',
                'title' => 'Status rumah diperbarui',
                'message' => $message,
            ]);
    }

    public function batchUpdate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'house_state' => ['nullable', 'string', Rule::in(['missing', 'filled'])],
            'applications' => ['required', 'array', 'min:1'],
            'applications.*.id' => ['required', 'integer', 'distinct', Rule::exists('student_applications', 'id')],
            'applications.*.status_rumah_text' => ['nullable', 'string', 'max:255', Rule::in($this->houseStatusReviewService->houseStatusOptions())],
        ]);

        $result = $this->houseStatusReviewService->batchUpdateHouseStatuses(
            array_values($validated['applications']),
            (int) $request->user()->id,
        );

        $message = $result['updated'] === 0
            ? 'Tidak ada perubahan status rumah pada halaman ini.'
            : "Tersimpan {$result['updated']} perubahan. Artefak lama yang dibersihkan: {$result['cleared_snapshots']} snapshot, {$result['cleared_encodings']} encoding, {$result['cleared_training_rows']} baris training.";

        return redirect()
            ->route('admin.applications.house-review', array_filter([
                'q' => $validated['q'] ?? null,
                'house_state' => $validated['house_state'] ?? null,
            ]))
            ->with('admin_notice', [
                'type' => 'success',
                'title' => 'Perubahan halaman tersimpan',
                'message' => $message,
            ]);
    }
}
