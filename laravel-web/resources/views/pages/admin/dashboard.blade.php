@extends('layouts.portal')

@section('title', 'Dasbor Admin | KIP-K UNAIR')
@section('description', 'Dashboard admin untuk monitoring pengajuan KIP-K mahasiswa UNAIR')

@php
    $applicationStats = $summary['applications'];
    $trainingStats = $summary['training_data'];
    $statusOptions = $page['filters']['status_options'];
    $priorityOptions = $page['filters']['priority_options'];
    $recommendationOptions = $page['filters']['recommendation_options'];
    $disagreementOptions = $page['filters']['disagreement_options'];
    $statusDisplayLabels = $page['status_display_labels'];
    $statusBadgeClasses = $page['status_badge_classes'];
    $priorityMeta = $page['priority_meta'];
    $statCards = $page['stat_cards'];
    $workflowCards = $page['workflow_cards'];
    $operationCards = $page['operation_cards'];
    $pageStart = max(1, $applications->currentPage() - 1);
    $pageEnd = min($applications->lastPage(), $applications->currentPage() + 1);
    $notice = session('admin_notice');
@endphp

@section('content')
@include('pages.admin.partials.sidebar', ['active' => 'dashboard'])

<main class="min-h-screen bg-background md:ml-64">
    <x-admin.topbar
        :admin="$admin"
        title="Administrator"
        subtitle="Divisi Kemahasiswaan"
        title-class="text-xl font-extrabold tracking-tighter text-blue-800"
        subtitle-class="text-[10px] font-medium uppercase tracking-[0.2em] text-slate-400"
        height-class="h-16"
    >
        <x-slot:actions>
            <a
                href="{{ route('admin.models.retrain') }}"
                class="rounded-xl bg-primary px-4 py-2 text-sm font-bold text-white shadow-lg shadow-primary/20 transition hover:bg-blue-700"
            >
                Latih Ulang Model
            </a>
        </x-slot:actions>
    </x-admin.topbar>

    <div class="mx-auto w-full max-w-screen-2xl p-6 md:p-8">
        @if ($notice)
            <div class="mb-6 rounded-2xl border px-5 py-4 {{ ($notice['type'] ?? 'success') === 'error' ? 'border-red-200 bg-error-container text-on-error-container' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">
                <p class="text-sm font-black uppercase tracking-[0.18em]">{{ $notice['title'] ?? 'Informasi Sistem' }}</p>
                <p class="mt-1 text-sm font-medium">{{ $notice['message'] ?? '' }}</p>
            </div>
        @endif

        @if ($defaultFocusApplied)
            <div class="mb-6 rounded-2xl border border-red-200 bg-error-container px-5 py-4 text-on-error-container">
                <p class="text-sm font-black uppercase tracking-[0.18em]">Fokus Review Awal</p>
                <p class="mt-1 text-sm font-medium">Dasbor dibuka dengan fokus pada pengajuan yang masih menunggu dan direkomendasikan <strong>Indikasi</strong>. Gunakan tombol <strong>Semua Pengajuan</strong> bila ingin melihat seluruh antrean.</p>
            </div>
        @endif

        <section class="mb-8 grid grid-cols-1 gap-6 xl:grid-cols-12">
            <div class="relative min-h-[360px] overflow-hidden rounded-3xl bg-primary px-8 py-10 text-white shadow-2xl xl:col-span-7 xl:px-10">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_rgba(255,255,255,0.22),_transparent_38%),linear-gradient(145deg,rgba(255,255,255,0.08),transparent_55%)]"></div>
                <div class="absolute -right-20 top-8 h-56 w-56 rounded-full bg-white/10 blur-3xl"></div>
                <div class="absolute -bottom-16 left-12 h-40 w-40 rounded-full bg-secondary/20 blur-3xl"></div>

                <div class="relative z-10">
                    <span class="mb-4 block text-sm font-black uppercase tracking-[0.24em] text-secondary-container">Ruang Kerja Verifikator</span>
                    <h1 class="max-w-3xl text-4xl font-black leading-tight tracking-tight">Dasbor Beasiswa untuk meninjau data mentah, membaca rekomendasi AI, dan menetapkan keputusan final secara terarah.</h1>
                    <p class="mt-5 max-w-2xl text-sm font-medium leading-7 text-blue-100/90">
                        Halaman ini menjadi pusat kerja admin untuk memilah antrean pengajuan, memprioritaskan kasus indikasi,
                        dan menjaga agar hanya keputusan final yang benar-benar disetujui masuk ke data latih.
                    </p>

                    <div class="mt-6 flex flex-wrap gap-3 text-xs font-semibold">
                        <span class="rounded-full bg-white/10 px-4 py-2 text-white ring-1 ring-white/15">
                            Model indikasi:
                            <span class="font-black text-secondary-container">{{ $applicationStats['indikasi_recommendations'] }}</span>
                        </span>
                        <span class="rounded-full bg-white/10 px-4 py-2 text-white ring-1 ring-white/15">
                            Indikasi Menunggu:
                            <span class="font-black text-secondary-container">{{ $applicationStats['indikasi_pending'] }}</span>
                        </span>
                        <span class="rounded-full bg-white/10 px-4 py-2 text-white ring-1 ring-white/15">
                            Disagreement:
                            <span class="font-black text-secondary-container">{{ $applicationStats['disagreement_cases'] }}</span>
                        </span>
                        <span class="rounded-full bg-white/10 px-4 py-2 text-white ring-1 ring-white/15">
                            Data Siap Dilatih:
                            <span class="font-black text-secondary-container">{{ number_format($trainingStats['total_active']) }}</span>
                        </span>
                    </div>

                    <div class="mt-8 grid gap-4 md:grid-cols-3">
                        @foreach ($workflowCards as $card)
                            <article class="rounded-2xl border border-white/12 bg-white/10 p-4 backdrop-blur-sm">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl {{ $card['tone'] }}">
                                        <span class="material-symbols-outlined">{{ $card['icon'] }}</span>
                                    </div>
                                    <p class="text-sm font-black tracking-tight">{{ $card['title'] }}</p>
                                </div>
                                <p class="mt-3 text-sm font-medium leading-6 text-blue-100/90">{{ $card['description'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="space-y-6 xl:col-span-5">
                <div class="rounded-3xl border-t-4 border-secondary bg-white p-8 shadow-lg">
                    <div class="mb-6 flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-secondary/15 text-yellow-700">
                            <span class="material-symbols-outlined">hub</span>
                        </div>
                        <div>
                            <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Pusat Aksi Admin</p>
                            <h2 class="text-xl font-extrabold text-on-surface">Pilih antrean prioritas dan jalankan tugas inti</h2>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="rounded-2xl bg-surface-container p-4">
                            <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Arahan Hari Ini</p>
                            <p class="mt-2 text-sm font-semibold text-on-surface">Mulai dari pengajuan yang berstatus menunggu dan direkomendasikan <span class="text-error">Indikasi</span>, lalu lanjutkan ke kasus disagreement dan prioritas tinggi.</p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <form method="POST" action="{{ route('admin.applications.run-predictions') }}">
                                @csrf
                                <input type="hidden" name="only_missing" value="1" />
                                <button
                                    type="submit"
                                    class="flex w-full items-center justify-center gap-2 rounded-2xl bg-slate-900 px-4 py-4 text-sm font-black text-white transition hover:bg-slate-800"
                                >
                                    <span class="material-symbols-outlined text-lg">auto_awesome</span>
                                    Buat Snapshot Model
                                </button>
                            </form>

                            <a
                                href="{{ route('admin.dashboard', ['status' => 'Submitted', 'recommendation' => 'Indikasi']) }}"
                                class="flex items-center justify-center gap-2 rounded-2xl bg-error px-4 py-4 text-sm font-black text-white shadow-lg shadow-error/20 transition hover:bg-red-600"
                            >
                                <span class="material-symbols-outlined text-lg">rule</span>
                                Fokus Indikasi Menunggu
                            </a>

                            <a
                                href="{{ route('admin.dashboard', $filters) }}"
                                class="flex items-center justify-center gap-2 rounded-2xl border border-outline-variant bg-surface px-4 py-4 text-sm font-bold text-on-surface transition hover:bg-surface-container"
                            >
                                <span class="material-symbols-outlined text-lg">refresh</span>
                                Muat Ulang
                            </a>

                            <a
                                href="{{ route('admin.models.retrain') }}"
                                class="flex items-center justify-center gap-2 rounded-2xl bg-secondary px-4 py-4 text-sm font-black text-on-secondary shadow-lg shadow-secondary/20 transition hover:scale-[1.01]"
                            >
                                <span class="material-symbols-outlined text-lg">model_training</span>
                                Latih Ulang Model
                            </a>

                            <a
                                href="{{ route('admin.applications.house-review') }}"
                                class="flex items-center justify-center gap-2 rounded-2xl border border-outline-variant bg-surface px-4 py-4 text-sm font-bold text-on-surface transition hover:bg-surface-container"
                            >
                                <span class="material-symbols-outlined text-lg">home_work</span>
                                Perbaikan Rumah
                            </a>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl bg-white p-6 shadow-lg">
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Ringkasan Operasional</p>
                    <div class="mt-4 space-y-4">
                        <div class="rounded-2xl bg-surface-container p-4">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Antrean Prioritas Tinggi</p>
                            <p class="mt-2 text-2xl font-black text-error">{{ $applicationStats['high_priority_pending'] }}</p>
                            <p class="mt-1 text-xs font-medium text-slate-500">Perlu tindakan cepat karena rekomendasi dan tingkat risikonya butuh review lebih dekat.</p>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($operationCards as $card)
                                <div class="rounded-2xl bg-surface-container p-4">
                                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">{{ $card['label'] }}</p>
                                    <p class="mt-2 text-xl font-black text-on-surface">{{ $card['value'] }}</p>
                                    <p class="mt-1 text-xs font-medium text-slate-500">{{ $card['detail'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mb-10 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            @foreach ($statCards as $card)
                @include('pages.admin.dashboard.partials.stat-card', [
                    'label' => $card['label'],
                    'value' => $card['value'],
                    'hint' => $card['hint'],
                    'hintClass' => $card['hint_class'],
                    'border' => $card['border'],
                    'iconWrap' => $card['icon_wrap'],
                    'icon' => $card['icon'],
                ])
            @endforeach
        </section>

        <section class="overflow-hidden rounded-3xl bg-white shadow-lg">
            <div class="flex flex-col gap-4 border-b border-slate-100 px-6 py-6 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Antrean Verifikasi</p>
                    <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-on-surface">Pusat tinjauan pengajuan mahasiswa</h2>
                    <p class="mt-2 max-w-2xl text-sm font-medium text-on-surface-variant">Gunakan filter untuk memusatkan perhatian pada kasus indikasi, disagreement model, atau antrean menunggu yang belum diputuskan admin.</p>
                </div>

                <div class="rounded-2xl border border-outline-variant bg-surface-container-low px-4 py-3 text-right">
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Baris Saat Ini</p>
                    <p class="mt-1 text-lg font-black text-on-surface">{{ number_format($applications->count()) }}</p>
                </div>
            </div>

            <div class="border-b border-slate-100 px-6 py-5">
                <form method="GET" action="{{ route('admin.dashboard') }}" class="flex w-full flex-col gap-3 lg:flex-row lg:items-center">
                    <div class="relative w-full lg:max-w-md">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">search</span>
                        <input
                            type="text"
                            name="q"
                            value="{{ $filters['q'] }}"
                            placeholder="Cari nama, email, atau nomor referensi..."
                            class="w-full rounded-xl border-none bg-surface-container py-3 pl-12 pr-4 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-primary/20"
                        />
                    </div>

                    <div class="flex flex-1 flex-col gap-3 sm:flex-row lg:flex-none">
                        <select
                            name="status"
                            class="cursor-pointer rounded-xl border-none bg-surface-container px-4 py-3 text-sm font-semibold focus:ring-primary/20"
                        >
                            @foreach ($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>

                        <select
                            name="priority"
                            class="cursor-pointer rounded-xl border-none bg-surface-container px-4 py-3 text-sm font-semibold focus:ring-primary/20"
                        >
                            @foreach ($priorityOptions as $value => $label)
                                <option value="{{ $value }}" @selected($filters['priority'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>

                        <select
                            name="recommendation"
                            class="cursor-pointer rounded-xl border-none bg-surface-container px-4 py-3 text-sm font-semibold focus:ring-primary/20"
                        >
                            @foreach ($recommendationOptions as $value => $label)
                                <option value="{{ $value }}" @selected($filters['recommendation'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>

                        <select
                            name="disagreement"
                            class="cursor-pointer rounded-xl border-none bg-surface-container px-4 py-3 text-sm font-semibold focus:ring-primary/20"
                        >
                            @foreach ($disagreementOptions as $value => $label)
                                <option value="{{ $value }}" @selected($filters['disagreement'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>

                        <button
                            type="submit"
                            class="rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white transition-colors hover:bg-primary/90"
                        >
                            Terapkan
                        </button>

                        <a
                            href="{{ route('admin.dashboard', ['scope' => 'all']) }}"
                            class="rounded-xl bg-surface-container px-4 py-3 text-center text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-200"
                        >
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <div class="flex flex-wrap gap-3 border-b border-slate-100 px-6 py-4">
                @include('pages.admin.dashboard.partials.filter-chip', [
                    'href' => route('admin.dashboard', ['status' => 'Submitted', 'recommendation' => 'Indikasi']),
                    'label' => 'Indikasi Menunggu',
                    'active' => $filters['status'] === 'Submitted' && $filters['recommendation'] === 'Indikasi',
                    'activeClass' => 'bg-error text-white',
                    'inactiveClass' => 'bg-error-container text-on-error-container hover:bg-red-100',
                ])

                @include('pages.admin.dashboard.partials.filter-chip', [
                    'href' => route('admin.dashboard', ['scope' => 'all']),
                    'label' => 'Semua Pengajuan',
                    'active' => $filters['scope'] === 'all' && $filters['status'] === '' && $filters['priority'] === '' && $filters['recommendation'] === '' && $filters['disagreement'] === '',
                    'activeClass' => 'bg-slate-900 text-white',
                    'inactiveClass' => 'bg-slate-100 text-slate-700 hover:bg-slate-200',
                ])

                @include('pages.admin.dashboard.partials.filter-chip', [
                    'href' => route('admin.dashboard', ['disagreement' => 'true']),
                    'label' => 'Hanya Disagreement',
                    'active' => $filters['disagreement'] === 'true',
                    'activeClass' => 'bg-yellow-600 text-white',
                    'inactiveClass' => 'bg-secondary-container text-on-secondary-container hover:bg-yellow-100',
                ])

                @include('pages.admin.dashboard.partials.filter-chip', [
                    'href' => route('admin.dashboard', ['priority' => 'high']),
                    'label' => 'Prioritas Tinggi',
                    'active' => $filters['priority'] === 'high',
                    'activeClass' => 'bg-primary text-white',
                    'inactiveClass' => 'bg-primary-container text-on-primary-container hover:bg-blue-100',
                ])

                @include('pages.admin.dashboard.partials.filter-chip', [
                    'href' => route('admin.dashboard', ['status' => 'Submitted']),
                    'label' => 'Semua Menunggu',
                    'active' => $filters['status'] === 'Submitted' && $filters['priority'] === '' && $filters['recommendation'] === '' && $filters['disagreement'] === '',
                    'activeClass' => 'bg-slate-900 text-white',
                    'inactiveClass' => 'bg-slate-100 text-slate-700 hover:bg-slate-200',
                ])
            </div>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr class="bg-slate-50/50">
                            <th class="border-b border-slate-100 px-6 py-4 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Pemohon</th>
                            <th class="border-b border-slate-100 px-6 py-4 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Program Studi</th>
                            <th class="border-b border-slate-100 px-6 py-4 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Status</th>
                            <th class="border-b border-slate-100 px-6 py-4 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Prioritas</th>
                            <th class="border-b border-slate-100 px-6 py-4 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Rekomendasi</th>
                            <th class="border-b border-slate-100 px-6 py-4 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @forelse ($applications as $application)
                            @include('pages.admin.dashboard.partials.application-row', [
                                'application' => $application,
                                'statusBadgeClasses' => $statusBadgeClasses,
                                'statusDisplayLabels' => $statusDisplayLabels,
                                'priorityMeta' => $priorityMeta,
                            ])
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-16 text-center">
                                    <div class="mx-auto max-w-md">
                                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-surface-container text-slate-500">
                                            <span class="material-symbols-outlined">inbox</span>
                                        </div>
                                        <h3 class="mt-4 text-lg font-extrabold text-on-surface">Belum ada pengajuan yang cocok dengan filter ini</h3>
                                        <p class="mt-2 text-sm font-medium text-on-surface-variant">
                                            Coba ubah pencarian atau reset filter untuk melihat seluruh antrean pengajuan mahasiswa.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-4 border-t border-slate-100 p-6 md:flex-row md:items-center md:justify-between">
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">
                    Menampilkan {{ $applications->firstItem() ?? 0 }} sampai {{ $applications->lastItem() ?? 0 }} dari {{ $applications->total() }} data
                </p>

                <div class="flex items-center gap-1">
                    <a
                        href="{{ $applications->previousPageUrl() ?? '#' }}"
                        class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-100 {{ $applications->onFirstPage() ? 'pointer-events-none text-slate-300' : 'text-slate-400 hover:bg-slate-50' }}"
                    >
                        <span class="material-symbols-outlined text-lg">chevron_left</span>
                    </a>

                    @for ($page = $pageStart; $page <= $pageEnd; $page++)
                        <a
                            href="{{ $applications->url($page) }}"
                            class="flex h-9 w-9 items-center justify-center rounded-lg text-xs font-bold {{ $page === $applications->currentPage() ? 'bg-primary text-white' : 'border border-slate-100 text-slate-600 hover:bg-slate-50' }}"
                        >
                            {{ $page }}
                        </a>
                    @endfor

                    <a
                        href="{{ $applications->nextPageUrl() ?? '#' }}"
                        class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-100 {{ $applications->hasMorePages() ? 'text-slate-400 hover:bg-slate-50' : 'pointer-events-none text-slate-300' }}"
                    >
                        <span class="material-symbols-outlined text-lg">chevron_right</span>
                    </a>
                </div>
            </div>
        </section>
    </div>

    <x-admin.footer />
</main>
@endsection
