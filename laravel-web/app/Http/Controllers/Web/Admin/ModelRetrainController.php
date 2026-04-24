<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModelVersion;
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
        $payload = $this->adminModelRetrainService->dashboardPayload();

        return view('pages.admin.models.retrain', [
            'admin' => $request->user(),
            'payload' => $payload,
            'page' => $this->adminModelRetrainService->viewPayload($payload),
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
        $validated = $request->validate([
            'purge_training' => ['nullable', 'boolean'],
        ]);

        $purgeTraining = (bool) ($validated['purge_training'] ?? false);

        try {
            $result = $this->adminModelRetrainService->triggerRetrain($request->user(), $purgeTraining);
        } catch (\Throwable $throwable) {
            return redirect()
                ->route('admin.models.retrain')
                ->with('admin_notice', [
                    'type' => 'error',
                    'title' => 'Retrain model gagal',
                    'message' => $throwable->getMessage(),
                ]);
        }

        $message = $purgeTraining
            ? 'Data training lama dihapus dan model sedang dilatih ulang dari awal. Muat ulang halaman ini dalam beberapa menit.'
            : 'Permintaan mulai diproses di latar belakang. Silakan muat ulang halaman ini dalam beberapa menit untuk melihat hasilnya.';

        return redirect()
            ->route('admin.models.retrain')
            ->with('admin_notice', [
                'type' => 'success',
                'title' => $purgeTraining ? 'Purge & Retrain Diproses' : 'Retrain Model Diproses',
                'message' => $message,
            ]);
    }

    public function activate(Request $request, ModelVersion $modelVersion): RedirectResponse
    {
        try {
            $result = $this->adminModelRetrainService->activateModelVersion($modelVersion, $request->user());
        } catch (\Throwable $throwable) {
            return redirect()
                ->route('admin.models.retrain')
                ->with('admin_notice', [
                    'type' => 'error',
                    'title' => 'Aktivasi versi model gagal',
                    'message' => $throwable->getMessage(),
                ]);
        }

        $activated = $result['activated_version'];

        return redirect()
            ->route('admin.models.retrain')
            ->with('admin_notice', [
                'type' => 'success',
                'title' => 'Versi model aktif diperbarui',
                'message' => "Versi {$activated?->version_name} sekarang menjadi model aktif untuk prediksi berikutnya.",
            ]);
    }
}
