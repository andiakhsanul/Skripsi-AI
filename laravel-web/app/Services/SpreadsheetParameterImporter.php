<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class SpreadsheetParameterImporter
{
    private const HEADER_ALIASES = [
        'name' => ['name', 'nama_parameter', 'parameter', 'parameter_name'],
        'type' => ['type', 'tipe', 'data_type', 'jenis', 'jenis_data'],
        'min_value' => ['min', 'minimum', 'min_value', 'range_min'],
        'max_value' => ['max', 'maximum', 'max_value', 'range_max'],
        'weight' => ['weight', 'bobot'],
        'is_core' => ['is_core', 'core', 'core_parameter', 'parameter_inti'],
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $filePath, ?string $originalFileName = null): array
    {
        $extensionSource = $originalFileName ?: $filePath;
        $extension = strtolower(pathinfo($extensionSource, PATHINFO_EXTENSION));

        $rows = match ($extension) {
            'csv', 'txt' => $this->parseCsv($filePath),
            'xlsx' => $this->parseXlsx($filePath),
            default => throw new RuntimeException('Format file belum didukung. Gunakan .xlsx atau .csv'),
        };

        return $this->normalizeRows($rows);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function parseCsv(string $filePath): array
    {
        $rows = [];
        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Tidak dapat membaca file CSV');
        }

        while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $rows[] = array_map(static fn ($item): string => trim((string) $item), $data);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function parseXlsx(string $filePath): array
    {
        $zip = new ZipArchive();

        if ($zip->open($filePath) !== true) {
            throw new RuntimeException('File XLSX tidak dapat dibuka');
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            $zip->close();
            throw new RuntimeException('Worksheet sheet1.xml tidak ditemukan di file XLSX');
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheet = simplexml_load_string($sheetXml);
        if ($sheet === false || ! isset($sheet->sheetData)) {
            $zip->close();
            throw new RuntimeException('Worksheet XLSX tidak valid');
        }

        $rows = [];

        foreach ($sheet->sheetData->row as $rowNode) {
            $cells = [];

            foreach ($rowNode->c as $cellNode) {
                $reference = (string) $cellNode['r'];
                $columnLetters = preg_replace('/\d+/', '', $reference) ?: 'A';
                $columnIndex = $this->columnToIndex($columnLetters);

                $type = (string) $cellNode['t'];
                $value = '';

                if ($type === 's') {
                    $sharedIndex = (int) ($cellNode->v ?? 0);
                    $value = $sharedStrings[$sharedIndex] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = trim((string) ($cellNode->is->t ?? ''));
                } else {
                    $value = trim((string) ($cellNode->v ?? ''));
                }

                $cells[$columnIndex] = $value;
            }

            if ($cells === []) {
                continue;
            }

            ksort($cells);
            $maxIndex = (int) max(array_keys($cells));
            $row = [];

            for ($i = 0; $i <= $maxIndex; $i++) {
                $row[] = $cells[$i] ?? '';
            }

            $rows[] = $row;
        }

        $zip->close();

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $shared = simplexml_load_string($xml);
        if ($shared === false) {
            return [];
        }

        $result = [];

        foreach ($shared->si as $item) {
            if (isset($item->t)) {
                $result[] = trim((string) $item->t);
                continue;
            }

            $composed = '';
            foreach ($item->r as $run) {
                $composed .= (string) ($run->t ?? '');
            }

            $result[] = trim($composed);
        }

        return $result;
    }

    /**
     * @param array<int, array<int, string>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRows(array $rows): array
    {
        if (count($rows) < 2) {
            throw new RuntimeException('File parameter minimal harus berisi header dan satu data');
        }

        $headerMap = $this->buildHeaderMap($rows[0]);
        $definitions = [];
        $seenNames = [];

        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }

            $name = trim((string) $this->cell($row, $headerMap['name']));
            if ($name === '') {
                continue;
            }

            $type = $this->normalizeType((string) $this->cell($row, $headerMap['type']));
            if ($type === null) {
                throw new RuntimeException("Tipe parameter tidak valid untuk baris {$index}. Gunakan integer, float, boolean, atau string");
            }

            $minValue = $this->nullableNumeric($this->cell($row, $headerMap['min_value']));
            $maxValue = $this->nullableNumeric($this->cell($row, $headerMap['max_value']));
            $weight = $this->nullableNumeric($this->cell($row, $headerMap['weight'])) ?? 1.0;
            $isCore = $this->toBool($this->cell($row, $headerMap['is_core']));

            if ($minValue !== null && $maxValue !== null && $minValue > $maxValue) {
                throw new RuntimeException("Range parameter {$name} tidak valid karena min lebih besar dari max");
            }

            if ($weight <= 0) {
                throw new RuntimeException("Bobot parameter {$name} harus lebih besar dari nol");
            }

            $normalizedName = strtolower($name);
            if (isset($seenNames[$normalizedName])) {
                throw new RuntimeException("Nama parameter duplikat terdeteksi: {$name}");
            }

            $seenNames[$normalizedName] = true;
            $definitions[] = [
                'name' => $name,
                'type' => $type,
                'min_value' => $minValue,
                'max_value' => $maxValue,
                'weight' => $weight,
                'is_core' => $isCore,
            ];
        }

        if ($definitions === []) {
            throw new RuntimeException('Tidak ada parameter valid yang dapat diimpor dari file');
        }

        return $definitions;
    }

    /**
     * @param array<int, string> $headerRow
     * @return array<string, int|null>
     */
    private function buildHeaderMap(array $headerRow): array
    {
        $normalizedHeader = [];
        foreach ($headerRow as $index => $header) {
            $normalizedHeader[$index] = $this->normalizeHeader($header);
        }

        $headerMap = [
            'name' => null,
            'type' => null,
            'min_value' => null,
            'max_value' => null,
            'weight' => null,
            'is_core' => null,
        ];

        foreach (self::HEADER_ALIASES as $canonical => $aliases) {
            foreach ($normalizedHeader as $index => $header) {
                if (in_array($header, $aliases, true)) {
                    $headerMap[$canonical] = $index;
                    break;
                }
            }
        }

        if ($headerMap['name'] === null || $headerMap['type'] === null) {
            throw new RuntimeException('Header wajib minimal: name dan type');
        }

        return $headerMap;
    }

    private function normalizeHeader(string $header): string
    {
        $normalized = strtolower(trim($header));

        return preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? '';
    }

    private function normalizeType(string $rawType): ?string
    {
        $normalized = strtolower(trim($rawType));

        return match ($normalized) {
            'int', 'integer', 'smallint', 'bigint' => 'integer',
            'float', 'double', 'decimal', 'numeric', 'number' => 'float',
            'bool', 'boolean' => 'boolean',
            'string', 'text', 'varchar' => 'string',
            default => null,
        };
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'ya', 'y'], true);
    }

    private function nullableNumeric(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (! is_numeric($raw)) {
            throw new RuntimeException("Nilai numerik tidak valid: {$raw}");
        }

        return (float) $raw;
    }

    private function cell(array $row, ?int $index): mixed
    {
        if ($index === null) {
            return null;
        }

        return $row[$index] ?? null;
    }

    private function columnToIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $length = strlen($letters);
        $index = 0;

        for ($i = 0; $i < $length; $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - ord('A') + 1);
        }

        return max($index - 1, 0);
    }
}
