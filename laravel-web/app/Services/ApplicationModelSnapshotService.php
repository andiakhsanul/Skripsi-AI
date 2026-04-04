<?php

namespace App\Services;

use App\Models\ApplicationFeatureEncoding;
use App\Models\ApplicationModelSnapshot;
use App\Models\StudentApplication;

class ApplicationModelSnapshotService
{
    /**
     * @param array<string, mixed> $inference
     * @param array<string, mixed> $ruleScore
     */
    public function syncFromEncoding(
        StudentApplication $application,
        ApplicationFeatureEncoding $encoding,
        array $inference,
        array $ruleScore,
        string $reviewPriority
    ): ApplicationModelSnapshot {
        return ApplicationModelSnapshot::query()->updateOrCreate(
            ['application_id' => $application->id],
            [
                'encoding_id' => $encoding->id,
                'schema_version' => $application->schema_version,
                'model_version_id' => $inference['model_version_id'] ?? null,
                ...$encoding->toFeatureArray(),
                'model_ready' => (bool) ($inference['model_ready'] ?? false),
                'catboost_label' => $inference['catboost_label'] ?? null,
                'catboost_confidence' => $inference['catboost_confidence'] ?? null,
                'naive_bayes_label' => $inference['naive_bayes_label'] ?? null,
                'naive_bayes_confidence' => $inference['naive_bayes_confidence'] ?? null,
                'disagreement_flag' => (bool) ($inference['disagreement_flag'] ?? false),
                'final_recommendation' => $inference['final_recommendation'] ?? null,
                'review_priority' => $reviewPriority,
                'rule_score' => $ruleScore['rule_score'] ?? null,
                'rule_recommendation' => $ruleScore['rule_recommendation'] ?? null,
                'snapshotted_at' => now(),
            ]
        );
    }
}
