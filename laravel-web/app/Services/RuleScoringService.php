<?php

namespace App\Services;

class RuleScoringService
{
    /**
     * @param array<string, int|float> $coreParameters
     * @return array{rule_score: float|null, rule_recommendation: string|null, total_weight: float}
     */
    public function score(array $coreParameters): array
    {
        $binaryIndicators = [
            (int) ($coreParameters['kip'] ?? 0),
            (int) ($coreParameters['pkh'] ?? 0),
            (int) ($coreParameters['kks'] ?? 0),
            (int) ($coreParameters['dtks'] ?? 0),
            (int) ($coreParameters['sktm'] ?? 0),
        ];

        $ordinalIndicators = [
            (int) ($coreParameters['penghasilan_gabungan'] ?? 3),
            (int) ($coreParameters['penghasilan_ayah'] ?? 3),
            (int) ($coreParameters['penghasilan_ibu'] ?? 3),
            (int) ($coreParameters['jumlah_tanggungan'] ?? 3),
            (int) ($coreParameters['anak_ke'] ?? 3),
            (int) ($coreParameters['status_orangtua'] ?? 3),
            (int) ($coreParameters['status_rumah'] ?? 3),
            (int) ($coreParameters['daya_listrik'] ?? 3),
        ];

        $binaryScore = array_sum($binaryIndicators) / 5;

        $ordinalVulnerabilityScores = array_map(
            static fn (int $value): float => max(0.0, min((4 - $value) / 3, 1.0)),
            $ordinalIndicators
        );

        $ordinalScore = array_sum($ordinalVulnerabilityScores) / 8;
        $combinedScore = round((0.5 * $binaryScore) + (0.5 * $ordinalScore), 4);

        $threshold = (float) env('RULE_RECOMMENDATION_THRESHOLD', 0.6);
        $recommendation = $combinedScore >= $threshold ? 'Layak' : 'Indikasi';

        return [
            'rule_score' => $combinedScore,
            'rule_recommendation' => $recommendation,
            'total_weight' => 1.0,
        ];
    }
}
