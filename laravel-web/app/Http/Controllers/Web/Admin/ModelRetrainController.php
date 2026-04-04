<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminModelRetrainService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModelRetrainController extends Controller
{
    public function __construct(
        private readonly AdminModelRetrainService $adminModelRetrainService,
    ) {}

    public function index(Request $request): View
    {
        return view('pages.admin.models.retrain', [
            'admin' => $request->user(),
            'payload' => $this->adminModelRetrainService->dashboardPayload(),
        ]);
    }

    public function syncTraining(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'force_resync' => ['nullable', 'boolean'],
        ]);

        $result = $this->adminModelRetrainService->syncTrainingData(
            $request->user()->id,
            (bool) ($validated['force_resync'] ?? false),
        );

        return redirect()
            ->route('admin.models.retrain')
            ->with('admin_notice', [
                'type' => 'success',
                'title' => 'Sinkronisasi data training berhasil',
                'message' => "Diproses {$result['processed']} data final, tersinkron {$result['synced']}, dilewati {$result['skipped']}.",
            ]);
    }

    public function retrain(Request $request): RedirectResponse
    {
        try {
            $result = $this->adminModelRetrainService->triggerRetrain($request->user());
        } catch (\Throwable $throwable) {
            return redirect()
                ->route('admin.models.retrain')
                ->with('admin_notice', [
                    'type' => 'error',
                    'title' => 'Retrain model gagal',
                    'message' => $throwable->getMessage(),
                ]);
        }

        $trainingSummary = $result['ml_response']['training_summary'] ?? [];
        $rowsUsed = $trainingSummary['rows_used'] ?? $result['training_rows'];

        return redirect()
            ->route('admin.models.retrain')
            ->with('admin_notice', [
                'type' => 'success',
                'title' => 'Retrain model berhasil',
                'message' => "Schema v{$result['schema_version']} dilatih ulang menggunakan {$rowsUsed} data aktif.",
            ]);
    }
}
