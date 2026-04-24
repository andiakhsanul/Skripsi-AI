<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminApplicationReviewService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApplicationReviewController extends Controller
{
    public function __construct(
        private readonly AdminApplicationReviewService $reviewService,
    ) {}

    public function show(Request $request, int $application): View
    {
        $applicationRecord = $this->reviewService->detail($application);
        $documentUrl = $this->reviewService->documentUrl($applicationRecord);

        return view('pages.admin.applications.show', [
            'admin' => $request->user(),
            'application' => $applicationRecord,
            'documentUrl' => $documentUrl,
            'page' => $this->reviewService->viewPayload($applicationRecord, $documentUrl),
        ]);
    }

    public function refreshPrediction(Request $request, int $application): RedirectResponse
    {
        try {
            $this->reviewService->syncPredictionSnapshot($application, $request->user()->id);
        } catch (\Throwable $throwable) {
            return $this->redirectWithNotice($application, 'error', 'Gagal memperbarui rekomendasi', $throwable->getMessage());
        }

        return $this->redirectWithNotice($application, 'success', 'Rekomendasi model diperbarui', 'Snapshot CatBoost dan Naive Bayes berhasil dibuat dari data mentah terbaru.');
    }

    public function verify(Request $request, int $application): RedirectResponse
    {
        return $this->finalize($request, $application, 'Verified');
    }

    public function reject(Request $request, int $application): RedirectResponse
    {
        return $this->finalize($request, $application, 'Rejected');
    }

    public function confirmAi(Request $request, int $application): RedirectResponse
    {
        $validated = $request->validate([
            'ai_correct' => ['required', 'boolean'],
            'correction_note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $result = $this->reviewService->confirmAiRecommendation(
                $application,
                $request->user()->id,
                (bool) $validated['ai_correct'],
                $validated['correction_note'] ?? null,
            );

            $title = $result['ai_correct']
                ? 'Hasil AI dikonfirmasi benar'
                : 'Hasil AI dikonfirmasi salah';
            $message = $result['ai_correct']
                ? 'Data training sudah ditandai valid dan siap digunakan untuk pelatihan ulang model.'
                : 'Label mengikuti keputusan admin. Data training sudah ditandai valid.';

            return $this->redirectWithNotice($application, 'success', $title, $message);
        } catch (DomainException $domainException) {
            return $this->redirectWithNotice($application, 'error', 'Konfirmasi AI gagal', $domainException->getMessage());
        } catch (\Throwable $throwable) {
            return $this->redirectWithNotice($application, 'error', 'Terjadi kesalahan', $throwable->getMessage());
        }
    }

    public function runPredictions(Request $request): RedirectResponse
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

        $type = $result['failed'] > 0 ? 'error' : 'success';
        $title = $result['failed'] > 0 ? 'Batch rekomendasi selesai dengan catatan' : 'Batch rekomendasi selesai';
        $message = "Diproses {$result['processed']} pengajuan, berhasil {$result['succeeded']}, gagal {$result['failed']}.";

        return redirect()
            ->route('admin.dashboard')
            ->with('admin_notice', compact('type', 'title', 'message'));
    }

    private function finalize(Request $request, int $application, string $status): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->reviewService->finalize(
                $application,
                $request->user()->id,
                $status,
                $validated['note'] ?? null,
            );
        } catch (DomainException $domainException) {
            return $this->redirectWithNotice($application, 'error', 'Keputusan tidak dapat disimpan', $domainException->getMessage());
        } catch (\Throwable $throwable) {
            return $this->redirectWithNotice($application, 'error', 'Terjadi kesalahan', $throwable->getMessage());
        }

        $title = $status === 'Verified' ? 'Pengajuan diverifikasi' : 'Pengajuan ditolak';
        $message = $status === 'Verified'
            ? 'Keputusan final admin tersimpan dan data training untuk pengajuan ini sudah disiapkan.'
            : 'Keputusan final admin tersimpan sebagai indikasi dan data training untuk pengajuan ini sudah disiapkan.';

        return $this->redirectWithNotice($application, 'success', $title, $message);
    }

    private function redirectWithNotice(int $application, string $type, string $title, string $message): RedirectResponse
    {
        return redirect()
            ->route('admin.applications.show', $application)
            ->with('admin_notice', compact('type', 'title', 'message'));
    }
}
