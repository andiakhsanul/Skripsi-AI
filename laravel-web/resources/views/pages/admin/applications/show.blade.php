@extends('layouts.portal')

@section('title', 'Detail Pengajuan | KIP-K UNAIR')
@section('description', 'Halaman review admin untuk data mentah, rekomendasi model, dan keputusan final pengajuan KIP-K UNAIR')

@php
    $notice = session('admin_notice');
    $snapshot = $application->modelSnapshot;
    $trainingRow = $application->latestTrainingRow;
    $displayName = $page['display_name'];
    $displayEmail = $page['display_email'];
    $displayMeta = $page['display_meta'];
    $statusLabels = $page['status_labels'];
    $statusClasses = $page['status_classes'];
    $priorityClasses = $page['priority_classes'];
    $score = $page['score'];
    $reviewGuides = $page['review_guides'];
    $reviewHighlights = $page['review_highlights'];
    $rawSections = $page['raw_sections'];
    $contextCards = $page['context_cards'];
    $sourceSummary = $page['source_summary'];
@endphp

@section('content')
<main class="min-h-screen bg-background">
    @include('pages.admin.partials.sidebar', ['active' => 'review', 'application' => $application])

    <div class="md:ml-64">
        <x-admin.topbar
            :admin="$admin"
            title="Detail Pengajuan"
            :subtitle="$displayMeta !== '' ? $displayMeta : null"
            title-class="text-xl font-extrabold tracking-tight text-blue-700"
            subtitle-class="mt-1 text-xs font-medium text-slate-400"
        >
            <x-slot:leading>
                <a href="{{ route('admin.dashboard') }}" class="rounded-full p-2 transition-colors hover:bg-slate-100">
                    <span class="material-symbols-outlined text-slate-600">arrow_back</span>
                </a>
            </x-slot:leading>

            <x-slot:meta>
                <div class="hidden text-right md:block">
                    <div class="flex flex-wrap items-center justify-end gap-2 text-sm text-slate-500">
                        <span class="font-semibold text-slate-700">{{ $displayName }}</span>
                        <span class="h-1.5 w-1.5 rounded-full bg-slate-300"></span>
                        <span>{{ $displayEmail }}</span>
                    </div>
                </div>
                <span class="rounded-full px-3 py-1 text-[11px] font-black uppercase tracking-[0.18em] {{ $statusClasses[$application->status] ?? 'bg-slate-100 text-slate-600 border border-slate-200' }}">
                    {{ $statusLabels[$application->status] ?? $application->status }}
                </span>
            </x-slot:meta>
        </x-admin.topbar>

        <div class="mx-auto w-full max-w-7xl space-y-8 p-6 md:p-8">
            @if ($notice)
                <div class="rounded-2xl border px-5 py-4 {{ ($notice['type'] ?? 'success') === 'error' ? 'border-red-200 bg-error-container text-on-error-container' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">
                    <p class="text-sm font-black uppercase tracking-[0.18em]">{{ $notice['title'] ?? 'Informasi Sistem' }}</p>
                    <p class="mt-1 text-sm font-medium">{{ $notice['message'] ?? '' }}</p>
                </div>
            @endif

            <section class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <div class="rounded-3xl border-t-4 border-secondary bg-white p-6 shadow-lg md:col-span-2">
                    <div class="mb-5 flex items-center gap-3">
                        <span class="material-symbols-outlined text-primary">info</span>
                        <h2 class="text-lg font-bold text-on-surface">Panduan Review Verifikator</h2>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($reviewGuides as $index => $guide)
                            <div class="rounded-2xl bg-surface-container p-4">
                                <p class="text-xs font-black uppercase tracking-[0.18em] text-primary">Langkah {{ $index + 1 }}</p>
                                <p class="mt-2 text-xs leading-6 text-on-surface-variant">{{ $guide }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="relative overflow-hidden rounded-3xl {{ $score['tone'] }} p-6">
                    <div class="absolute -right-10 -bottom-10 h-32 w-32 rounded-full bg-white/10 blur-2xl"></div>
                    <div class="relative z-10">
                        <p class="text-sm font-black uppercase tracking-[0.2em] text-white/80">Skor AI Saat Ini</p>
                        <div class="mt-3 text-5xl font-extrabold">{{ number_format($score['percent'], 1, ',', '.') }}<span class="text-xl opacity-70">%</span></div>
                        <div class="mt-4 inline-flex items-center gap-2 rounded-full bg-white/20 px-3 py-1 text-xs font-black uppercase tracking-[0.16em] backdrop-blur-sm">
                            <span class="material-symbols-outlined text-sm">stars</span>
                            Klasifikasi: {{ $score['recommendation_display'] }}
                        </div>
                        <p class="mt-4 text-sm leading-6 text-white/85">
                            Nilai ini membantu admin membaca kecenderungan model sebelum menetapkan keputusan akhir pada pengajuan.
                        </p>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($reviewHighlights as $highlight)
                    @include('pages.admin.applications.partials.highlight-card', ['highlight' => $highlight])
                @endforeach
            </section>

            <section class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                <div class="overflow-hidden rounded-3xl border-t-4 border-yellow-500 bg-white shadow-lg lg:col-span-2">
                    <div class="flex items-center justify-between border-b border-slate-50 px-6 py-5">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">psychology</span>
                            <h2 class="font-bold text-slate-800">Analisis Rekomendasi Sistem</h2>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">
                            {{ $score['model_version_name'] }}
                        </span>
                    </div>

                    <div class="grid gap-10 p-8 md:grid-cols-2">
                        <div class="space-y-6">
                            @if ($snapshot)
                                <div>
                                    <div class="mb-2 flex justify-between">
                                        <span class="text-sm font-semibold text-slate-700">Probabilitas CatBoost</span>
                                        <span class="text-sm font-bold text-primary">{{ $score['catboost_percent'] }}%</span>
                                    </div>
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full bg-primary" style="width: {{ $score['catboost_percent'] }}%"></div>
                                    </div>
                                    <p class="mt-2 text-xs font-medium text-slate-500">Label: <span class="font-bold text-slate-700">{{ $snapshot->catboost_label ?? '-' }}</span></p>
                                </div>

                                <div>
                                    <div class="mb-2 flex justify-between">
                                        <span class="text-sm font-semibold text-slate-700">Probabilitas Naive Bayes</span>
                                        <span class="text-sm font-bold text-yellow-600">{{ $score['naive_bayes_percent'] }}%</span>
                                    </div>
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full bg-secondary" style="width: {{ $score['naive_bayes_percent'] }}%"></div>
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

                                <div class="rounded-2xl bg-surface-container p-4">
                                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Prioritas Review</p>
                                    <p class="mt-2 inline-flex rounded-full px-3 py-1 text-xs font-black uppercase {{ $priorityClasses[$snapshot->review_priority ?? 'normal'] ?? 'bg-slate-100 text-slate-600 border border-slate-200' }}">
                                        {{ $snapshot->review_priority === 'high' ? 'Tinggi' : 'Normal' }}
                                    </p>
                                </div>
                            @else
                                <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-6 text-sm font-medium text-slate-600">
                                    Snapshot model belum ada. Bangun rekomendasi lebih dulu agar admin bisa meninjau hasil CatBoost dan Naive Bayes sebelum memberi keputusan final.
                                </div>
                            @endif
                        </div>

                        <div class="flex flex-col items-center justify-center rounded-2xl border border-slate-100 bg-surface-container-low p-6 text-center shadow-inner">
                            <span class="mb-2 text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Rekomendasi Model</span>
                            <div class="relative mb-4">
                                <svg class="h-32 w-32 -rotate-90 transform">
                                    <circle class="text-slate-200" cx="64" cy="64" r="58" fill="transparent" stroke="currentColor" stroke-width="8"></circle>
                                    <circle class="{{ $score['recommendation_label'] === 'Layak' ? 'text-primary' : 'text-error' }}" cx="64" cy="64" r="58" fill="transparent" stroke="currentColor" stroke-width="8" stroke-dasharray="364.4" stroke-dashoffset="{{ 364.4 - (364.4 * (($snapshot?->catboost_confidence ?? 0.5))) }}"></circle>
                                </svg>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span class="text-2xl font-black {{ $score['recommendation_label'] === 'Layak' ? 'text-primary' : 'text-error' }}">
                                        {{ $snapshot?->final_recommendation ?? 'BELUM ADA' }}
                                    </span>
                                </div>
                            </div>
                            <p class="max-w-[240px] text-sm font-medium text-slate-500">
                                Model hanya memberi rekomendasi. Keputusan final tetap ditetapkan oleh admin setelah melihat data mentah dan dokumen.
                            </p>
                            <p class="mt-4 text-xs text-slate-400">
                                Snapshot dibuat: {{ $score['snapshot_time'] }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-4 rounded-3xl border border-slate-100 bg-white p-6 shadow-lg">
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
                            <p class="mt-2 text-sm font-bold text-on-surface">{{ $sourceSummary['submit_label'] }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $documentUrl ? 'Dokumen pendukung tersedia untuk ditinjau' : 'Belum ada tautan dokumen pendukung' }}</p>
                        </div>
                        <div class="rounded-2xl bg-surface-container p-4">
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Referensi Import</p>
                            <p class="mt-2 text-sm font-bold text-on-surface">{{ $sourceSummary['reference_label'] }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $sourceSummary['reference_meta'] }}</p>
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
                            @if ($trainingRow)
                                <a href="{{ route('admin.training-data.show', $application) }}" class="mt-4 inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-white">
                                    <span class="material-symbols-outlined text-sm">edit_note</span>
                                    Buka Koreksi Data Training
                                </a>
                            @endif
                        </div>

                        {{-- Panel Konfirmasi Hasil AI --}}
                        @if ($trainingRow && $snapshot)
                            <div class="rounded-2xl border {{ $trainingRow->admin_corrected ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }} p-4">
                                <div class="flex items-center gap-2 mb-3">
                                    <span class="material-symbols-outlined text-lg {{ $trainingRow->admin_corrected ? 'text-emerald-600' : 'text-amber-600' }}">
                                        {{ $trainingRow->admin_corrected ? 'verified' : 'help_outline' }}
                                    </span>
                                    <p class="text-[11px] font-black uppercase tracking-[0.18em] {{ $trainingRow->admin_corrected ? 'text-emerald-700' : 'text-amber-700' }}">
                                        {{ $trainingRow->admin_corrected ? 'Konfirmasi AI Selesai' : 'Konfirmasi Hasil AI' }}
                                    </p>
                                </div>

                                <div class="grid grid-cols-2 gap-3 mb-3">
                                    <div class="rounded-xl bg-white/80 p-3">
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Rekomendasi AI</p>
                                        <p class="mt-1 text-sm font-black {{ ($snapshot->final_recommendation ?? '') === 'Indikasi' ? 'text-error' : 'text-primary' }}">
                                            {{ $snapshot->final_recommendation ?? 'Belum Ada' }}
                                        </p>
                                        <p class="mt-0.5 text-[10px] text-slate-500">
                                            CB: {{ $snapshot->catboost_label ?? '-' }} · NB: {{ $snapshot->naive_bayes_label ?? '-' }}
                                        </p>
                                    </div>
                                    <div class="rounded-xl bg-white/80 p-3">
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Keputusan Admin</p>
                                        <p class="mt-1 text-sm font-black {{ $application->status === 'Rejected' ? 'text-error' : 'text-emerald-600' }}">
                                            {{ $application->status === 'Verified' ? 'Layak' : 'Indikasi' }}
                                        </p>
                                        <p class="mt-0.5 text-[10px] text-slate-500">
                                            {{ $application->admin_decided_at?->format('d M Y H:i') ?? '-' }}
                                        </p>
                                    </div>
                                </div>

                                @if ($trainingRow->admin_corrected)
                                    <div class="rounded-xl bg-white/60 p-3">
                                        <p class="text-xs font-bold text-emerald-700">
                                            <span class="material-symbols-outlined text-sm align-middle">check_circle</span>
                                            Data training sudah dikonfirmasi dan siap untuk retrain.
                                        </p>
                                        @if ($trainingRow->correction_note)
                                            <p class="mt-1 text-xs text-slate-600">{{ $trainingRow->correction_note }}</p>
                                        @endif
                                    </div>
                                @else
                                    <p class="text-xs font-medium leading-5 {{ $trainingRow->admin_corrected ? 'text-emerald-700' : 'text-amber-800' }} mb-3">
                                        Apakah rekomendasi AI sudah sesuai dengan keputusan admin? Konfirmasi ini menentukan kesiapan data untuk pelatihan ulang model.
                                    </p>

                                    <form method="POST" action="{{ route('admin.applications.confirm-ai', $application) }}">
                                        @csrf
                                        <div class="mb-3">
                                            <textarea name="correction_note" rows="2" class="w-full rounded-xl border-none bg-white/80 p-3 text-xs text-slate-700 placeholder:text-slate-400 focus:ring-2 focus:ring-primary/20" placeholder="Catatan opsional (alasan koreksi jika AI salah)..."></textarea>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2">
                                            <button type="submit" name="ai_correct" value="1" class="flex items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3 py-2.5 text-xs font-black text-white shadow transition hover:bg-emerald-700">
                                                <span class="material-symbols-outlined text-sm">thumb_up</span>
                                                AI Benar
                                            </button>
                                            <button type="submit" name="ai_correct" value="0" class="flex items-center justify-center gap-1.5 rounded-xl bg-amber-600 px-3 py-2.5 text-xs font-black text-white shadow transition hover:bg-amber-700">
                                                <span class="material-symbols-outlined text-sm">thumb_down</span>
                                                AI Salah
                                            </button>
                                        </div>
                                    </form>
                                @endif
                            </div>
                        @endif
                    @endif
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
                        @include('pages.admin.applications.partials.raw-section', ['section' => $section])
                    @endforeach
                </div>
            </section>

            <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-lg">
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
                            <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Versi Model</p>
                            <p class="mt-2 text-sm font-bold text-on-surface">{{ $score['model_version_name'] }}</p>
                            <p class="mt-1 text-xs text-slate-500">Snapshot: {{ $score['snapshot_time'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-lg">
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

            <section class="grid grid-cols-1 gap-6 pb-12 md:grid-cols-2">
                @foreach ($contextCards as $card)
                    @include('pages.admin.applications.partials.context-card', ['card' => $card])
                @endforeach
            </section>

            <x-admin.footer />
        </div>
    </div>
</main>
@endsection
