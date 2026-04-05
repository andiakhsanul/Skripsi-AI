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
        private readonly ParameterSchemaService $parameterSchemaService,
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

        $predictionSnapshots = ApplicationModelSnapshot::query()->count();
        $applicationsWithoutSnapshot = StudentApplication::query()
            ->whereDoesntHave('modelSnapshot')
            ->count();

        $activeModel = $this->currentActiveModel();
        $latestReadyModel = $this->latestReadyModel();
        $latestAttempt = $this->latestAttempt();

        return [
            'active_schema' => $this->parameterSchemaService->getActiveSchema(),
            'finalized_applications' => $finalizedApplications,
            'training_rows' => $trainingRows,
            'training_gap' => max($finalizedApplications - $trainingRows, 0),
            'training_corrections' => $trainingCorrections,
            'prediction_snapshots' => $predictionSnapshots,
            'applications_without_snapshot' => $applicationsWithoutSnapshot,
            'label_distribution' => $this->labelDistribution(),
            'active_model' => $activeModel,
            'latest_ready_model' => $latestReadyModel,
            'latest_attempt' => $latestAttempt,
            'recent_model_versions' => $this->recentModelVersions(),
            'model_status' => [
                'ready' => $activeModel !== null,
                'label' => $activeModel !== null ? 'SIAP' : 'BELUM SIAP',
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
    public function triggerRetrain(User $admin): array
    {
        $trainingRows = SpkTrainingData::query()
            ->where('is_active', true)
            ->count();

        if ($trainingRows === 0) {
            throw new RuntimeException('Belum ada data training aktif. Sinkronkan data final terlebih dahulu.');
        }

        $schemaVersion = $this->parameterSchemaService->getActiveSchema()?->version ?? 1;
        $mlResponse = $this->mlGatewayService->retrain([
            'triggered_by_user_id' => $admin->id,
            'triggered_by_email' => $admin->email,
            'schema_version' => $schemaVersion,
        ]);

        return [
            'schema_version' => $schemaVersion,
            'training_rows' => $trainingRows,
            'ml_response' => $mlResponse,
        ];
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
            ->orderByRaw('COALESCE(trained_at, created_at) DESC, id DESC')
            ->first();
    }
}
