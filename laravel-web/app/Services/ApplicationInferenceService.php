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
        // Encoding tetap disimpan di DB untuk audit/tampilan admin, tapi prediksi
        // dikirim ke Flask dalam bentuk RAW agar Flask jadi authoritative encoder.
        $encoding = $this->encodingService->syncFromApplication($application, $actorUserId);
        $features = $encoding->toFeatureArray();
        $schema = ParameterSchemaVersion::query()->where('version', $application->schema_version)->first();

        $this->schemaService->validateApplicationPayload($features, [], $schema);

        $rawPayload = $this->buildRawPayload($application);
        $inference = $this->mlGateway->predictOrFallback($rawPayload);
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
     * @return array<string, mixed>
     */
    private function buildRawPayload(StudentApplication $application): array
    {
        return [
            'kip' => (int) $application->kip,
            'pkh' => (int) $application->pkh,
            'kks' => (int) $application->kks,
            'dtks' => (int) $application->dtks,
            'sktm' => (int) $application->sktm,
            'penghasilan_ayah_rupiah' => $application->penghasilan_ayah_rupiah,
            'penghasilan_ibu_rupiah' => $application->penghasilan_ibu_rupiah,
            'penghasilan_gabungan_rupiah' => $application->penghasilan_gabungan_rupiah,
            'jumlah_tanggungan_raw' => $application->jumlah_tanggungan_raw,
            'anak_ke_raw' => $application->anak_ke_raw,
            'status_orangtua_text' => $application->status_orangtua_text,
            'status_rumah_text' => $application->status_rumah_text,
            'daya_listrik_text' => $application->daya_listrik_text,
        ];
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
