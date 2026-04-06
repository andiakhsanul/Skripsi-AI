<?php

namespace App\Services;

use App\Models\ApplicationStatusLog;
use App\Models\StudentApplication;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminApplicationReviewService
{
    public function __construct(
        private readonly TrainingDataSyncService $trainingDataSyncService,
        private readonly ApplicationInferenceService $applicationInferenceService,
    ) {}

    public function detail(int $applicationId): StudentApplication
    {
        return StudentApplication::query()
            ->with([
                'student:id,name,email',
                'adminDecider:id,name,email',
                'currentEncoding',
                'modelSnapshot.modelVersion',
                'latestTrainingRow.finalizedBy:id,name,email',
                'logs' => fn ($query) => $query
                    ->with('actor:id,name,email')
                    ->orderByDesc('id'),
            ])
            ->findOrFail($applicationId);
    }

    public function documentUrl(StudentApplication $application): ?string
    {
        if ($application->submitted_pdf_path) {
            return Storage::disk('public')->url($application->submitted_pdf_path);
        }

        return $application->source_document_link;
    }

    /**
     * @return array<string, mixed>
     */
    public function viewPayload(StudentApplication $application, ?string $documentUrl): array
    {
        $student = $application->student;
        $snapshot = $application->modelSnapshot;
        $trainingRow = $application->latestTrainingRow;
        $displayName = $student?->name ?? $application->applicant_name ?? 'Mahasiswa';
        $displayEmail = $student?->email ?? $application->applicant_email ?? 'Email belum tersedia';
        $displayMeta = collect([
            $application->faculty,
            $application->study_program,
            $application->source_reference_number,
        ])->filter()->implode(' • ');

        $statusLabels = [
            'Submitted' => 'Menunggu Verifikasi',
            'Verified' => 'Terverifikasi',
            'Rejected' => 'Ditolak',
        ];

        $statusClasses = [
            'Submitted' => 'bg-secondary-fixed text-on-secondary-fixed border border-secondary/20',
            'Verified' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
            'Rejected' => 'bg-error-container text-on-error-container border border-red-200',
        ];

        $priorityClasses = [
            'high' => 'bg-error-container text-on-error-container border border-red-200',
            'normal' => 'bg-slate-100 text-slate-600 border border-slate-200',
        ];

        $confidencePercent = static fn (?float $value): float => round(((float) $value) * 100, 1);
        $formatBoolean = static fn (?int $value): string => (int) $value === 1 ? 'Ya' : 'Tidak';
        $formatCurrency = static fn (?int $value): string => $value !== null ? 'Rp '.number_format($value, 0, ',', '.') : '-';

        $recommendationLabel = $snapshot?->final_recommendation ?? 'Belum ada';
        $reviewPriorityLabel = $snapshot?->review_priority === 'high' ? 'Tinggi' : 'Normal';
        $scorePercent = $confidencePercent($snapshot?->catboost_confidence);

        return [
            'display_name' => $displayName,
            'display_email' => $displayEmail,
            'display_meta' => $displayMeta,
            'status_labels' => $statusLabels,
            'status_classes' => $statusClasses,
            'priority_classes' => $priorityClasses,
            'score' => [
                'percent' => $scorePercent,
                'tone' => $recommendationLabel === 'Indikasi'
                    ? 'bg-error text-white shadow-lg shadow-error/20'
                    : 'bg-primary text-white shadow-lg shadow-primary/20',
                'recommendation_label' => $recommendationLabel,
                'recommendation_display' => $recommendationLabel === 'Belum ada' ? 'Belum Ada' : Str::upper($recommendationLabel),
                'review_priority_label' => $reviewPriorityLabel,
                'catboost_percent' => $confidencePercent($snapshot?->catboost_confidence),
                'naive_bayes_percent' => $confidencePercent($snapshot?->naive_bayes_confidence),
                'snapshot_time' => $snapshot?->snapshotted_at?->format('d M Y H:i') ?? 'Belum ada',
                'model_version_name' => $snapshot?->modelVersion?->version_name ?? 'Snapshot belum dibuat',
            ],
            'review_guides' => [
                'Model hanya memberi rekomendasi. Keputusan final tetap ditetapkan admin.',
                'Dokumen pendukung harus menjadi acuan utama saat hasil model dan data mentah tidak selaras.',
                'Jika pengajuan sudah final dan data training tersedia, koreksi training bisa dibuka setelah review selesai.',
            ],
            'review_highlights' => [
                [
                    'label' => 'Status Pengajuan',
                    'value' => $statusLabels[$application->status] ?? $application->status,
                    'note' => 'Posisi alur verifikasi saat ini',
                    'icon' => 'fact_check',
                    'icon_wrap' => 'bg-primary-container text-primary',
                ],
                [
                    'label' => 'Rekomendasi Model',
                    'value' => $recommendationLabel,
                    'note' => $snapshot ? 'Rekomendasi primer mengikuti CatBoost' : 'Bangun snapshot model lebih dulu',
                    'icon' => 'psychology',
                    'icon_wrap' => $recommendationLabel === 'Indikasi'
                        ? 'bg-error-container text-error'
                        : 'bg-emerald-50 text-emerald-700',
                ],
                [
                    'label' => 'Prioritas Review',
                    'value' => $snapshot ? $reviewPriorityLabel : 'Belum dinilai',
                    'note' => $snapshot?->disagreement_flag
                        ? 'Ada disagreement, cek dokumen dan data mentah'
                        : 'Dipakai untuk membantu urutan review admin',
                    'icon' => 'priority_high',
                    'icon_wrap' => ($snapshot?->review_priority ?? 'normal') === 'high'
                        ? 'bg-error-container text-error'
                        : 'bg-slate-100 text-slate-700',
                ],
                [
                    'label' => 'Dokumen',
                    'value' => $documentUrl ? 'Tersedia' : 'Belum ada',
                    'note' => $documentUrl ? 'Dokumen pendukung siap ditinjau admin' : 'Belum ada tautan atau file pendukung',
                    'icon' => 'description',
                    'icon_wrap' => $documentUrl
                        ? 'bg-primary-container text-primary'
                        : 'bg-slate-100 text-slate-700',
                ],
            ],
            'raw_sections' => [
                [
                    'title' => 'Bantuan Sosial dan Dokumen',
                    'subtitle' => 'Indikator ya/tidak yang dikirim mahasiswa atau hasil impor admin.',
                    'icon' => 'badge',
                    'icon_wrap' => 'bg-blue-50 text-blue-700',
                    'items' => [
                        ['label' => 'KIP', 'value' => $formatBoolean($application->kip), 'note' => 'Kartu Indonesia Pintar'],
                        ['label' => 'PKH', 'value' => $formatBoolean($application->pkh), 'note' => 'Program Keluarga Harapan'],
                        ['label' => 'KKS', 'value' => $formatBoolean($application->kks), 'note' => 'Kartu Keluarga Sejahtera'],
                        ['label' => 'DTKS', 'value' => $formatBoolean($application->dtks), 'note' => 'Data Terpadu Kesejahteraan Sosial'],
                        ['label' => 'SKTM', 'value' => $formatBoolean($application->sktm), 'note' => 'Surat Keterangan Tidak Mampu'],
                    ],
                ],
                [
                    'title' => 'Ekonomi Keluarga',
                    'subtitle' => 'Nilai mentah rupiah dan beban keluarga sebelum proses encoding.',
                    'icon' => 'payments',
                    'icon_wrap' => 'bg-emerald-50 text-emerald-700',
                    'items' => [
                        ['label' => 'Penghasilan Ayah', 'value' => $formatCurrency($application->penghasilan_ayah_rupiah), 'note' => 'Nominal mentah rupiah'],
                        ['label' => 'Penghasilan Ibu', 'value' => $formatCurrency($application->penghasilan_ibu_rupiah), 'note' => 'Nominal mentah rupiah'],
                        ['label' => 'Penghasilan Gabungan', 'value' => $formatCurrency($application->penghasilan_gabungan_rupiah), 'note' => 'Penjumlahan ayah dan ibu'],
                        ['label' => 'Jumlah Tanggungan', 'value' => $application->jumlah_tanggungan_raw ?? '-', 'note' => 'Angka mentah keluarga'],
                        ['label' => 'Anak Ke-', 'value' => $application->anak_ke_raw ?? '-', 'note' => 'Urutan anak dalam keluarga'],
                    ],
                ],
                [
                    'title' => 'Kondisi Rumah Tangga',
                    'subtitle' => 'Teks mentah yang nantinya dipetakan ke aturan model.',
                    'icon' => 'home_work',
                    'icon_wrap' => 'bg-amber-50 text-amber-700',
                    'items' => [
                        ['label' => 'Status Orang Tua', 'value' => $application->status_orangtua_text ?? '-', 'note' => 'Teks mentah sebelum encoding'],
                        ['label' => 'Status Rumah', 'value' => $application->status_rumah_text ?? '-', 'note' => 'Teks mentah sebelum encoding'],
                        ['label' => 'Daya Listrik', 'value' => $application->daya_listrik_text ?? '-', 'note' => 'Teks mentah sebelum encoding'],
                    ],
                ],
            ],
            'context_cards' => [
                [
                    'title' => 'Dokumen Pendukung',
                    'description' => $documentUrl
                        ? 'Buka dokumen pendukung untuk memeriksa slip penghasilan, bantuan sosial, atau bukti rumah tangga sebelum memberi keputusan.'
                        : 'Belum ada dokumen pendukung yang tersimpan untuk pengajuan ini.',
                    'cta' => $documentUrl ? 'Lihat Dokumen Pendukung' : null,
                    'href' => $documentUrl,
                    'icon' => 'description',
                    'icon_wrap' => 'bg-primary-container text-primary',
                ],
                [
                    'title' => 'Status Data Training',
                    'description' => $trainingRow
                        ? 'Pengajuan ini sudah memiliki row data training. Admin dapat membuka koreksi training jika perlu menyempurnakan label atau encoding akhir.'
                        : 'Data training baru tersedia setelah admin memberi keputusan final dan sinkronisasi dijalankan.',
                    'cta' => $trainingRow ? 'Buka Koreksi Data Training' : null,
                    'href' => $trainingRow ? route('admin.training-data.show', $application) : null,
                    'icon' => 'fact_check',
                    'icon_wrap' => $trainingRow ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-700',
                ],
            ],
            'source_summary' => [
                'submit_label' => $application->submission_source === 'offline_admin_import' ? 'Import Admin Offline' : 'Submit Mahasiswa Online',
                'reference_label' => $application->source_reference_number ?? 'Tidak ada nomor referensi',
                'reference_meta' => 'Sheet '.($application->source_sheet_name ?? '-').' · Baris '.($application->source_row_number ?? '-'),
            ],
        ];
    }

    public function syncPredictionSnapshot(int $applicationId, ?int $actorUserId = null): StudentApplication
    {
        $application = StudentApplication::query()->findOrFail($applicationId);
        $this->applicationInferenceService->syncPredictionSnapshot($application, $actorUserId);

        return $this->detail($applicationId);
    }

    /**
     * @return array{processed:int, succeeded:int, failed:int, errors:list<array{application_id:int, message:string}>}
     */
    public function batchRunPredictions(?int $actorUserId = null, ?string $status = null, ?int $limit = null, bool $onlyMissing = true): array
    {
        $query = StudentApplication::query()
            ->when($status !== null, fn ($builder) => $builder->where('status', $status))
            ->when($onlyMissing, fn ($builder) => $builder->whereDoesntHave('modelSnapshot'))
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $applications = $query->get();
        $success = 0;
        $failed = [];

        foreach ($applications as $application) {
            try {
                $this->applicationInferenceService->syncPredictionSnapshot($application, $actorUserId);
                $success++;
            } catch (\Throwable $throwable) {
                $failed[] = [
                    'application_id' => $application->id,
                    'message' => $throwable->getMessage(),
                ];
            }
        }

        return [
            'processed' => $applications->count(),
            'succeeded' => $success,
            'failed' => count($failed),
            'errors' => $failed,
        ];
    }

    public function finalize(int $applicationId, int $actorUserId, string $status, ?string $note = null): StudentApplication
    {
        $application = StudentApplication::query()->findOrFail($applicationId);

        if (! in_array($status, ['Verified', 'Rejected'], true)) {
            throw new DomainException('Status keputusan admin tidak valid.');
        }

        if ($application->status !== 'Submitted') {
            throw new DomainException('Pengajuan hanya dapat diputuskan dari status Submitted.');
        }

        if (! $application->modelSnapshot()->exists()) {
            throw new DomainException('Rekomendasi model belum tersedia. Bangun snapshot prediksi terlebih dahulu sebelum memberi keputusan final.');
        }

        DB::transaction(function () use ($application, $actorUserId, $status, $note): void {
            $previousStatus = $application->status;

            $application->forceFill([
                'status' => $status,
                'admin_decision' => $status,
                'admin_decided_by' => $actorUserId,
                'admin_decision_note' => $note,
                'admin_decided_at' => now(),
            ])->save();

            ApplicationStatusLog::query()->create([
                'application_id' => $application->id,
                'actor_user_id' => $actorUserId,
                'from_status' => $previousStatus,
                'to_status' => $status,
                'action' => strtolower($status),
                'note' => $note,
                'metadata' => ['admin_decision' => $status],
            ]);

            $this->trainingDataSyncService->syncFromApplication($application, $actorUserId);
        });

        return $this->detail($applicationId);
    }
}
