<?php

namespace App\Services;

use App\Models\SpkTrainingData;
use App\Models\StudentApplication;
use Illuminate\Support\Facades\Storage;
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
            'view_payload' => $this->viewPayload($application, $trainingRow, $this->legend()),
        ];
    }

    /**
     * @param array<string, string> $legend
     * @return array<string, mixed>
     */
    public function viewPayload(StudentApplication $application, SpkTrainingData $trainingRow, array $legend): array
    {
        $snapshot = $application->modelSnapshot;
        $student = $application->student;
        $displayName = $student?->name ?? $application->applicant_name ?? 'Mahasiswa';
        $displayMeta = collect([
            $application->faculty,
            $application->study_program,
            $student?->email ?? $application->applicant_email,
        ])->filter()->implode(' • ');
        $documentUrl = $application->submitted_pdf_path
            ? Storage::disk('public')->url($application->submitted_pdf_path)
            : $application->source_document_link;

        return [
            'display_name' => $displayName,
            'display_meta' => $displayMeta,
            'document_url' => $documentUrl,
            'score' => [
                'current' => round(((float) ($snapshot?->catboost_confidence ?? 0)) * 100, 1),
                'label' => $trainingRow->label === 'Layak' ? 'LAYAK' : 'INDIKASI',
                'tone' => $trainingRow->label === 'Layak'
                    ? 'bg-primary text-white shadow-lg shadow-primary/20'
                    : 'bg-error text-white shadow-lg shadow-error/20',
            ],
            'training_status' => [
                'classes' => $trainingRow->admin_corrected
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                    : 'border-yellow-200 bg-yellow-50 text-yellow-700',
                'icon' => $trainingRow->admin_corrected ? 'verified' : 'priority_high',
            ],
            'training_field_groups' => [
                [
                    'title' => 'Dokumen & Bantuan Sosial',
                    'type' => 'binary',
                    'fields' => [
                        'kip' => 'Kepemilikan KIP',
                        'pkh' => 'Kepemilikan PKH',
                        'kks' => 'Kepemilikan KKS',
                        'dtks' => 'Terdata DTKS',
                        'sktm' => 'SKTM',
                    ],
                ],
                [
                    'title' => 'Ekonomi Keluarga',
                    'type' => 'income',
                    'fields' => [
                        'penghasilan_ayah' => 'Penghasilan Ayah',
                        'penghasilan_ibu' => 'Penghasilan Ibu',
                        'penghasilan_gabungan' => 'Penghasilan Gabungan',
                    ],
                ],
                [
                    'title' => 'Beban Keluarga',
                    'type' => 'mixed',
                    'fields' => [
                        'jumlah_tanggungan' => ['label' => 'Jumlah Tanggungan', 'options' => 'dependents'],
                        'anak_ke' => ['label' => 'Anak Ke-', 'options' => 'child'],
                    ],
                ],
                [
                    'title' => 'Standar Hidup',
                    'type' => 'mixed',
                    'fields' => [
                        'status_orangtua' => ['label' => 'Status Orang Tua', 'options' => 'parent'],
                        'status_rumah' => ['label' => 'Status Rumah', 'options' => 'house'],
                        'daya_listrik' => ['label' => 'Daya Listrik', 'options' => 'power'],
                    ],
                ],
            ],
            'legend_cards' => [
                ['title' => 'Biner', 'description' => $legend['binary'] ?? ''],
                ['title' => 'Ordinal', 'description' => $legend['ordinal'] ?? ''],
                ['title' => 'Penghasilan', 'description' => $legend['income'] ?? ''],
                ['title' => 'Tanggungan', 'description' => $legend['dependents'] ?? ''],
                ['title' => 'Status Ortu', 'description' => $legend['parent'] ?? ''],
                ['title' => 'Daya Listrik', 'description' => $legend['power'] ?? ''],
            ],
            'context_notes' => [
                [
                    'title' => 'Dokumen Pendukung',
                    'description' => $documentUrl
                        ? 'Buka dokumen pendukung untuk memastikan hasil encoding sesuai berkas yang diserahkan mahasiswa.'
                        : 'Belum ada tautan dokumen pendukung yang dapat ditinjau dari pengajuan ini.',
                    'cta' => $documentUrl ? 'Lihat Dokumen Lengkap' : null,
                    'href' => $documentUrl,
                    'icon' => 'description',
                    'icon_wrap' => 'bg-primary-container text-primary',
                ],
                [
                    'title' => 'Konteks Rekomendasi Model',
                    'description' => $snapshot
                        ? 'CatBoost '.$snapshot->catboost_label.' dan Naive Bayes '.$snapshot->naive_bayes_label.'. Gunakan ini sebagai pembanding, bukan keputusan akhir.'
                        : 'Snapshot rekomendasi belum tersedia. Bangun rekomendasi sistem bila admin perlu membandingkan hasil model.',
                    'cta' => null,
                    'href' => null,
                    'icon' => 'psychology',
                    'icon_wrap' => 'bg-secondary-fixed text-on-secondary-fixed',
                ],
            ],
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
            'ordinal' => 'Ordinal: 1 = prioritas tertinggi, 5 = prioritas lebih rendah',
            'income' => 'Penghasilan: 1 = Rp0, 2 = < 1jt, 3 = 1-2jt, 4 = 2-4jt, 5 = ≥ 4jt',
            'dependents' => 'Jumlah tanggungan: 1 = ≥ 6, 2 = 5, 3 = 4, 4 = 2-3, 5 = ≤ 1',
            'child' => 'Anak ke-: 1 = ≥ 5, 2 = 4, 3 = 3 (atau tidak diketahui), 4 = 2, 5 = 1',
            'parent' => 'Status orang tua: 1 = yatim piatu, 2 = yatim/piatu, 3 = lengkap',
            'house' => 'Status rumah: 1 = tidak memiliki, 2 = menumpang, 3 = sewa, 4 = milik sendiri',
            'power' => 'Daya listrik: 1 = non-PLN, 2 = 450 VA, 3 = 900 VA, 4 = 1300 VA, 5 = > 1300 VA',
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
                1 => '1 - Rp0 / Tidak Berpenghasilan',
                2 => '2 - < Rp1.000.000',
                3 => '3 - Rp1.000.000 s.d. < Rp2.000.000',
                4 => '4 - Rp2.000.000 s.d. < Rp4.000.000',
                5 => '5 - ≥ Rp4.000.000',
            ],
            'dependents' => [
                1 => '1 - ≥ 6 orang',
                2 => '2 - 5 orang',
                3 => '3 - 4 orang',
                4 => '4 - 2 sampai 3 orang',
                5 => '5 - 0 sampai 1 orang',
            ],
            'child' => [
                1 => '1 - Anak ke-5 atau lebih',
                2 => '2 - Anak ke-4',
                3 => '3 - Anak ke-3 (atau tidak diketahui)',
                4 => '4 - Anak ke-2',
                5 => '5 - Anak ke-1',
            ],
            'parent' => [
                1 => '1 - Yatim Piatu',
                2 => '2 - Yatim atau Piatu',
                3 => '3 - Lengkap',
            ],
            'house' => [
                1 => '1 - Tidak memiliki rumah',
                2 => '2 - Menumpang',
                3 => '3 - Sewa / Kontrak',
                4 => '4 - Milik sendiri',
            ],
            'power' => [
                1 => '1 - Tidak ada / Non-PLN',
                2 => '2 - PLN ≤ 450 VA',
                3 => '3 - PLN 451 - 900 VA',
                4 => '4 - PLN 901 - 1300 VA',
                5 => '5 - PLN > 1300 VA',
            ],
            'label' => [
                0 => 'Layak',
                1 => 'Indikasi',
            ],
        ];
    }
}
