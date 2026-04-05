<?php

namespace App\Services;

use App\Models\SpkTrainingData;
use App\Models\StudentApplication;
use Illuminate\Validation\ValidationException;

class AdminTrainingDataReviewService
{
    /**
     * @return array<string, mixed>
     */
    public function detail(int $applicationId): array
    {
        $application = StudentApplication::query()
            ->with([
                'student:id,name,email',
                'adminDecider:id,name,email',
                'currentEncoding',
                'modelSnapshot.modelVersion',
                'latestTrainingRow.finalizedBy:id,name,email',
            ])
            ->findOrFail($applicationId);

        $trainingRow = $application->latestTrainingRow;

        if (! $trainingRow) {
            throw ValidationException::withMessages([
                'training_data' => ['Data training belum tersedia. Finalisasi dan sinkronkan data terlebih dahulu.'],
            ]);
        }

        return [
            'application' => $application,
            'training_row' => $trainingRow,
            'legend' => $this->legend(),
            'field_options' => $this->fieldOptions(),
        ];
    }

    /**
     * @param array<string, mixed> $validated
     */
    public function update(int $applicationId, array $validated): SpkTrainingData
    {
        StudentApplication::query()->findOrFail($applicationId);

        $trainingRow = SpkTrainingData::query()
            ->where('source_application_id', $applicationId)
            ->latest('id')
            ->first();

        if (! $trainingRow) {
            throw ValidationException::withMessages([
                'training_data' => ['Data training belum tersedia untuk pengajuan ini.'],
            ]);
        }

        $updateData = array_filter($validated, static fn ($value): bool => $value !== null);
        $updateData['admin_corrected'] = true;

        if (isset($validated['label'])) {
            $updateData['label_class'] = $validated['label'] === 'Indikasi' ? 1 : 0;
        }

        $trainingRow->update($updateData);

        return $trainingRow->fresh();
    }

    /**
     * @return array<string, string>
     */
    public function legend(): array
    {
        return [
            'binary' => 'KIP/PKH/KKS/DTKS/SKTM: 0 = Tidak, 1 = Ya',
            'ordinal' => 'Ordinal: 1 = prioritas tertinggi, 3 = prioritas lebih rendah',
            'income' => 'Penghasilan: 1 = < 1jt, 2 = 1jt - < 4jt, 3 = ≥ 4jt',
            'dependents' => 'Jumlah tanggungan: 1 = ≥ 6, 2 = 4 - 5, 3 = 0 - 3',
            'child' => 'Anak ke-: 1 = ≥ 5, 2 = 3 - 4, 3 = 1 - 2',
            'parent' => 'Status orang tua: 1 = yatim piatu, 2 = yatim/piatu, 3 = lengkap',
            'house' => 'Status rumah: 1 = tidak memiliki, 2 = sewa/menumpang, 3 = milik sendiri',
            'power' => 'Daya listrik: 1 = non-PLN/tidak ada, 2 = 450-900 VA, 3 = > 900 VA',
            'label' => 'Label akhir: Layak = 0, Indikasi = 1',
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function fieldOptions(): array
    {
        return [
            'binary' => [
                0 => '0 - Tidak',
                1 => '1 - Ya',
            ],
            'income' => [
                1 => '1 - < Rp1.000.000',
                2 => '2 - Rp1.000.000 s.d. < Rp4.000.000',
                3 => '3 - ≥ Rp4.000.000',
            ],
            'dependents' => [
                1 => '1 - ≥ 6 orang',
                2 => '2 - 4 sampai 5 orang',
                3 => '3 - 0 sampai 3 orang',
            ],
            'child' => [
                1 => '1 - Anak ke-5 atau lebih',
                2 => '2 - Anak ke-3 atau ke-4',
                3 => '3 - Anak ke-1 atau ke-2',
            ],
            'parent' => [
                1 => '1 - Yatim Piatu',
                2 => '2 - Yatim atau Piatu',
                3 => '3 - Lengkap',
            ],
            'house' => [
                1 => '1 - Tidak memiliki rumah',
                2 => '2 - Sewa / Menumpang',
                3 => '3 - Milik sendiri',
            ],
            'power' => [
                1 => '1 - Tidak ada / Non-PLN',
                2 => '2 - PLN 450 - 900 VA',
                3 => '3 - PLN > 900 VA',
            ],
            'label' => [
                0 => 'Layak',
                1 => 'Indikasi',
            ],
        ];
    }
}
