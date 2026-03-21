<?php

namespace App\Services;

use App\Models\ParameterSchemaVersion;

class RuleScoringService
{
    /**
     * @param array<string, int|float> $coreParameters
     * @param array<string, mixed> $extraParameters
     * @return array{rule_score: float|null, rule_recommendation: string|null, total_weight: float}
     */
    public function score(
        array $coreParameters,
        array $extraParameters,
        ?ParameterSchemaVersion $schema
    ): array {
        if ($schema === null) {
            return $this->fallbackScore($coreParameters);
        }

        $definitions = $schema->parameter_definitions ?? [];
        if ($definitions === []) {
            return $this->fallbackScore($coreParameters);
        }

        $weightedSum = 0.0;
        $totalWeight = 0.0;

        foreach ($definitions as $definition) {
            $name = (string) ($definition['name'] ?? '');
            $type = strtolower((string) ($definition['type'] ?? ''));

            if ($name === '' || $type === '') {
                continue;
            }

            $isCore = (bool) ($definition['is_core'] ?? false);
            $weight = (float) ($definition['weight'] ?? 1.0);

            if ($weight <= 0) {
                continue;
            }

            $source = $isCore ? $coreParameters : $extraParameters;
            if (! array_key_exists($name, $source)) {
                continue;
            }

            $value = $source[$name];
            $normalizedValue = $this->normalizeValue($value, $type, $definition);
            if ($normalizedValue === null) {
                continue;
            }

            $weightedSum += ($normalizedValue * $weight);
            $totalWeight += $weight;
        }

        if ($totalWeight <= 0) {
            return $this->fallbackScore($coreParameters);
        }

        $ruleScore = round($weightedSum / $totalWeight, 4);
        $threshold = (float) env('RULE_RECOMMENDATION_THRESHOLD', 0.6);

        return [
            'rule_score' => $ruleScore,
            'rule_recommendation' => $ruleScore >= $threshold ? 'Layak' : 'Indikasi',
            'total_weight' => $totalWeight,
        ];
    }

    /**
     * @param array<string, int|float> $coreParameters
     * @return array{rule_score: float|null, rule_recommendation: string|null, total_weight: float}
     */
    private function fallbackScore(array $coreParameters): array
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

    /**
     * @param array<string, mixed> $definition
     */
    private function normalizeValue(mixed $value, string $type, array $definition): ?float
    {
        if ($type === 'boolean') {
            if (is_bool($value)) {
                return $value ? 1.0 : 0.0;
            }

            $normalized = strtolower(trim((string) $value));
            if (in_array($normalized, ['1', 'true', 'yes', 'ya'], true)) {
                return 1.0;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'tidak'], true)) {
                return 0.0;
            }

            return null;
        }

        if (! in_array($type, ['integer', 'float'], true)) {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $numericValue = (float) $value;
        $min = $definition['min_value'] ?? null;
        $max = $definition['max_value'] ?? null;

        if ($min !== null && $max !== null && (float) $max > (float) $min) {
            $normalizedValue = ($numericValue - (float) $min) / ((float) $max - (float) $min);

            return max(0.0, min($normalizedValue, 1.0));
        }

        if ($numericValue <= 0) {
            return 0.0;
        }

        // Fallback normalisasi sederhana kalau range tidak ada.
        return min($numericValue / max($numericValue, 1.0), 1.0);
    }
}
