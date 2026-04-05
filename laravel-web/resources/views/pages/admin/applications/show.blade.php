@extends('layouts.portal')

@section('title', 'Detail Pengajuan | KIP-K UNAIR')
@section('description', 'Halaman review admin untuk data mentah, rekomendasi model, dan keputusan final pengajuan KIP-K UNAIR')

@php
    $notice = session('admin_notice');
    $student = $application->student;
    $snapshot = $application->modelSnapshot;
    $trainingRow = $application->latestTrainingRow;
    $adminName = $admin->name ?? 'Admin';
    $adminInitials = collect(preg_split('/\s+/', trim($adminName)) ?: [])
        ->filter()
        ->map(fn ($part) => strtoupper(mb_substr($part, 0, 1)))
        ->take(2)
        ->implode('');

    $displayName = $student?->name ?? $application->applicant_name ?? 'Mahasiswa';
    $displayEmail = $student?->email ?? $application->applicant_email ?? 'Email belum tersedia';

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

    $formatBoolean = fn (?int $value): string => (int) $value === 1 ? 'Ya' : 'Tidak';
    $formatCurrency = fn (?int $value): string => $value !== null ? 'Rp '.number_format($value, 0, ',', '.') : '-';
    $confidencePercent = fn (?float $value): float => round(((float) $value) * 100, 1);
    $reviewPriorityLabel = $snapshot?->review_priority === 'high' ? 'Tinggi' : 'Normal';
    $trainingStatusLabel = $trainingRow
        ? 'Sudah masuk data training'
        : (in_array($application->status, ['Verified', 'Rejected'], true) ? 'Belum disinkronkan ke data training' : 'Menunggu keputusan final');
    $trainingStatusNote = $trainingRow
        ? 'Label akhir: '.$trainingRow->label.' · Sinkron pada '.($trainingRow->finalized_at?->format('d M Y H:i') ?? '-')
        : (in_array($application->status, ['Verified', 'Rejected'], true)
            ? 'Pengajuan sudah final, tetapi belum disalin ke spk_training_data.'
            : 'Baris training baru dibuat setelah admin memberi keputusan final.');

    $reviewHighlights = [
        [
            'label' => 'Status Pengajuan',
            'value' => $statusLabels[$application->status] ?? $application->status,
            'note' => 'Posisi alur verifikasi saat ini',
            'icon' => 'fact_check',
            'icon_wrap' => 'bg-primary-container text-primary',
        ],
        [
            'label' => 'Rekomendasi Model',
            'value' => $snapshot?->final_recommendation ?? 'Belum ada',
            'note' => $snapshot ? 'Rekomendasi primer mengikuti CatBoost' : 'Bangun snapshot model lebih dulu',
            'icon' => 'psychology',
            'icon_wrap' => ($snapshot?->final_recommendation ?? null) === 'Indikasi'
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
            'label' => 'Status Data Training',
            'value' => $trainingStatusLabel,
            'note' => $trainingStatusNote,
            'icon' => 'database',
            'icon_wrap' => $trainingRow
                ? 'bg-emerald-50 text-emerald-700'
                : 'bg-slate-100 text-slate-700',
        ],
    ];

    $rawSections = [
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
    ];
@endphp

