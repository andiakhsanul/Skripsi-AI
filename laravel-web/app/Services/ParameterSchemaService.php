<?php

namespace App\Services;

use App\Models\ParameterSchemaVersion;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ParameterSchemaService
{
    public function getActiveSchema(): ?ParameterSchemaVersion
    {
        return ParameterSchemaVersion::query()
            ->where('is_active', true)
            ->orderByDesc('version')
            ->first()
            ?? ParameterSchemaVersion::query()->orderByDesc('version')->first();
    }

    public function resolveSchemaVersion(?int $requestedVersion = null): int
    {
        if ($requestedVersion !== null) {
            $exists = ParameterSchemaVersion::query()
                ->where('version', $requestedVersion)
                ->exists();

            if (! $exists) {
                throw ValidationException::withMessages([
                    'schema_version' => ['Schema version tidak ditemukan'],
                ]);
            }

            return $requestedVersion;
        }

        return (int) (ParameterSchemaVersion::query()->max('version') ?? 1);
    }

    /**
     * @param array<string, mixed> $coreParameters
     * @param array<string, mixed> $extraParameters
     */
    public function validateApplicationPayload(
        array $coreParameters,
        array $extraParameters,
        ?ParameterSchemaVersion $schema
    ): void {
        $baseValidator = Validator::make($coreParameters, [
            'kip' => ['required', 'integer', 'in:0,1'],
            'pkh' => ['required', 'integer', 'in:0,1'],
            'kks' => ['required', 'integer', 'in:0,1'],
            'dtks' => ['required', 'integer', 'in:0,1'],
            'sktm' => ['required', 'integer', 'in:0,1'],

            'penghasilan_gabungan' => ['required', 'integer', 'in:1,2,3'],
            'penghasilan_ayah' => ['required', 'integer', 'in:1,2,3'],
            'penghasilan_ibu' => ['required', 'integer', 'in:1,2,3'],
            'jumlah_tanggungan' => ['required', 'integer', 'in:1,2,3'],
            'anak_ke' => ['required', 'integer', 'in:1,2,3'],
            'status_orangtua' => ['required', 'integer', 'in:1,2,3'],
            'status_rumah' => ['required', 'integer', 'in:1,2,3'],
            'daya_listrik' => ['required', 'integer', 'in:1,2,3'],
        ]);

        if ($baseValidator->fails()) {
            throw ValidationException::withMessages($baseValidator->errors()->toArray());
        }

        if ($schema === null) {
            if ($extraParameters !== []) {
                throw ValidationException::withMessages([
                    'parameters_extra' => ['Schema parameter belum tersedia. Import schema terlebih dahulu.'],
                ]);
            }

            return;
        }

        $definitions = $schema->parameter_definitions ?? [];
        $definitionMap = [];

        foreach ($definitions as $definition) {
            if (! isset($definition['name'], $definition['type'])) {
                continue;
            }

            $definitionMap[strtolower((string) $definition['name'])] = $definition;
        }

        foreach ($extraParameters as $name => $value) {
            $normalizedName = strtolower($name);
            if (! isset($definitionMap[$normalizedName])) {
                throw ValidationException::withMessages([
                    "parameters_extra.{$name}" => ["Parameter {$name} tidak terdaftar di schema aktif"],
                ]);
            }

            $this->validateValueByDefinition($name, $value, $definitionMap[$normalizedName]);
        }

        foreach ($definitions as $definition) {
            $name = (string) ($definition['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $isCore = (bool) ($definition['is_core'] ?? false);
            if (! $isCore) {
                continue;
            }

            if (! array_key_exists($name, $coreParameters)) {
                throw ValidationException::withMessages([
                    $name => ["Parameter inti {$name} wajib diisi"],
                ]);
            }

            $this->validateValueByDefinition($name, $coreParameters[$name], $definition);
        }
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function validateValueByDefinition(string $name, mixed $value, array $definition): void
    {
        $type = strtolower((string) ($definition['type'] ?? ''));

        if ($type === 'integer') {
            if (! is_numeric($value) || (int) $value != $value) {
                throw ValidationException::withMessages([
                    $name => ["Parameter {$name} harus bertipe integer"],
                ]);
            }
            $numericValue = (float) $value;
        } elseif ($type === 'float') {
            if (! is_numeric($value)) {
                throw ValidationException::withMessages([
                    $name => ["Parameter {$name} harus bertipe numerik"],
                ]);
            }
            $numericValue = (float) $value;
        } elseif ($type === 'boolean') {
            if (! is_bool($value) && ! in_array(strtolower((string) $value), ['0', '1', 'true', 'false'], true)) {
                throw ValidationException::withMessages([
                    $name => ["Parameter {$name} harus bertipe boolean"],
                ]);
            }

            return;
        } elseif ($type === 'string') {
            if (! is_string($value)) {
                throw ValidationException::withMessages([
                    $name => ["Parameter {$name} harus bertipe string"],
                ]);
            }

            return;
        } else {
            return;
        }

        $min = $definition['min_value'] ?? null;
        $max = $definition['max_value'] ?? null;

        if ($min !== null && $numericValue < (float) $min) {
            throw ValidationException::withMessages([
                $name => ["Parameter {$name} berada di bawah minimum {$min}"],
            ]);
        }

        if ($max !== null && $numericValue > (float) $max) {
            throw ValidationException::withMessages([
                $name => ["Parameter {$name} berada di atas maksimum {$max}"],
            ]);
        }
    }
}
