<?php

namespace App\Services;

use App\Models\ApplicationStatusLog;
use App\Models\StudentApplication;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminApplicationReviewService
{
    public function __construct(
        private readonly TrainingDataSyncService $trainingDataSyncService,
        private readonly ApplicationInferenceService $applicationInferenceService,
    ) {}

    public function detail(int $applicationId): StudentApplication
    {
        return StudentApplication::query()
            ->with([
                'student:id,name,email',
                'adminDecider:id,name,email',
                'currentEncoding',
                'modelSnapshot.modelVersion',
                'latestTrainingRow.finalizedBy:id,name,email',
                'logs' => fn ($query) => $query
                    ->with('actor:id,name,email')
                    ->orderByDesc('id'),
            ])
            ->findOrFail($applicationId);
    }

    public function documentUrl(StudentApplication $application): ?string
    {
        if ($application->submitted_pdf_path) {
            return Storage::disk('public')->url($application->submitted_pdf_path);
        }

        return $application->source_document_link;
    }

    public function syncPredictionSnapshot(int $applicationId, ?int $actorUserId = null): StudentApplication
    {
        $application = StudentApplication::query()->findOrFail($applicationId);
        $this->applicationInferenceService->syncPredictionSnapshot($application, $actorUserId);

        return $this->detail($applicationId);
    }

    /**
     * @return array{processed:int, succeeded:int, failed:int, errors:list<array{application_id:int, message:string}>}
     */
    public function batchRunPredictions(?int $actorUserId = null, ?string $status = null, ?int $limit = null, bool $onlyMissing = true): array
    {
        $query = StudentApplication::query()
            ->when($status !== null, fn ($builder) => $builder->where('status', $status))
            ->when($onlyMissing, fn ($builder) => $builder->whereDoesntHave('modelSnapshot'))
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $applications = $query->get();
        $success = 0;
        $failed = [];

        foreach ($applications as $application) {
            try {
                $this->applicationInferenceService->syncPredictionSnapshot($application, $actorUserId);
                $success++;
            } catch (\Throwable $throwable) {
                $failed[] = [
                    'application_id' => $application->id,
                    'message' => $throwable->getMessage(),
                ];
            }
        }

        return [
            'processed' => $applications->count(),
            'succeeded' => $success,
            'failed' => count($failed),
            'errors' => $failed,
        ];
    }

    public function finalize(int $applicationId, int $actorUserId, string $status, ?string $note = null): StudentApplication
    {
        $application = StudentApplication::query()->findOrFail($applicationId);

        if (! in_array($status, ['Verified', 'Rejected'], true)) {
            throw new DomainException('Status keputusan admin tidak valid.');
        }

        if ($application->status !== 'Submitted') {
            throw new DomainException('Pengajuan hanya dapat diputuskan dari status Submitted.');
        }

        if (! $application->modelSnapshot()->exists()) {
            throw new DomainException('Rekomendasi model belum tersedia. Bangun snapshot prediksi terlebih dahulu sebelum memberi keputusan final.');
        }

        DB::transaction(function () use ($application, $actorUserId, $status, $note): void {
            $previousStatus = $application->status;

            $application->forceFill([
                'status' => $status,
                'admin_decision' => $status,
                'admin_decided_by' => $actorUserId,
                'admin_decision_note' => $note,
                'admin_decided_at' => now(),
            ])->save();

            ApplicationStatusLog::query()->create([
                'application_id' => $application->id,
                'actor_user_id' => $actorUserId,
                'from_status' => $previousStatus,
                'to_status' => $status,
                'action' => strtolower($status),
                'note' => $note,
                'metadata' => ['admin_decision' => $status],
            ]);

            $this->trainingDataSyncService->syncFromApplication($application, $actorUserId);
        });

        return $this->detail($applicationId);
    }
}