@section('content')
<main class="min-h-screen bg-background">
    <aside class="fixed left-0 top-0 hidden h-screen w-64 flex-col border-r border-slate-100 bg-slate-50 md:flex">
        <div class="p-6">
            <p class="text-lg font-black uppercase tracking-[0.2em] text-blue-900">KIP-K UNAIR</p>
        </div>

        <nav class="flex flex-1 flex-col gap-2 p-4">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 rounded-md px-4 py-3 font-semibold text-slate-500 transition-transform duration-200 hover:-translate-y-0.5 hover:bg-white">
                <span class="material-symbols-outlined">dashboard_customize</span>
                <span class="text-sm">Dasbor Admin</span>
            </a>

            <a href="{{ route('admin.applications.show', $application) }}" class="flex items-center gap-3 rounded-md border-t-2 border-yellow-500 bg-white px-4 py-3 font-semibold text-blue-700 shadow-sm transition-transform duration-200 hover:-translate-y-0.5">
                <span class="material-symbols-outlined">verified_user</span>
                <span class="text-sm">Review Pengajuan</span>
            </a>

            <a href="{{ route('admin.models.retrain') }}" class="flex items-center gap-3 rounded-md px-4 py-3 font-semibold text-slate-500 transition-transform duration-200 hover:-translate-y-0.5 hover:bg-white">
                <span class="material-symbols-outlined">model_training</span>
                <span class="text-sm">Retrain Model</span>
            </a>
        </nav>

        <div class="border-t border-slate-100 bg-slate-100/50 p-4">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex w-full items-center gap-3 px-4 py-3 text-left font-semibold text-slate-500 transition-colors hover:text-error">
                    <span class="material-symbols-outlined">logout</span>
                    <span class="text-sm">Keluar</span>
                </button>
            </form>
        </div>
    </aside>

    <div class="md:ml-64">
        <header class="sticky top-0 z-30 flex h-20 items-center justify-between border-b border-slate-100 bg-white/80 px-6 backdrop-blur-md md:px-10">
            <div class="flex items-center gap-4">
                <a href="{{ route('admin.dashboard') }}" class="rounded-full p-2 transition-colors hover:bg-slate-100">
                    <span class="material-symbols-outlined text-slate-600">arrow_back</span>
                </a>
                <div>
                    <h1 class="text-xl font-extrabold tracking-tight text-blue-700">Detail Pengajuan</h1>
                    <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                        <span class="font-semibold text-slate-700">{{ $displayName }}</span>
                        <span class="h-1.5 w-1.5 rounded-full bg-slate-300"></span>
                        <span>{{ $displayEmail }}</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <span class="rounded-full px-3 py-1 text-[11px] font-black uppercase tracking-[0.18em] {{ $statusClasses[$application->status] ?? 'bg-slate-100 text-slate-600 border border-slate-200' }}">
                    {{ $statusLabels[$application->status] ?? $application->status }}
                </span>
                <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 border-primary/10 bg-primary-container font-bold text-primary shadow-sm">
                    {{ $adminInitials !== '' ? $adminInitials : 'AD' }}
                </div>
            </div>
        </header>

        <div class="mx-auto w-full max-w-7xl space-y-8 p-6 md:p-8">
            @if ($notice)
                <div class="rounded-2xl border px-5 py-4 {{ ($notice['type'] ?? 'success') === 'error' ? 'border-red-200 bg-error-container text-on-error-container' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">
                    <p class="text-sm font-black uppercase tracking-[0.18em]">{{ $notice['title'] ?? 'Informasi Sistem' }}</p>
                    <p class="mt-1 text-sm font-medium">{{ $notice['message'] ?? '' }}</p>
                </div>
            @endif

            <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($reviewHighlights as $highlight)
                    <div class="rounded-xl border border-slate-100 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex h-11 w-11 items-center justify-center rounded-2xl {{ $highlight['icon_wrap'] }}">
                                <span class="material-symbols-outlined">{{ $highlight['icon'] }}</span>
                            </div>
                            <span class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">{{ $highlight['label'] }}</span>
                        </div>
                        <p class="mt-5 text-lg font-black leading-6 text-slate-800">{{ $highlight['value'] }}</p>
                        <p class="mt-2 text-xs font-medium leading-5 text-slate-500">{{ $highlight['note'] }}</p>
                    </div>
                @endforeach
            </section>

            <section class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                <div class="overflow-hidden rounded-xl border-t-4 border-yellow-500 bg-white shadow-lg lg:col-span-2">
                    <div class="flex items-center justify-between border-b border-slate-50 px-6 py-5">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">psychology</span>
                            <h2 class="font-bold text-slate-800">Analisis AI Predictive</h2>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">
                            {{ $snapshot?->modelVersion?->version_name ?? 'Snapshot belum dibuat' }}
                        </span>
                    </div>

                    <div class="grid gap-10 p-8 md:grid-cols-2">
                        <div class="space-y-6">
                            @if ($snapshot)
                                <div>
                                    <div class="mb-2 flex justify-between">
                                        <span class="text-sm font-semibold text-slate-700">Probabilitas CatBoost</span>
                                        <span class="text-sm font-bold text-primary">{{ $confidencePercent($snapshot->catboost_confidence) }}%</span>
                                    </div>
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full bg-primary" style="width: {{ $confidencePercent($snapshot->catboost_confidence) }}%"></div>
                                    </div>
                                    <p class="mt-2 text-xs font-medium text-slate-500">Label: <span class="font-bold text-slate-700">{{ $snapshot->catboost_label ?? '-' }}</span></p>
                                </div>

                                <div>
                                    <div class="mb-2 flex justify-between">
                                        <span class="text-sm font-semibold text-slate-700">Probabilitas Naive Bayes</span>
                                        <span class="text-sm font-bold text-yellow-600">{{ $confidencePercent($snapshot->naive_bayes_confidence) }}%</span>
                                    </div>
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full bg-secondary" style="width: {{ $confidencePercent($snapshot->naive_bayes_confidence) }}%"></div>
                                    </div>
                                    <p class="mt-2 text-xs font-medium text-slate-500">Label: <span class="font-bold text-slate-700">{{ $snapshot->naive_bayes_label ?? '-' }}</span></p>
                                </div>

                                @if ($snapshot->disagreement_flag)
                                    <div class="flex gap-3 border-l-4 border-amber-400 bg-amber-50 p-4">
                                        <span class="material-symbols-outlined text-amber-600">warning</span>
                                        <div class="text-xs leading-relaxed text-amber-800">
                                            <strong class="mb-1 block">Perbedaan antar model terdeteksi</strong>
                                            CatBoost dan Naive Bayes memberi hasil yang berbeda. Admin perlu melihat data mentah dan dokumen pendukung sebelum memutuskan final.
                                        </div>
                                    </div>
                                @endif

                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div class="rounded-2xl bg-surface-container p-4">
                                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Prioritas Review</p>
                                        <p class="mt-2 inline-flex rounded-full px-3 py-1 text-xs font-black uppercase {{ $priorityClasses[$snapshot->review_priority ?? 'normal'] ?? 'bg-slate-100 text-slate-600 border border-slate-200' }}">{{ $snapshot->review_priority === 'high' ? 'Tinggi' : 'Normal' }}</p>
                                    </div>
                                    <div class="rounded-2xl bg-surface-container p-4">
                                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Aturan Pendamping</p>
                                        <p class="mt-2 text-sm font-bold text-slate-700">{{ $snapshot->rule_recommendation ?? '-' }}</p>
                                        <p class="mt-1 text-xs text-slate-500">Skor rule: {{ $snapshot->rule_score !== null ? number_format((float) $snapshot->rule_score, 2) : '-' }}</p>
                                    </div>
                                </div>
                            @else
                                <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-6 text-sm font-medium text-slate-600">
                                    Snapshot model belum ada. Bangun rekomendasi lebih dulu agar admin bisa meninjau hasil CatBoost dan Naive Bayes sebelum memberi keputusan final.
                                </div>
                            @endif
                        </div>

                        <div class="flex flex-col items-center justify-center rounded-xl border border-slate-100 bg-surface-container-low p-6 text-center shadow-inner">
                            <span class="mb-2 text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Rekomendasi Model</span>
                            <div class="relative mb-4">
                                <svg class="h-32 w-32 -rotate-90 transform">
                                    <circle class="text-slate-200" cx="64" cy="64" r="58" fill="transparent" stroke="currentColor" stroke-width="8"></circle>
                                    <circle class="{{ ($snapshot?->final_recommendation ?? 'Layak') === 'Layak' ? 'text-primary' : 'text-error' }}" cx="64" cy="64" r="58" fill="transparent" stroke="currentColor" stroke-width="8" stroke-dasharray="364.4" stroke-dashoffset="{{ 364.4 - (364.4 * (($snapshot?->catboost_confidence ?? 0.5))) }}"></circle>
                                </svg>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span class="text-2xl font-black {{ ($snapshot?->final_recommendation ?? 'Layak') === 'Layak' ? 'text-primary' : 'text-error' }}">
                                        {{ $snapshot?->final_recommendation ?? 'BELUM ADA' }}
                                    </span>
                                </div>
                            </div>
                            <p class="max-w-[240px] text-sm font-medium text-slate-500">
                                Model hanya memberi rekomendasi. Keputusan final tetap ditetapkan oleh admin setelah melihat data mentah dan dokumen.
                            </p>
                            <p class="mt-4 text-xs text-slate-400">
                                Snapshot dibuat: {{ $snapshot?->snapshotted_at?->format('d M Y H:i') ?? 'Belum ada' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-4 rounded-xl border border-slate-100 bg-white p-6 shadow-lg">
                    <div>
                        <h2 class="flex items-center gap-2 font-bold text-slate-800">
                            <span class="material-symbols-outlined text-slate-400">gavel</span>
                            Aksi Verifikasi
                        </h2>
                        <p class="mt-2 text-sm font-medium leading-6 text-slate-500">
                            Rekomendasi model menjadi bahan review. Hanya keputusan final admin yang boleh masuk ke data training dan retrain.
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl bg-surface-container p-4">
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Sumber Submit</p>
                            <p class="mt-2 text-sm font-bold text-on-surface">{{ $application->submission_source === 'offline_admin_import' ? 'Import Admin Offline' : 'Submit Mahasiswa Online' }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $documentUrl ? 'Dokumen pendukung tersedia untuk ditinjau' : 'Belum ada tautan dokumen pendukung' }}</p>
                        </div>
                        <div class="rounded-2xl bg-surface-container p-4">
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Referensi Import</p>
                            <p class="mt-2 text-sm font-bold text-on-surface">{{ $application->source_reference_number ?? 'Tidak ada nomor referensi' }}</p>
                            <p class="mt-1 text-xs text-slate-500">Sheet {{ $application->source_sheet_name ?? '-' }} · Baris {{ $application->source_row_number ?? '-' }}</p>
                        </div>
                    </div>

                    @if ($documentUrl)
                        <a href="{{ $documentUrl }}" target="_blank" class="flex items-center justify-center gap-2 rounded-2xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                            <span class="material-symbols-outlined text-lg">description</span>
                            Lihat Dokumen Pendukung
                        </a>
                    @endif

                    <form method="POST" action="{{ route('admin.applications.refresh-prediction', $application) }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-2xl bg-slate-900 px-4 py-3 text-sm font-black text-white transition hover:bg-slate-800">
                            <span class="material-symbols-outlined text-lg">auto_awesome</span>
                            {{ $snapshot ? 'Perbarui Rekomendasi Model' : 'Buat Rekomendasi Model' }}
                        </button>
                    </form>

                    @if ($application->status === 'Submitted')
                        <form method="POST" class="space-y-4">
                            @csrf
                            <div>
                                <label for="note" class="mb-2 block text-xs font-black uppercase tracking-[0.18em] text-slate-400">Catatan Verifikator</label>
                                <textarea id="note" name="note" rows="6" class="w-full rounded-2xl border-none bg-surface-container-low p-4 text-sm text-slate-700 placeholder:text-slate-400 focus:ring-2 focus:ring-primary/20" placeholder="Tulis alasan keputusan final admin..."></textarea>
                            </div>

                            @if (! $snapshot)
                                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-800">
                                    Bangun snapshot rekomendasi terlebih dahulu. Keputusan final admin dikunci agar selalu didahului rekomendasi model.
                                </div>
                            @endif

                            <div class="grid grid-cols-2 gap-3">
                                <button type="submit" formaction="{{ route('admin.applications.reject', $application) }}" @disabled(! $snapshot) class="flex items-center justify-center gap-2 rounded-2xl bg-error px-4 py-3 text-sm font-black text-white shadow-lg shadow-error/20 transition hover:bg-red-600 disabled:cursor-not-allowed disabled:opacity-50">
                                    <span class="material-symbols-outlined text-sm">close</span>
                                    Tolak / Indikasi
                                </button>
                                <button type="submit" formaction="{{ route('admin.applications.verify', $application) }}" @disabled(! $snapshot) class="flex items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-black text-white shadow-lg shadow-emerald-600/20 transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">
                                    <span class="material-symbols-outlined text-sm">done_all</span>
                                    Verifikasi / Layak
                                </button>
                            </div>
                        </form>
                    @else
                        <div class="rounded-2xl bg-surface-container p-4">
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Keputusan Final</p>
                            <p class="mt-2 text-lg font-black text-on-surface">{{ $statusLabels[$application->status] ?? $application->status }}</p>
                            <p class="mt-2 text-sm text-slate-600">Diputuskan oleh {{ $application->adminDecider?->name ?? 'Admin' }} pada {{ $application->admin_decided_at?->format('d M Y H:i') ?? '-' }}</p>
                            @if ($application->admin_decision_note)
                                <p class="mt-3 rounded-xl bg-white p-3 text-sm font-medium text-slate-600">{{ $application->admin_decision_note }}</p>
                            @endif
                        </div>
                    @endif

                    <div class="rounded-2xl bg-surface-container p-4">
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Status Data Training</p>
                        @if ($trainingRow)
                            <p class="mt-2 text-sm font-bold text-emerald-700">{{ $trainingStatusLabel }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $trainingStatusNote }}</p>
                            <a href="{{ route('admin.training-data.show', $application) }}" class="mt-3 inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-white">
                                <span class="material-symbols-outlined text-sm">edit_note</span>
                                Koreksi Data Training
                            </a>
                        @elseif (in_array($application->status, ['Verified', 'Rejected'], true))
                            <p class="mt-2 text-sm font-bold text-amber-700">{{ $trainingStatusLabel }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $trainingStatusNote }}</p>
                        @else
                            <p class="mt-2 text-sm font-bold text-slate-700">{{ $trainingStatusLabel }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $trainingStatusNote }}</p>
                        @endif
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-extrabold text-slate-800">Data Mentah Mahasiswa</h2>
                        <p class="mt-1 text-sm font-medium text-slate-500">Admin meninjau data asli yang dikirim mahasiswa atau hasil impor offline sebelum memberi keputusan final.</p>
                    </div>
                    <span class="text-xs font-medium italic text-slate-400">Halaman review ini menampilkan data mentah yang dikirim atau diimpor admin</span>
                </div>

                <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                    @foreach ($rawSections as $section)
                        <article class="overflow-hidden rounded-xl border border-slate-100 bg-white shadow-lg">
                            <div class="border-b border-slate-100 px-6 py-5">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl {{ $section['icon_wrap'] }}">
                                        <span class="material-symbols-outlined">{{ $section['icon'] }}</span>
                                    </div>
                                    <div>
                                        <h3 class="text-base font-extrabold text-slate-800">{{ $section['title'] }}</h3>
                                        <p class="mt-1 text-xs font-medium leading-5 text-slate-500">{{ $section['subtitle'] }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-3 p-5">
                                @foreach ($section['items'] as $item)
                                    <div class="rounded-2xl bg-surface-container-low p-4">
                                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">{{ $item['label'] }}</p>
                                        <p class="mt-2 text-base font-black text-slate-800">{{ $item['value'] }}</p>
                                        <p class="mt-1 text-xs font-medium leading-5 text-slate-500">{{ $item['note'] }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <div class="rounded-xl border border-slate-100 bg-white p-6 shadow-lg">
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Informasi Pengajuan</p>
                    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="rounded-2xl bg-surface-container p-4">
                            <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Sumber Submit</p>
                            <p class="mt-2 text-sm font-bold text-on-surface">{{ $application->submission_source === 'offline_admin_import' ? 'Import Admin Offline' : 'Submit Mahasiswa Online' }}</p>
                        </div>
                        <div class="rounded-2xl bg-surface-container p-4">
                            <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Fakultas / Program Studi</p>
                            <p class="mt-2 text-sm font-bold text-on-surface">{{ $application->faculty ?? '-' }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $application->study_program ?? '-' }}</p>
                        </div>
                        <div class="rounded-2xl bg-surface-container p-4">
                            <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Referensi Sumber</p>
                            <p class="mt-2 text-sm font-bold text-on-surface">{{ $application->source_reference_number ?? '-' }}</p>
                            <p class="mt-1 text-xs text-slate-500">Sheet: {{ $application->source_sheet_name ?? '-' }} · Baris: {{ $application->source_row_number ?? '-' }}</p>
                        </div>
                        <div class="rounded-2xl bg-surface-container p-4">
                            <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Status Training</p>
                            @if ($trainingRow)
                                <p class="mt-2 text-sm font-bold text-emerald-700">{{ $trainingStatusLabel }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $trainingStatusNote }}</p>
                                <a href="{{ route('admin.training-data.show', $application) }}" class="mt-3 inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-white">
                                    <span class="material-symbols-outlined text-sm">edit_note</span>
                                    Koreksi Data Training
                                </a>
                            @elseif (in_array($application->status, ['Verified', 'Rejected'], true))
                                <p class="mt-2 text-sm font-bold text-amber-700">{{ $trainingStatusLabel }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $trainingStatusNote }}</p>
                            @else
                                <p class="mt-2 text-sm font-bold text-slate-700">{{ $trainingStatusLabel }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $trainingStatusNote }}</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-100 bg-white p-6 shadow-lg">
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Riwayat Status</p>
                    <div class="mt-5 space-y-4">
                        @forelse ($application->logs as $log)
                            <div class="flex gap-3">
                                <div class="mt-1 h-3 w-3 rounded-full {{ $log->to_status === 'Rejected' ? 'bg-error' : ($log->to_status === 'Verified' ? 'bg-emerald-500' : 'bg-primary') }}"></div>
                                <div>
                                    <p class="text-sm font-bold text-on-surface">{{ $log->from_status }} → {{ $log->to_status }}</p>
                                    <p class="mt-1 text-xs font-medium text-slate-500">{{ $log->actor?->name ?? 'Sistem' }} · {{ $log->created_at?->format('d M Y H:i') ?? '-' }}</p>
                                    @if ($log->note)
                                        <p class="mt-1 text-sm text-slate-600">{{ $log->note }}</p>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-sm font-medium text-slate-500">Belum ada riwayat perubahan status.</p>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>
@endsection
