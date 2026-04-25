@extends('layouts.portal')

@section('title', 'Retrain Model | KIP-K UNAIR')
@section('description', 'Halaman admin untuk sinkronisasi data training dan retrain model machine learning KIP-K UNAIR')

@php
    $notice = session('admin_notice');
    $modelStatus = $payload['model_status'];
    $activeModel = $payload['active_model'];
    $activeModelEvaluation = $payload['active_model_evaluation'];
    $latestReadyModel = $payload['latest_ready_model'];
    $latestReadyModelEvaluation = $payload['latest_ready_model_evaluation'];
    $latestAttempt = $payload['latest_attempt'];
    $recentVersions = $payload['recent_model_versions'];
    $recentVersionsView = $payload['recent_model_versions_view'];
    $systemNotes = $payload['system_notes'];
    $statusToneClasses = $page['status_tone_classes'];
    $statusDotClasses = $page['status_dot_classes'];
    $noteToneClasses = $page['note_tone_classes'];
    $cards = $page['cards'];
@endphp

@section('content')
<main class="min-h-screen bg-background">
    @include('pages.admin.partials.sidebar', ['active' => 'retrain'])

    <div class="md:ml-64">
        <x-admin.topbar
            :admin="$admin"
            title="Retrain Model"
            subtitle="Pusat pelatihan ulang dan aktivasi versi model"
        >
            <x-slot:meta>
                <div class="flex items-center gap-2 rounded-full border px-4 py-1.5 text-xs font-black uppercase tracking-[0.18em] {{ $statusToneClasses }}">
                    <span class="h-2.5 w-2.5 rounded-full {{ $statusDotClasses }}"></span>
                    Status: {{ $modelStatus['label'] }}
                </div>
            </x-slot:meta>
        </x-admin.topbar>

        <div class="mx-auto max-w-7xl space-y-8 p-6 md:p-10">
            @if ($notice)
                <div class="rounded-2xl border px-5 py-4 {{ ($notice['type'] ?? 'success') === 'error' ? 'border-red-200 bg-error-container text-on-error-container' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">
                    <p class="text-sm font-black uppercase tracking-[0.18em]">{{ $notice['title'] ?? 'Informasi Sistem' }}</p>
                    <p class="mt-1 text-sm font-medium">{{ $notice['message'] ?? '' }}</p>
                </div>
            @endif

            <section class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                <div class="relative min-h-[420px] overflow-hidden rounded-3xl bg-primary px-8 py-10 text-white shadow-2xl lg:col-span-7 lg:px-10">
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_rgba(255,255,255,0.22),_transparent_38%),linear-gradient(145deg,rgba(255,255,255,0.06),transparent_55%)]"></div>
                    <div class="absolute -right-20 top-8 h-56 w-56 rounded-full bg-white/10 blur-3xl"></div>
                    <div class="absolute -bottom-16 left-12 h-40 w-40 rounded-full bg-secondary/20 blur-3xl"></div>
                    <div class="relative z-10">
                        <span class="mb-4 block text-sm font-black uppercase tracking-[0.24em] text-secondary-container">Panel Pelatihan Ulang</span>
                        <h2 class="max-w-2xl text-4xl font-black tracking-tight leading-tight">Optimalkan rekomendasi KIP-K dari keputusan final yang benar-benar sudah disetujui admin.</h2>
                        <p class="mt-5 max-w-2xl text-sm font-medium leading-7 text-blue-100/90">
                            Halaman ini dipakai setelah admin selesai meninjau pengajuan.
                            Sistem akan mengambil data yang sudah benar-benar disetujui, menyiapkan data latih,
                            lalu menjalankan pelatihan ulang agar rekomendasi berikutnya lebih akurat.
                        </p>

                        <div class="mt-10 grid gap-5 border-t border-white/20 pt-8 md:grid-cols-2 xl:grid-cols-3">
                            <div>
                                <h3 class="text-lg font-bold">CatBoost</h3>
                                <p class="mt-2 text-sm leading-6 text-blue-100/85">
                                    Menjadi rekomendasi utama yang dilihat admin ketika sistem menandai pengajuan sebagai Layak atau Indikasi.
                                </p>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold">Naive Bayes</h3>
                                <p class="mt-2 text-sm leading-6 text-blue-100/85">
                                    Menjadi pembanding untuk membantu admin mengenali kasus yang hasil modelnya tidak selaras.
                                </p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/10 p-4 md:col-span-2 xl:col-span-1">
                                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-blue-100">Versi Aktif</p>
                                <p class="mt-2 text-sm font-bold">{{ $activeModel?->version_name ?? 'Belum ada model aktif' }}</p>
                                <p class="mt-1 text-xs text-blue-100/80">{{ optional($activeModel?->activated_at ?? $activeModel?->trained_at)->format('d M Y H:i') ?? 'Menunggu pelatihan pertama' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6 lg:col-span-5">
                    <div class="rounded-3xl border-t-4 border-secondary bg-white p-8 shadow-lg">
                        <div class="mb-6 flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-secondary/15 text-yellow-700">
                                <span class="material-symbols-outlined">settings</span>
                            </div>
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Kontrol Retrain</p>
                                <h3 class="text-xl font-extrabold text-on-surface">Siapkan data, lalu mulai pelatihan</h3>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="rounded-2xl bg-surface-container p-4">
                                <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Sebelum Mulai</p>
                                <p class="mt-2 text-sm font-semibold text-on-surface">Pastikan pengajuan final admin sudah tersalin ke data latih. Jika masih ada selisih, jalankan sinkronisasi terlebih dahulu.</p>
                            </div>

                            <div class="rounded-2xl border border-outline-variant bg-surface-container-low p-4">
                                <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Snapshot Data Aktif</p>
                                <div class="mt-3 flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <span class="material-symbols-outlined rounded-2xl bg-white p-2 text-primary shadow-sm">database</span>
                                        <div>
                                            <p class="text-sm font-bold text-on-surface">Dataset aktif dari sistem</p>
                                            <p class="mt-1 text-[11px] text-slate-500">{{ number_format($payload['training_rows']) }} data siap dilatih</p>
                                        </div>
                                    </div>
                                    <span class="material-symbols-outlined text-slate-400">folder_open</span>
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <form method="POST" action="{{ route('admin.models.retrain.sync-training') }}">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-slate-900 px-4 py-4 text-sm font-black text-white transition hover:bg-slate-800"
                                    >
                                        <span class="material-symbols-outlined text-lg">sync</span>
                                        Sinkronkan Data Latih
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('admin.models.retrain.run') }}">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-primary px-4 py-4 text-sm font-black text-white shadow-lg shadow-primary/20 transition hover:bg-blue-700"
                                    >
                                        <span class="material-symbols-outlined text-lg">rocket_launch</span>
                                        Mulai Latih Ulang
                                    </button>
                                </form>
                            </div>

                            <form method="POST" action="{{ route('admin.models.retrain.run') }}">
                                @csrf
                                <input type="hidden" name="purge_training" value="1" />
                                <button
                                    type="submit"
                                    onclick="return confirm('Apakah Anda yakin ingin menghapus SEMUA data training lama dan melatih ulang dari awal? Tindakan ini tidak bisa dibatalkan.')"
                                    class="flex w-full items-center justify-center gap-2 rounded-2xl border-2 border-error bg-error/10 px-4 py-3.5 text-sm font-black text-error transition hover:bg-error hover:text-white"
                                >
                                    <span class="material-symbols-outlined text-lg">delete_sweep</span>
                                    Hapus Data Lama & Latih Ulang Dari Awal
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="rounded-3xl bg-white p-6 shadow-lg">
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Cek Kesiapan</p>
                        <div class="mt-4 space-y-4">
                            <div class="rounded-2xl bg-surface-container p-4">
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Data Final Admin</p>
                                <p class="mt-2 text-2xl font-black text-on-surface">{{ number_format($payload['finalized_applications']) }}</p>
                            </div>
                            <div class="rounded-2xl bg-surface-container p-4">
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Belum Punya Rekomendasi</p>
                                <p class="mt-2 text-2xl font-black text-on-surface">{{ number_format($payload['applications_without_snapshot']) }}</p>
                            </div>
                            <div class="rounded-2xl bg-surface-container p-4">
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Koreksi Data Latih</p>
                                <p class="mt-2 text-2xl font-black text-on-surface">{{ number_format($payload['training_corrections']) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($cards as $card)
                    @include('pages.admin.dashboard.partials.stat-card', [
                        'label' => $card['label'],
                        'value' => $card['value'],
                        'hint' => $card['hint'],
                        'hintClass' => 'text-slate-500',
                        'border' => $card['border'],
                        'iconWrap' => $card['icon_wrap'],
                        'icon' => $card['icon'],
                    ])
                @endforeach
            </section>

            <section class="overflow-hidden rounded-3xl bg-white shadow-lg">
                <div class="flex items-center justify-between border-b border-slate-100 px-8 py-5">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-on-surface-variant">history</span>
                        <h3 class="font-bold text-lg">Catatan Sistem Terakhir</h3>
                    </div>
                    <span class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Monitor Internal</span>
                </div>
                <div class="divide-y divide-slate-50">
                    @forelse ($systemNotes as $note)
                        @php
                            $tone = $noteToneClasses[$note['tone']] ?? $noteToneClasses['info'];
                        @endphp
                        <div class="flex items-center gap-6 px-8 py-4 transition-colors hover:bg-surface-container-low">
                            <span class="w-24 text-[10px] font-bold text-on-surface-variant">{{ $note['time'] }}</span>
                            <div class="h-2.5 w-2.5 rounded-full {{ $tone['dot'] }}"></div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-on-surface">{{ $note['message'] }}</p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] {{ $tone['pill'] }}">
                                {{ $note['actor'] }}
                            </span>
                        </div>
                    @empty
                        <div class="px-8 py-12 text-center">
                            <p class="text-sm font-semibold text-slate-500">Belum ada catatan sistem. Jalankan sinkronisasi atau pelatihan pertama untuk memulai log.</p>
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <div class="rounded-3xl bg-white p-6 shadow-lg">
                    <div class="mb-4">
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Evaluasi Model Aktif</p>
                        <h3 class="mt-1 text-xl font-extrabold text-on-surface">Metrik retrain terbaru</h3>
                    </div>

                    @if ($activeModelEvaluation)
                        <div class="space-y-4">
                            @if ($activeModel->note)
                                <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5 text-blue-900">
                                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-blue-500">Summary Catatan Model</p>
                                    <p class="mt-2 text-sm font-medium leading-relaxed">{{ $activeModel->note }}</p>
                                </div>
                            @endif
                            @include('pages.admin.models.partials.evaluation-card', ['metrics' => $activeModelEvaluation['catboost']])
                            @include('pages.admin.models.partials.evaluation-card', ['metrics' => $activeModelEvaluation['naive_bayes']])
                        </div>
                    @else
                        <div class="rounded-2xl bg-yellow-50 p-5 text-yellow-800">
                            <p class="text-sm font-semibold">Belum ada metrik evaluasi tersimpan. Jalankan retrain terbaru untuk melihat kualitas model secara lebih lengkap.</p>
                        </div>
                    @endif
                </div>

                <div class="rounded-3xl bg-white p-6 shadow-lg">
                    <div class="mb-4">
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Evaluasi Versi Cadangan</p>
                        <h3 class="mt-1 text-xl font-extrabold text-on-surface">Perbandingan model siap terbaru</h3>
                    </div>

                    @if ($latestReadyModelEvaluation)
                        <div class="space-y-4">
                            @if ($latestReadyModel->note)
                                <div class="rounded-2xl border border-slate-200 bg-surface-container-low p-5 text-slate-700">
                                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Summary Catatan Model</p>
                                    <p class="mt-2 text-sm font-medium leading-relaxed">{{ $latestReadyModel->note }}</p>
                                </div>
                            @endif
                            @include('pages.admin.models.partials.evaluation-card', ['metrics' => $latestReadyModelEvaluation['catboost']])
                            @include('pages.admin.models.partials.evaluation-card', ['metrics' => $latestReadyModelEvaluation['naive_bayes']])
                        </div>
                    @else
                        <div class="rounded-2xl bg-surface-container p-5">
                            <p class="text-sm font-medium text-slate-600">Versi cadangan belum memiliki metrik evaluasi terstruktur.</p>
                        </div>
                    @endif
                </div>
            </section>

            <section class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.15fr)_420px]">
                <div class="overflow-hidden rounded-3xl bg-white shadow-lg">
                    <div class="flex items-center justify-between border-b border-slate-100 px-8 py-5">
                        <div>
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Histori Retrain</p>
                            <h3 class="mt-1 text-xl font-extrabold text-on-surface">Riwayat pelatihan model</h3>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="bg-slate-50/70">
                                    <th class="px-8 py-4 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Versi</th>
                                    <th class="px-8 py-4 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Status</th>
                                    <th class="px-8 py-4 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Hasil Uji</th>
                                    <th class="px-8 py-4 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Waktu</th>
                                    <th class="px-8 py-4 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                @forelse ($recentVersionsView as $versionView)
                                    @php
                                        $modelVersion = $versionView['version'];
                                    @endphp
                                    <tr class="hover:bg-slate-50/60">
                                        <td class="px-8 py-5">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="text-sm font-bold text-on-surface">{{ $modelVersion->version_name }}</p>
                                                @if ($modelVersion->is_current)
                                                    <span class="rounded-full bg-primary-container px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-on-primary-container">Aktif</span>
                                                @endif
                                            </div>
                                            <p class="mt-1 text-[11px] text-slate-400">{{ $modelVersion->rows_used ?? 0 }} data dipakai</p>
                                        </td>
                                        <td class="px-8 py-5">
                                            <span class="rounded-full px-3 py-1 text-[11px] font-black uppercase {{ $modelVersion->status === 'ready' ? 'bg-emerald-50 text-emerald-700' : 'bg-error-container text-on-error-container' }}">
                                                {{ $modelVersion->status === 'ready' ? 'Siap' : 'Gagal' }}
                                            </span>
                                        </td>
                                        <td class="px-8 py-5">
                                            <p class="text-sm font-semibold text-on-surface">CatBoost {{ $modelVersion->catboost_validation_accuracy ?? $modelVersion->catboost_train_accuracy ?? '-' }}</p>
                                            <p class="mt-1 text-[11px] text-slate-400">Naive Bayes {{ $modelVersion->naive_bayes_validation_accuracy ?? $modelVersion->naive_bayes_train_accuracy ?? '-' }}</p>
                                            @if ($versionView['catboost'])
                                                <p class="mt-2 text-[11px] text-slate-400">Precision Indikasi {{ number_format((float) ($versionView['catboost']['precision_indikasi'] ?? 0), 4) }} · Recall {{ number_format((float) ($versionView['catboost']['recall_indikasi'] ?? 0), 4) }}</p>
                                            @endif
                                        </td>
                                        <td class="px-8 py-5">
                                            <p class="text-sm font-semibold text-on-surface">{{ optional($modelVersion->trained_at)->format('d/m/Y H:i') ?? '-' }}</p>
                                            <p class="mt-1 text-[11px] text-slate-400">{{ $modelVersion->triggeredBy?->name ?? $modelVersion->triggered_by_email ?? 'Sistem' }}</p>
                                            <p class="mt-1 text-[11px] text-slate-400">Aktif: {{ optional($modelVersion->activated_at)->format('d/m/Y H:i') ?? '-' }}</p>
                                        </td>
                                        <td class="px-8 py-5 text-right">
                                            @if ($modelVersion->status === 'ready' && ! $modelVersion->is_current)
                                                <form method="POST" action="{{ route('admin.models.retrain.activate', $modelVersion) }}">
                                                    @csrf
                                                    <button
                                                        type="submit"
                                                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-xs font-black uppercase tracking-[0.14em] text-slate-700 transition hover:bg-slate-50"
                                                    >
                                                    <span class="material-symbols-outlined text-sm">restore</span>
                                                    Aktifkan
                                                </button>
                                            </form>
                                            @elseif ($modelVersion->is_current)
                                                <span class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-xs font-black uppercase tracking-[0.14em] text-white">
                                                    <span class="material-symbols-outlined text-sm">verified</span>
                                                    Model Aktif
                                                </span>
                                            @else
                                                <span class="inline-flex items-center rounded-xl bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-500">
                                                    Tidak bisa diaktifkan
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-8 py-14 text-center">
                                            <p class="text-sm font-semibold text-slate-500">Belum ada riwayat pelatihan. Sinkronkan data latih lalu jalankan pelatihan pertama.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-3xl bg-white p-6 shadow-lg">
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Versi Aktif</p>
                        @if ($activeModel)
                            <div class="mt-4 rounded-2xl bg-surface-container p-5">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-black text-on-surface">{{ $activeModel->version_name }}</p>
                                    <span class="rounded-full bg-primary-container px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-on-primary-container">Aktif</span>
                                </div>
                                <p class="mt-2 text-sm font-medium text-slate-500">Versi ini dipakai untuk menghasilkan rekomendasi terbaru pada pengajuan mahasiswa.</p>
                                <div class="mt-4 space-y-2 text-sm">
                                    <p class="font-semibold text-on-surface">CatBoost: {{ $activeModel->catboost_validation_accuracy ?? $activeModel->catboost_train_accuracy ?? '-' }}</p>
                                    <p class="font-semibold text-on-surface">Naive Bayes: {{ $activeModel->naive_bayes_validation_accuracy ?? $activeModel->naive_bayes_train_accuracy ?? '-' }}</p>
                                    @if ($activeModelEvaluation && $activeModelEvaluation['catboost'])
                                        <p class="font-semibold text-on-surface">Recall Indikasi: {{ number_format((float) ($activeModelEvaluation['catboost']['recall_indikasi'] ?? 0), 4) }}</p>
                                    @endif
                                    <p class="font-semibold text-on-surface">Mulai dipakai: {{ optional($activeModel->activated_at ?? $activeModel->trained_at)->format('d M Y H:i') ?? '-' }}</p>
                                </div>
                            </div>
                        @else
                            <div class="mt-4 rounded-2xl bg-yellow-50 p-5 text-yellow-800">
                                <p class="text-sm font-semibold">Belum ada versi siap. Setelah data latih tersedia, jalankan pelatihan pertama dari tombol di atas.</p>
                            </div>
                        @endif
                    </div>

                    <div class="rounded-3xl bg-white p-6 shadow-lg">
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Versi Cadangan Terbaru</p>
                        <div class="mt-4 rounded-2xl bg-surface-container p-5">
                            <p class="text-sm font-black text-on-surface">{{ $latestReadyModel?->version_name ?? 'Belum ada model siap' }}</p>
                            <p class="mt-2 text-sm font-medium text-slate-500">Versi ini bisa diaktifkan jika admin ingin kembali memakai hasil pelatihan yang sebelumnya.</p>
                        </div>
                    </div>

                    <div class="rounded-3xl bg-white p-6 shadow-lg">
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Status Proses Terakhir</p>
                        <div class="mt-4 rounded-2xl {{ $latestAttempt && $latestAttempt->status === 'failed' ? 'bg-error-container text-on-error-container' : 'bg-surface-container text-on-surface' }} p-5">
                            <p class="text-sm font-black">{{ $latestAttempt?->version_name ?? 'Belum ada percobaan retrain' }}</p>
                            <p class="mt-2 text-sm font-medium">
                                {{ $latestAttempt?->error_message ?? $latestAttempt?->note ?? 'Riwayat proses pelatihan akan muncul di sini setelah pelatihan pertama dijalankan.' }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <x-admin.footer />
        </div>
    </div>
</main>
@endsection
