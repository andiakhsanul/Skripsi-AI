<?php

namespace App\Services;

use App\Models\ApplicationModelSnapshot;
use App\Models\ModelVersion;
use App\Models\SpkTrainingData;
use App\Models\StudentApplication;
use App\Models\User;
use Illuminate\Support\Collection;
use RuntimeException;

class AdminModelRetrainService
{
    public function __construct(
        private readonly TrainingDataSyncService $trainingDataSyncService,
        private readonly MlGatewayService $mlGatewayService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboardPayload(): array
    {
        $finalizedApplications = StudentApplication::query()
            ->whereIn('status', ['Verified', 'Rejected'])
            ->count();

        $trainingRows = SpkTrainingData::query()
            ->where('is_active', true)
            ->count();

        $trainingCorrections = SpkTrainingData::query()
            ->where('admin_corrected', true)
            ->count();

        $pendingConfirmation = SpkTrainingData::query()
            ->where('is_active', true)
            ->where('admin_corrected', false)
            ->count();

        $predictionSnapshots = ApplicationModelSnapshot::query()->count();
        $applicationsWithoutSnapshot = StudentApplication::query()
            ->whereDoesntHave('modelSnapshot')
            ->count();

        $activeModel = $this->currentActiveModel();
        $latestReadyModel = $this->latestReadyModel();
        $latestAttempt = $this->latestAttempt();

        return [
            'finalized_applications' => $finalizedApplications,
            'training_rows' => $trainingRows,
            'training_gap' => max($finalizedApplications - $trainingRows, 0),
            'training_corrections' => $trainingCorrections,
            'confirmed_training_rows' => $trainingCorrections,
            'pending_confirmation' => $pendingConfirmation,
            'prediction_snapshots' => $predictionSnapshots,
            'applications_without_snapshot' => $applicationsWithoutSnapshot,
            'label_distribution' => $this->labelDistribution(),
            'active_model' => $activeModel,
            'active_model_evaluation' => $this->buildEvaluationPayload($activeModel),
            'latest_ready_model' => $latestReadyModel,
            'latest_ready_model_evaluation' => $this->buildEvaluationPayload($latestReadyModel),
            'latest_attempt' => $latestAttempt,
            'recent_model_versions' => $this->recentModelVersions(),
            'recent_model_versions_view' => $this->buildRecentVersionsPayload($this->recentModelVersions()),
            'system_notes' => $this->systemNotes(
                $activeModel,
                $latestAttempt,
                $finalizedApplications,
                $trainingRows,
                $applicationsWithoutSnapshot,
            ),
            'model_status' => [
                'ready' => $activeModel !== null,
                'label' => $activeModel !== null ? 'SIAP' : 'BELUM SIAP',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function viewPayload(array $payload): array
    {
        $modelStatus = $payload['model_status'];

        return [
            'status_tone_classes' => $modelStatus['ready']
                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                : 'border-yellow-200 bg-yellow-50 text-yellow-700',
            'status_dot_classes' => $modelStatus['ready'] ? 'bg-emerald-500' : 'bg-yellow-500',
            'note_tone_classes' => [
                'success' => ['dot' => 'bg-emerald-500', 'pill' => 'bg-emerald-50 text-emerald-700'],
                'warning' => ['dot' => 'bg-yellow-500', 'pill' => 'bg-yellow-50 text-yellow-700'],
                'info' => ['dot' => 'bg-primary', 'pill' => 'bg-primary/10 text-primary'],
            ],
            'cards' => [
                [
                    'label' => 'Pengajuan Final',
                    'value' => number_format($payload['finalized_applications']),
                    'hint' => 'Sudah diputuskan admin.',
                    'border' => 'border-primary',
                    'icon_wrap' => 'bg-primary/10 text-primary',
                    'icon' => 'fact_check',
                ],
                [
                    'label' => 'Data Siap Dilatih',
                    'value' => number_format($payload['training_rows']),
                    'hint' => 'Siap dipakai pada pelatihan berikutnya.',
                    'border' => 'border-emerald-500',
                    'icon_wrap' => 'bg-emerald-50 text-emerald-600',
                    'icon' => 'database',
                ],
                [
                    'label' => 'Belum Tersalin',
                    'value' => number_format($payload['training_gap']),
                    'hint' => 'Masih perlu disinkronkan.',
                    'border' => 'border-yellow-500',
                    'icon_wrap' => 'bg-yellow-50 text-yellow-700',
                    'icon' => 'sync_problem',
                ],
                [
                    'label' => 'Hasil Rekomendasi',
                    'value' => number_format($payload['prediction_snapshots']),
                    'hint' => 'Pengajuan yang sudah punya rekomendasi sistem.',
                    'border' => 'border-slate-800',
                    'icon_wrap' => 'bg-slate-100 text-slate-700',
                    'icon' => 'analytics',
                ],
                [
                    'label' => 'Dikonfirmasi Admin',
                    'value' => number_format($payload['confirmed_training_rows'] ?? 0),
                    'hint' => 'Training data yang sudah dikonfirmasi admin.',
                    'border' => 'border-emerald-500',
                    'icon_wrap' => 'bg-emerald-50 text-emerald-600',
                    'icon' => 'verified',
                ],
                [
                    'label' => 'Menunggu Konfirmasi',
                    'value' => number_format($payload['pending_confirmation'] ?? 0),
                    'hint' => 'Training data yang belum dikonfirmasi admin.',
                    'border' => 'border-amber-500',
                    'icon_wrap' => 'bg-amber-50 text-amber-700',
                    'icon' => 'schedule',
                ],
            ],
        ];
    }

    /**
     * @return array{processed:int, synced:int, skipped:int}
     */
    public function syncTrainingData(int $adminUserId, bool $forceResync = false): array
    {
        return $this->trainingDataSyncService->syncFinalizedApplications($adminUserId, $forceResync);
    }

    /**
     * @return array<string, mixed>
     */
    public function triggerRetrain(User $admin, bool $purgeTraining = false): array
    {
        $trainingRows = SpkTrainingData::query()
            ->where('is_active', true)
            ->count();

        if ($trainingRows === 0 && ! $purgeTraining) {
            throw new RuntimeException('Belum ada data training aktif. Sinkronkan data final terlebih dahulu.');
        }

        $mlResponse = $this->mlGatewayService->retrain([
            'triggered_by_user_id' => $admin->id,
            'triggered_by_email' => $admin->email,
            'purge_training' => $purgeTraining,
        ]);

        return [
            'training_rows' => $trainingRows,
            'purge_training' => $purgeTraining,
            'ml_response' => $mlResponse,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function trainingStatus(): array
    {
        return $this->mlGatewayService->trainingStatus();
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelTraining(): array
    {
        return $this->mlGatewayService->cancelTraining();
    }

    /**
     * @return array<string, mixed>
     */
    public function activateModelVersion(ModelVersion $modelVersion, User $admin): array
    {
        if ($modelVersion->status !== 'ready') {
            throw new RuntimeException('Hanya versi model dengan status siap yang dapat diaktifkan.');
        }

        $mlResponse = $this->mlGatewayService->activateModelVersion($modelVersion->id);

        return [
            'activated_version' => $modelVersion->fresh(),
            'triggered_by' => $admin->email,
            'ml_response' => $mlResponse,
        ];
    }

    /**
     * @return array{layak:int, indikasi:int}
     */
    private function labelDistribution(): array
    {
        $counts = SpkTrainingData::query()
            ->where('is_active', true)
            ->selectRaw('label_class, COUNT(*) AS total')
            ->groupBy('label_class')
            ->pluck('total', 'label_class');

        return [
            'layak' => (int) ($counts[0] ?? 0),
            'indikasi' => (int) ($counts[1] ?? 0),
        ];
    }

    /**
     * @return Collection<int, ModelVersion>
     */
    private function recentModelVersions(int $limit = 6): Collection
    {
        return ModelVersion::query()
            ->with('triggeredBy:id,name,email')
            ->where('status', '!=', 'training')
            ->orderByRaw('COALESCE(trained_at, created_at) DESC, id DESC')
            ->limit($limit)
            ->get();
    }

    private function latestReadyModel(): ?ModelVersion
    {
        return ModelVersion::query()
            ->with('triggeredBy:id,name,email')
            ->where('status', 'ready')
            ->orderByRaw('COALESCE(trained_at, created_at) DESC, id DESC')
            ->first();
    }

    private function currentActiveModel(): ?ModelVersion
    {
        return ModelVersion::query()
            ->with('triggeredBy:id,name,email')
            ->where('status', 'ready')
            ->where('is_current', true)
            ->orderByRaw('COALESCE(activated_at, trained_at, created_at) DESC, id DESC')
            ->first()
            ?? $this->latestReadyModel();
    }

    private function latestAttempt(): ?ModelVersion
    {
        return ModelVersion::query()
            ->with('triggeredBy:id,name,email')
            ->where('status', '!=', 'training')
            ->orderByRaw('COALESCE(trained_at, created_at) DESC, id DESC')
            ->first();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function systemNotes(
        ?ModelVersion $activeModel,
        ?ModelVersion $latestAttempt,
        int $finalizedApplications,
        int $trainingRows,
        int $applicationsWithoutSnapshot,
    ): array {
        $notes = [];

        if ($activeModel) {
            $notes[] = [
                'tone' => 'success',
                'message' => "Versi {$activeModel->version_name} sedang dipakai untuk rekomendasi terbaru.",
                'actor' => $activeModel->triggeredBy?->name ?? $activeModel->triggered_by_email ?? 'Sistem',
                'time' => optional($activeModel->activated_at ?? $activeModel->trained_at)->format('d M Y H:i') ?? '-',
            ];
        }

        if ($finalizedApplications > $trainingRows) {
            $notes[] = [
                'tone' => 'warning',
                'message' => ($finalizedApplications - $trainingRows).' pengajuan final belum tersalin ke data latih.',
                'actor' => 'Monitoring',
                'time' => now()->format('d M Y H:i'),
            ];
        }

        if ($applicationsWithoutSnapshot > 0) {
            $notes[] = [
                'tone' => 'info',
                'message' => $applicationsWithoutSnapshot.' pengajuan belum memiliki rekomendasi sistem terbaru.',
                'actor' => 'Review',
                'time' => now()->format('d M Y H:i'),
            ];
        }

        if ($latestAttempt) {
            $latestAttemptMessages = [
                'failed' => $latestAttempt->error_message ?: 'Percobaan pelatihan terakhir belum berhasil.',
                'training' => "Pelatihan {$latestAttempt->version_name} sedang berjalan.",
                'cancelled' => "Pelatihan {$latestAttempt->version_name} dibatalkan.",
                'ready' => "Pelatihan {$latestAttempt->version_name} selesai dijalankan.",
            ];

            $notes[] = [
                'tone' => in_array($latestAttempt->status, ['failed', 'training'], true) ? 'warning' : 'success',
                'message' => $latestAttemptMessages[$latestAttempt->status]
                    ?? "Status pelatihan {$latestAttempt->version_name}: {$latestAttempt->status}.",
                'actor' => $latestAttempt->triggeredBy?->name ?? $latestAttempt->triggered_by_email ?? 'Sistem',
                'time' => optional($latestAttempt->trained_at ?? $latestAttempt->created_at)->format('d M Y H:i') ?? '-',
            ];
        }

        return array_slice($notes, 0, 4);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildEvaluationPayload(?ModelVersion $modelVersion): ?array
    {
        if (! $modelVersion) {
            return null;
        }

        return [
            'catboost' => $this->formatModelMetrics('CatBoost', $modelVersion->catboost_metrics),
            'naive_bayes' => $this->formatModelMetrics('Naive Bayes', $modelVersion->naive_bayes_metrics),
        ];
    }

    /**
     * @param Collection<int, ModelVersion> $versions
     * @return array<int, array<string, mixed>>
     */
    private function buildRecentVersionsPayload(Collection $versions): array
    {
        return $versions
            ->map(function (ModelVersion $version): array {
                return [
                    'version' => $version,
                    'catboost' => $this->formatModelMetrics('CatBoost', $version->catboost_metrics),
                    'naive_bayes' => $this->formatModelMetrics('Naive Bayes', $version->naive_bayes_metrics),
                ];
            })
            ->all();
    }

    /**
     * @param array<string, mixed>|null $metrics
     * @return array<string, mixed>|null
     */
    private function formatModelMetrics(string $label, ?array $metrics): ?array
    {
        if (! $metrics) {
            return null;
        }

        $confusion = $metrics['confusion_matrix'] ?? [];

        return [
            'label' => $label,
            'evaluation_dataset' => $metrics['evaluation_dataset'] ?? 'validation',
            'threshold' => $metrics['threshold'] ?? null,
            'accuracy' => $metrics['accuracy'] ?? null,
            'balanced_accuracy' => $metrics['balanced_accuracy'] ?? null,
            'precision_indikasi' => $metrics['precision_indikasi'] ?? null,
            'recall_indikasi' => $metrics['recall_indikasi'] ?? null,
            'f1_indikasi' => $metrics['f1_indikasi'] ?? null,
            'fbeta_indikasi' => $metrics['fbeta_indikasi'] ?? null,
            'confusion_matrix' => [
                'tn' => $confusion['tn'] ?? 0,
                'fp' => $confusion['fp'] ?? 0,
                'fn' => $confusion['fn'] ?? 0,
                'tp' => $confusion['tp'] ?? 0,
            ],
        ];
    }
}
