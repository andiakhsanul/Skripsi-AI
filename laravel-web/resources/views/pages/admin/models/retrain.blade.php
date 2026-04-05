@extends('layouts.portal')

@section('title', 'Retrain Model | KIP-K UNAIR')
@section('description', 'Halaman admin untuk sinkronisasi data training dan retrain model machine learning KIP-K UNAIR')

@php
    $adminName = $admin->name ?? 'Admin';
    $adminInitials = collect(preg_split('/\s+/', trim($adminName)) ?: [])
        ->filter()
        ->map(fn ($part) => strtoupper(mb_substr($part, 0, 1)))
        ->take(2)
        ->implode('');

    $notice = session('admin_notice');
    $activeSchema = $payload['active_schema'];
    $modelStatus = $payload['model_status'];
    $activeModel = $payload['active_model'];
    $latestReadyModel = $payload['latest_ready_model'];
    $latestAttempt = $payload['latest_attempt'];
    $recentVersions = $payload['recent_model_versions'];

    $cards = [
        [
            'label' => 'Data Final Admin',
            'value' => number_format($payload['finalized_applications']),
            'hint' => 'Pengajuan yang sudah berstatus Verified atau Rejected.',
            'border' => 'border-primary',
            'icon_wrap' => 'bg-primary/10 text-primary',
            'icon' => 'fact_check',
        ],
        [
            'label' => 'Data Training Aktif',
            'value' => number_format($payload['training_rows']),
            'hint' => 'Baris canonical di spk_training_data yang siap dipakai retrain.',
            'border' => 'border-emerald-500',
            'icon_wrap' => 'bg-emerald-50 text-emerald-600',
            'icon' => 'database',
        ],
        [
            'label' => 'Gap Sinkronisasi',
            'value' => number_format($payload['training_gap']),
            'hint' => 'Data final yang belum disalin ke tabel training.',
            'border' => 'border-yellow-500',
            'icon_wrap' => 'bg-yellow-50 text-yellow-700',
            'icon' => 'sync_problem',
        ],
        [
            'label' => 'Snapshot Prediksi',
            'value' => number_format($payload['prediction_snapshots']),
            'hint' => 'Jumlah pengajuan yang sudah punya hasil CatBoost dan Naive Bayes.',
            'border' => 'border-slate-800',
            'icon_wrap' => 'bg-slate-100 text-slate-700',
            'icon' => 'analytics',
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
            <a
                href="{{ route('admin.dashboard') }}"
                class="flex items-center gap-3 rounded-md px-4 py-3 font-semibold text-slate-500 transition-transform duration-200 hover:-translate-y-0.5 hover:bg-white"
            >
                <span class="material-symbols-outlined">dashboard_customize</span>
                <span class="text-sm">Dasbor Admin</span>
            </a>

            <a
                href="{{ route('admin.models.retrain') }}"
                class="flex items-center gap-3 rounded-md border-t-2 border-yellow-500 bg-white px-4 py-3 font-semibold text-blue-700 shadow-sm transition-transform duration-200 hover:-translate-y-0.5"
            >
                <span class="material-symbols-outlined">model_training</span>
                <span class="text-sm">Retrain Model</span>
            </a>
        </nav>

        <div class="border-t border-slate-100 bg-slate-100/50 p-4">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button
                    type="submit"
                    class="flex w-full items-center gap-3 px-4 py-3 text-left font-semibold text-slate-500 transition-colors hover:text-error"
                >
                    <span class="material-symbols-outlined">logout</span>
                    <span class="text-sm">Keluar</span>
                </button>
            </form>
        </div>
    </aside>

    <div class="md:ml-64">
        <header class="sticky top-0 z-30 flex h-20 items-center justify-between border-b border-slate-100 bg-white/80 px-6 backdrop-blur-md md:px-10">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-on-surface">Retrain Model</h1>
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">CatBoost dan Naive Bayes dikendalikan oleh Flask ML API</p>
            </div>

            <div class="flex items-center gap-4">
                <div class="rounded-full border px-4 py-1.5 text-xs font-black uppercase tracking-[0.18em] {{ $modelStatus['ready'] ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-yellow-200 bg-yellow-50 text-yellow-700' }}">
                    Status Model: {{ $modelStatus['label'] }}
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 border-primary/10 bg-primary-container font-bold text-primary shadow-sm">
                    {{ $adminInitials !== '' ? $adminInitials : 'AD' }}
                </div>
            </div>
        </header>

        <div class="mx-auto max-w-7xl space-y-8 p-6 md:p-10">
            @if ($notice)
                <div class="rounded-2xl border px-5 py-4 {{ ($notice['type'] ?? 'success') === 'error' ? 'border-red-200 bg-error-container text-on-error-container' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">
                    <p class="text-sm font-black uppercase tracking-[0.18em]">{{ $notice['title'] ?? 'Informasi Sistem' }}</p>
                    <p class="mt-1 text-sm font-medium">{{ $notice['message'] ?? '' }}</p>
                </div>
            @endif

            <section class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                <div class="relative overflow-hidden rounded-3xl bg-primary px-8 py-10 text-white shadow-2xl lg:col-span-7">
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_rgba(255,255,255,0.22),_transparent_42%),linear-gradient(135deg,rgba(255,255,255,0.08),transparent)]"></div>
                    <div class="relative z-10">
                        <span class="mb-4 block text-sm font-black uppercase tracking-[0.24em] text-secondary-container">Mesin Seleksi Akademik</span>
                        <h2 class="max-w-2xl text-4xl font-black tracking-tight">Laravel mengelola kontrol admin, Flask menjalankan retrain model.</h2>
                        <p class="mt-5 max-w-2xl text-sm font-medium leading-7 text-blue-100/90">
                            Data offline yang sudah final dari admin dipindahkan dulu ke <span class="font-bold text-white">spk_training_data</span>,
                            lalu Laravel hanya mengirim trigger internal ke Flask. Service Flask yang melatih CatBoost dan Naive Bayes,
                            menyimpan file model, dan mencatat histori versi model.
                        </p>

                        <div class="mt-8 grid gap-4 md:grid-cols-3">
                            <div class="rounded-2xl border border-white/10 bg-white/10 p-4">
                                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-blue-100">Skema Aktif</p>
                                <p class="mt-2 text-2xl font-black">{{ $activeSchema?->version ? 'v'.$activeSchema->version : 'v1 default' }}</p>
                                <p class="mt-1 text-xs text-blue-100/80">{{ $activeSchema?->source_file_name ?? 'Belum ada file schema khusus' }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/10 p-4">
                                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-blue-100">Distribusi Label</p>
                                <p class="mt-2 text-sm font-semibold">Layak: {{ number_format($payload['label_distribution']['layak']) }}</p>
                                <p class="mt-1 text-sm font-semibold">Indikasi: {{ number_format($payload['label_distribution']['indikasi']) }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/10 p-4">
                                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-blue-100">Versi Siap Terakhir</p>
                                <p class="mt-2 text-sm font-bold">{{ $activeModel?->version_name ?? 'Belum ada model aktif' }}</p>
                                <p class="mt-1 text-xs text-blue-100/80">{{ optional($activeModel?->activated_at ?? $activeModel?->trained_at)->format('d M Y H:i') ?? 'Menunggu retrain pertama' }}</p>
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
                                <h3 class="text-xl font-extrabold text-on-surface">Siapkan dataset, lalu latih model</h3>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="rounded-2xl bg-surface-container p-4">
                                <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Sebelum Retrain</p>
                                <p class="mt-2 text-sm font-semibold text-on-surface">Pastikan data final admin sudah tersalin ke tabel training. Jika masih ada gap, jalankan sinkronisasi dahulu.</p>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <form method="POST" action="{{ route('admin.models.retrain.sync-training') }}">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-slate-900 px-4 py-4 text-sm font-black text-white transition hover:bg-slate-800"
                                    >
                                        <span class="material-symbols-outlined text-lg">sync</span>
                                        Sinkronkan Data Training
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('admin.models.retrain.run') }}">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-primary px-4 py-4 text-sm font-black text-white shadow-lg shadow-primary/20 transition hover:bg-blue-700"
                                    >
                                        <span class="material-symbols-outlined text-lg">rocket_launch</span>
                                        Mulai Retrain via Flask
                                    </button>
                                </form>
                            </div>
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
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Belum Punya Snapshot Prediksi</p>
                                <p class="mt-2 text-2xl font-black text-on-surface">{{ number_format($payload['applications_without_snapshot']) }}</p>
                            </div>
                            <div class="rounded-2xl bg-surface-container p-4">
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Koreksi Training</p>
                                <p class="mt-2 text-2xl font-black text-on-surface">{{ number_format($payload['training_corrections']) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($cards as $card)
                    <div class="rounded-2xl border-t-4 {{ $card['border'] }} bg-white p-6 shadow-lg">
                        <div class="mb-4 flex items-center justify-between">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl {{ $card['icon_wrap'] }}">
                                <span class="material-symbols-outlined">{{ $card['icon'] }}</span>
                            </div>
                            <span class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">{{ $card['label'] }}</span>
                        </div>
                        <p class="text-3xl font-black text-on-surface">{{ $card['value'] }}</p>
                        <p class="mt-2 text-sm font-medium text-slate-500">{{ $card['hint'] }}</p>
                    </div>
                @endforeach
            </section>

            <section class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.15fr)_420px]">
                <div class="overflow-hidden rounded-3xl bg-white shadow-lg">
                    <div class="flex items-center justify-between border-b border-slate-100 px-8 py-5">
                        <div>
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Histori Retrain</p>
                            <h3 class="mt-1 text-xl font-extrabold text-on-surface">Histori versi model</h3>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="bg-slate-50/70">
                                    <th class="px-8 py-4 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Versi</th>
                                    <th class="px-8 py-4 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Status</th>
                                    <th class="px-8 py-4 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Akurasi</th>
                                    <th class="px-8 py-4 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Waktu</th>
                                    <th class="px-8 py-4 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                @forelse ($recentVersions as $modelVersion)
                                    <tr class="hover:bg-slate-50/60">
                                        <td class="px-8 py-5">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="text-sm font-bold text-on-surface">{{ $modelVersion->version_name }}</p>
                                                @if ($modelVersion->is_current)
                                                    <span class="rounded-full bg-primary-container px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-on-primary-container">Aktif</span>
                                                @endif
                                            </div>
                                            <p class="mt-1 text-[11px] text-slate-400">Skema v{{ $modelVersion->schema_version }} · {{ $modelVersion->rows_used ?? 0 }} baris</p>
                                        </td>
                                        <td class="px-8 py-5">
                                            <span class="rounded-full px-3 py-1 text-[11px] font-black uppercase {{ $modelVersion->status === 'ready' ? 'bg-emerald-50 text-emerald-700' : 'bg-error-container text-on-error-container' }}">
                                                {{ $modelVersion->status === 'ready' ? 'Siap' : 'Gagal' }}
                                            </span>
                                        </td>
                                        <td class="px-8 py-5">
                                            <p class="text-sm font-semibold text-on-surface">CatBoost {{ $modelVersion->catboost_validation_accuracy ?? $modelVersion->catboost_train_accuracy ?? '-' }}</p>
                                            <p class="mt-1 text-[11px] text-slate-400">Naive Bayes {{ $modelVersion->naive_bayes_validation_accuracy ?? $modelVersion->naive_bayes_train_accuracy ?? '-' }}</p>
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
                                                        Jadikan Aktif
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
                                            <p class="text-sm font-semibold text-slate-500">Belum ada histori retrain. Jalankan sinkronisasi training lalu retrain pertama.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-3xl bg-white p-6 shadow-lg">
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Model Aktif</p>
                        @if ($activeModel)
                            <div class="mt-4 rounded-2xl bg-surface-container p-5">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-black text-on-surface">{{ $activeModel->version_name }}</p>
                                    <span class="rounded-full bg-primary-container px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-on-primary-container">Aktif</span>
                                </div>
                                <p class="mt-2 text-sm font-medium text-slate-500">CatBoost adalah model primer. Naive Bayes digunakan sebagai pembanding disagreement.</p>
                                <div class="mt-4 space-y-2 text-sm">
                                    <p class="font-semibold text-on-surface">CatBoost: {{ $activeModel->catboost_validation_accuracy ?? $activeModel->catboost_train_accuracy ?? '-' }}</p>
                                    <p class="font-semibold text-on-surface">Naive Bayes: {{ $activeModel->naive_bayes_validation_accuracy ?? $activeModel->naive_bayes_train_accuracy ?? '-' }}</p>
                                    <p class="font-semibold text-on-surface">Diaktifkan: {{ optional($activeModel->activated_at ?? $activeModel->trained_at)->format('d M Y H:i') ?? '-' }}</p>
                                </div>
                            </div>
                        @else
                            <div class="mt-4 rounded-2xl bg-yellow-50 p-5 text-yellow-800">
                                <p class="text-sm font-semibold">Belum ada model siap. Setelah data training aktif tersedia, jalankan retrain pertama melalui tombol di atas.</p>
                            </div>
                        @endif
                    </div>

                    <div class="rounded-3xl bg-white p-6 shadow-lg">
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Versi Siap Terbaru</p>
                        <div class="mt-4 rounded-2xl bg-surface-container p-5">
                            <p class="text-sm font-black text-on-surface">{{ $latestReadyModel?->version_name ?? 'Belum ada model siap' }}</p>
                            <p class="mt-2 text-sm font-medium text-slate-500">Versi siap terbaru belum tentu menjadi model aktif jika admin melakukan rollback ke versi sebelumnya.</p>
                        </div>
                    </div>

                    <div class="rounded-3xl bg-white p-6 shadow-lg">
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Percobaan Terakhir</p>
                        <div class="mt-4 rounded-2xl {{ $latestAttempt && $latestAttempt->status === 'failed' ? 'bg-error-container text-on-error-container' : 'bg-surface-container text-on-surface' }} p-5">
                            <p class="text-sm font-black">{{ $latestAttempt?->version_name ?? 'Belum ada percobaan retrain' }}</p>
                            <p class="mt-2 text-sm font-medium">
                                {{ $latestAttempt?->error_message ?? $latestAttempt?->note ?? 'Riwayat retrain akan muncul di sini setelah proses pertama dijalankan.' }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>
@endsection
