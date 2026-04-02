<?php

namespace App\Services;

use App\Models\ApplicationModelSnapshot;
use App\Models\StudentApplication;

class ApplicationModelSnapshotService
{
    /**
     * @param array<string, int> $encodedFeatures
     * @param array<string, mixed> $inference
     * @param array<string, mixed> $ruleScore
     */
    public function syncFromApplication(
        StudentApplication $application,
        array $encodedFeatures,
        array $inference,
        array $ruleScore,
        string $reviewPriority
    ): ApplicationModelSnapshot {
        return ApplicationModelSnapshot::query()->updateOrCreate(
            ['application_id' => $application->id],
            [
                'schema_version' => $application->schema_version,
                'model_version_id' => $inference['model_version_id'] ?? null,
                'kip' => (int) ($encodedFeatures['kip'] ?? 0),
                'pkh' => (int) ($encodedFeatures['pkh'] ?? 0),
                'kks' => (int) ($encodedFeatures['kks'] ?? 0),
                'dtks' => (int) ($encodedFeatures['dtks'] ?? 0),
                'sktm' => (int) ($encodedFeatures['sktm'] ?? 0),
                'penghasilan_gabungan' => (int) ($encodedFeatures['penghasilan_gabungan'] ?? 3),
                'penghasilan_ayah' => (int) ($encodedFeatures['penghasilan_ayah'] ?? 3),
                'penghasilan_ibu' => (int) ($encodedFeatures['penghasilan_ibu'] ?? 3),
                'jumlah_tanggungan' => (int) ($encodedFeatures['jumlah_tanggungan'] ?? 3),
                'anak_ke' => (int) ($encodedFeatures['anak_ke'] ?? 3),
                'status_orangtua' => (int) ($encodedFeatures['status_orangtua'] ?? 3),
                'status_rumah' => (int) ($encodedFeatures['status_rumah'] ?? 3),
                'daya_listrik' => (int) ($encodedFeatures['daya_listrik'] ?? 3),
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
