<?php

namespace App\Services;

use App\Models\ApplicationFeatureEncoding;
use App\Models\SpkTrainingData;
use App\Models\StudentApplication;

class TrainingDataSyncService
{
    public function __construct(
        private readonly ApplicationFeatureEncodingService $encodingService,
    ) {}

    public function syncFromApplication(StudentApplication $application, ?int $finalizedByUserId = null): void
    {
        if (! in_array($application->status, ['Verified', 'Rejected'], true)) {
            return;
        }

        $encoding = $application->currentEncoding()->first()
            ?? $this->encodingService->syncFromApplication($application, $finalizedByUserId);

        $this->upsertTrainingRow($application, $encoding, $finalizedByUserId);
    }

    /**
     * @return array{processed:int, synced:int, skipped:int}
     */
    public function syncFinalizedApplications(?int $finalizedByUserId = null, bool $forceResync = false): array
    {
        $processed = 0;
        $synced = 0;
        $skipped = 0;

        $applications = StudentApplication::query()
            ->whereIn('status', ['Verified', 'Rejected'])
            ->orderBy('id')
            ->get();

        foreach ($applications as $application) {
            $processed++;

            $encoding = $application->currentEncoding()->first()
                ?? $this->encodingService->syncFromApplication($application, $finalizedByUserId);

            if (! $forceResync && SpkTrainingData::query()->where('source_encoding_id', $encoding->id)->exists()) {
                $skipped++;
                continue;
            }

            $this->upsertTrainingRow($application, $encoding, $finalizedByUserId);
            $synced++;
        }

        return [
            'processed' => $processed,
            'synced' => $synced,
            'skipped' => $skipped,
        ];
    }

    private function upsertTrainingRow(
        StudentApplication $application,
        ApplicationFeatureEncoding $encoding,
        ?int $finalizedByUserId = null,
    ): void {
        $label = $application->status === 'Verified' ? 'Layak' : 'Indikasi';
        $labelClass = $label === 'Indikasi' ? 1 : 0;

        SpkTrainingData::query()->updateOrCreate(
            ['source_encoding_id' => $encoding->id],
            [
                'source_application_id' => $application->id,
                'schema_version' => $encoding->schema_version,
                'encoding_version' => $encoding->encoding_version,
                ...$encoding->toFeatureArray(),
                'label' => $label,
                'label_class' => $labelClass,
                'decision_status' => $application->status,
                'finalized_by_user_id' => $application->admin_decided_by ?? $finalizedByUserId,
                'finalized_at' => $application->admin_decided_at ?? now(),
                'is_active' => true,
                'admin_corrected' => false,
                'correction_note' => null,
            ],
        );
    }
}
