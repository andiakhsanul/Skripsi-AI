<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class MlGatewayService
{
    /**
     * @param array<string, int|float> $features
     * @return array<string, mixed>
     */
    public function predictOrFallback(array $features): array
    {
        try {
            return $this->predict($features);
        } catch (\Throwable $th) {
            return $this->fallbackPrediction($features, $th->getMessage());
        }
    }

    /**
     * @param array<string, int|float> $features
     * @return array<string, mixed>
     */
    public function predict(array $features): array
    {
        $predictUrl = env('FLASK_API_URL', 'http://flask-api:5000/api/predict');
        $internalToken = env('FLASK_INTERNAL_TOKEN', 'spk_internal_dev_token');

        $response = Http::timeout(10)
            ->retry(1, 200)
            ->withHeaders([
                'X-Internal-Token' => $internalToken,
            ])
            ->post($predictUrl, $features);

        if (! $response->successful()) {
            throw new RuntimeException('Prediksi gagal diproses oleh service ML. HTTP '.$response->status());
        }

        return $this->normalizePredictionPayload($response->json() ?? []);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizePredictionPayload(array $payload): array
    {
        $results = $payload['model_results'] ?? $payload;

        $catboostLabel = $results['catboost']['label']
            ?? $results['catboost_label']
            ?? $results['catboost_result']
            ?? 'Layak';

        $naiveBayesLabel = $results['naive_bayes']['label']
            ?? $results['naive_bayes_label']
            ?? $results['naive_bayes_result']
            ?? 'Layak';

        $catboostConfidence = (float) ($results['catboost']['confidence']
            ?? $results['catboost_confidence']
            ?? 0.5);

        $naiveBayesConfidence = (float) ($results['naive_bayes']['confidence']
            ?? $results['naive_bayes_confidence']
            ?? 0.5);

        $disagreementFlag = (bool) ($results['disagreement_flag'] ?? ($catboostLabel !== $naiveBayesLabel));
        $finalRecommendation = (string) ($results['final_recommendation'] ?? $catboostLabel);
        $reviewPriority = (string) ($results['review_priority'] ?? ($disagreementFlag ? 'high' : 'normal'));
        $modelVersionId = $results['model_version_id'] ?? $payload['model_version_id'] ?? null;
        $modelVersionName = $results['model_version_name'] ?? $payload['model_version_name'] ?? null;
        $modelTrainedAt = $results['model_trained_at'] ?? $payload['model_trained_at'] ?? null;

        return [
            'model_ready' => (bool) ($results['model_ready'] ?? true),
            'catboost_label' => $catboostLabel,
            'catboost_confidence' => round($catboostConfidence, 4),
            'naive_bayes_label' => $naiveBayesLabel,
            'naive_bayes_confidence' => round($naiveBayesConfidence, 4),
            'disagreement_flag' => $disagreementFlag,
            'final_recommendation' => $finalRecommendation,
            'review_priority' => $reviewPriority,
            'model_version_id' => $modelVersionId !== null ? (int) $modelVersionId : null,
            'model_version_name' => $modelVersionName,
            'model_trained_at' => $modelTrainedAt,
        ];
    }

    /**
     * @param array<string, int|float> $features
     * @return array<string, mixed>
     */
    private function fallbackPrediction(array $features, string $reason): array
    {
        $isLikelyEligible = (int) ($features['kip'] ?? 0) === 1
            && (int) ($features['penghasilan_gabungan'] ?? 3) === 1
            && (int) ($features['daya_listrik'] ?? 3) <= 2;

        $label = $isLikelyEligible ? 'Layak' : 'Indikasi';

        return [
            'model_ready' => false,
            'catboost_label' => $label,
            'catboost_confidence' => 0.5,
            'naive_bayes_label' => $label,
            'naive_bayes_confidence' => 0.5,
            'disagreement_flag' => false,
            'final_recommendation' => $label,
            'review_priority' => 'high',
            'model_version_id' => null,
            'model_version_name' => null,
            'model_trained_at' => null,
            'fallback_reason' => $reason,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function retrain(array $payload): array
    {
        $retrainUrl = env('FLASK_RETRAIN_URL', 'http://flask-api:5000/api/retrain');
        $internalToken = env('FLASK_INTERNAL_TOKEN', 'spk_internal_dev_token');

        $response = Http::timeout(30)
            ->retry(1, 300)
            ->withHeaders([
                'X-Internal-Token' => $internalToken,
            ])
            ->post($retrainUrl, $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Retrain gagal diproses service ML. HTTP '.$response->status());
        }

        return $response->json() ?? [];
    }
}
