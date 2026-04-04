<?php

namespace App\Services;

use App\Models\ApplicationModelSnapshot;
use App\Models\ParameterSchemaVersion;
use App\Models\StudentApplication;

class ApplicationInferenceService
{
    public function __construct(
        private readonly ParameterSchemaService $schemaService,
        private readonly ApplicationFeatureEncodingService $encodingService,
        private readonly MlGatewayService $mlGateway,
        private readonly RuleScoringService $ruleScoringService,
        private readonly ApplicationModelSnapshotService $modelSnapshotService,
    ) {}

    public function syncPredictionSnapshot(StudentApplication $application, ?int $actorUserId = null): ApplicationModelSnapshot
    {
        $encoding = $this->encodingService->syncFromApplication($application, $actorUserId);
        $features = $encoding->toFeatureArray();
        $schema = ParameterSchemaVersion::query()->where('version', $application->schema_version)->first();

        $this->schemaService->validateApplicationPayload($features, [], $schema);

        $inference = $this->mlGateway->predictOrFallback($features);
        $ruleScore = $this->ruleScoringService->score($features, [], $schema);
        $reviewPriority = $this->resolveReviewPriority($inference, $ruleScore);

        return $this->modelSnapshotService->syncFromEncoding(
            $application,
            $encoding,
            $inference,
            $ruleScore,
            $reviewPriority,
        );
    }

    /**
     * @param array<string, mixed> $inference
     * @param array<string, mixed> $ruleScore
     */
    private function resolveReviewPriority(array $inference, array $ruleScore): string
    {
        $reviewPriority = (string) ($inference['review_priority'] ?? 'normal');

        if (
            isset($ruleScore['rule_recommendation'], $inference['catboost_label'])
            && $ruleScore['rule_recommendation'] !== null
            && $ruleScore['rule_recommendation'] !== ($inference['catboost_label'] ?? null)
        ) {
            return 'high';
        }

        return $reviewPriority;
    }
}
