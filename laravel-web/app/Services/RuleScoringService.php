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
            'rule_recommendation' => $ruleScore >= $threshold ? 'Layak' : 'Tidak Layak',
            'total_weight' => $totalWeight,
        ];
    }

    /**
     * @param array<string, int|float> $coreParameters
     * @return array{rule_score: float|null, rule_recommendation: string|null, total_weight: float}
     */
    private function fallbackScore(array $coreParameters): array
    {
        $isLikelyEligible = (int) ($coreParameters['kip_sma'] ?? 0) === 1
            && (float) ($coreParameters['penghasilan_gabungan'] ?? 0) <= 2_000_000
            && (int) ($coreParameters['daya_listrik'] ?? 0) <= 1300;

        return [
            'rule_score' => $isLikelyEligible ? 0.75 : 0.35,
            'rule_recommendation' => $isLikelyEligible ? 'Layak' : 'Tidak Layak',
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
