<?php

namespace App\Services;

use App\Models\ApplicationFeatureEncoding;
use App\Models\SpkTrainingData;
use App\Models\StudentApplication;
use Illuminate\Validation\ValidationException;

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
     * @return array{processed:int, synced:int, skipped:int, skipped_applications:list<array{application_id:int, applicant_name:?string, reason:string}>}
     */
    public function syncFinalizedApplications(?int $finalizedByUserId = null, bool $forceResync = false): array
    {
        $processed = 0;
        $synced = 0;
        $skipped = 0;
        $skippedApplications = [];

        $applications = StudentApplication::query()
            ->whereIn('status', ['Verified', 'Rejected'])
            ->orderBy('id')
            ->get();

        foreach ($applications as $application) {
            $processed++;

            try {
                $encoding = $application->currentEncoding()->first()
                    ?? $this->encodingService->syncFromApplication($application, $finalizedByUserId);
            } catch (ValidationException $validationException) {
                $skipped++;
                $skippedApplications[] = [
                    'application_id' => $application->id,
                    'applicant_name' => $application->applicant_name,
                    'reason' => collect($validationException->errors())
                        ->flatten()
                        ->implode(' '),
                ];
                continue;
            }

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
            'skipped_applications' => $skippedApplications,
        ];
    }

    private function upsertTrainingRow(
        StudentApplication $application,
        ApplicationFeatureEncoding $encoding,
        ?int $finalizedByUserId = null,
    ): void {
        $label = $application->status === 'Verified' ? 'Layak' : 'Indikasi';
        $labelClass = $label === 'Indikasi' ? 1 : 0;

        // Ambil snapshot AI jika tersedia
        $snapshot = $application->modelSnapshot;
        $aiData = [];
        if ($snapshot) {
            $aiData = [
                'ai_recommendation' => $snapshot->final_recommendation,
                'ai_catboost_label' => $snapshot->catboost_label,
                'ai_naive_bayes_label' => $snapshot->naive_bayes_label,
                'ai_catboost_confidence' => $snapshot->catboost_confidence,
                'ai_naive_bayes_confidence' => $snapshot->naive_bayes_confidence,
            ];
        }

        SpkTrainingData::query()->updateOrCreate(
            ['source_encoding_id' => $encoding->id],
            [
                'source_application_id' => $application->id,
                'schema_version' => $encoding->schema_version,
                'encoding_version' => $encoding->encoding_version,
                ...$encoding->toFeatureArray(),
                'label' => $label,
                'label_class' => $labelClass,
                ...$aiData,
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

