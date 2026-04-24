<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminTrainingDataReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TrainingDataCorrectionController extends Controller
{
    public function __construct(
        private readonly AdminTrainingDataReviewService $trainingDataReviewService,
    ) {
    }

    public function show(Request $request, int $application): View|RedirectResponse
    {
        try {
            $detail = $this->trainingDataReviewService->detail($application);
        } catch (ValidationException $validationException) {
            return redirect()
                ->route('admin.applications.show', $application)
                ->with('admin_notice', [
                    'type' => 'error',
                    'title' => 'Data training belum tersedia',
                    'message' => $validationException->getMessage(),
                ]);
        }

        return view('pages.admin.training-data.show', [
            'admin' => $request->user(),
            'application' => $detail['application'],
            'trainingRow' => $detail['training_row'],
            'legend' => $detail['legend'],
            'fieldOptions' => $detail['field_options'],
            'page' => $detail['view_payload'],
        ]);
    }

    public function update(Request $request, int $application): RedirectResponse
    {
        $validated = $request->validate([
            'kip' => ['required', 'integer', 'in:0,1'],
            'pkh' => ['required', 'integer', 'in:0,1'],
            'kks' => ['required', 'integer', 'in:0,1'],
            'dtks' => ['required', 'integer', 'in:0,1'],
            'sktm' => ['required', 'integer', 'in:0,1'],
            'penghasilan_gabungan' => ['required', 'integer', 'in:1,2,3,4,5'],
            'penghasilan_ayah' => ['required', 'integer', 'in:1,2,3,4,5'],
            'penghasilan_ibu' => ['required', 'integer', 'in:1,2,3,4,5'],
            'jumlah_tanggungan' => ['required', 'integer', 'in:1,2,3,4,5'],
            'anak_ke' => ['required', 'integer', 'in:1,2,3,4,5'],
            'status_orangtua' => ['required', 'integer', 'in:1,2,3'],
            'status_rumah' => ['required', 'integer', 'in:1,2,3,4'],
            'daya_listrik' => ['required', 'integer', 'in:1,2,3,4,5'],
            'label' => ['required', 'string', 'in:Layak,Indikasi'],
            'correction_note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->trainingDataReviewService->update($application, $validated);
        } catch (ValidationException $validationException) {
            return redirect()
                ->route('admin.training-data.show', $application)
                ->withErrors($validationException->errors())
                ->withInput();
        }

        return redirect()
            ->route('admin.training-data.show', $application)
            ->with('admin_notice', [
                'type' => 'success',
                'title' => 'Data training diperbarui',
                'message' => 'Perubahan tersimpan. Retrain berikutnya akan menggunakan nilai koreksi terbaru.',
            ]);
    }
}
