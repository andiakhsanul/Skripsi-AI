@extends('layouts.portal')

@section('title', 'Semua Aplikan | KIP-K UNAIR')
@section('description', 'Daftar lengkap semua pengajuan KIP-K mahasiswa UNAIR (history)')

@php
    $applicationStats = $summary['applications'];
    $statusOptions = $page['filters']['status_options'];
    $priorityOptions = $page['filters']['priority_options'];
    $recommendationOptions = $page['filters']['recommendation_options'];
    $disagreementOptions = $page['filters']['disagreement_options'];
    $decisionOptions = $page['filters']['decision_options'];
    $sourceOptions = $page['filters']['source_options'];
    $statusDisplayLabels = $page['status_display_labels'];
    $statusBadgeClasses = $page['status_badge_classes'];
    $priorityMeta = $page['priority_meta'];
    $statCards = $page['all_applications_stat_cards'];
    $pageStart = max(1, $applications->currentPage() - 1);
    $pageEnd = min($applications->lastPage(), $applications->currentPage() + 1);
    $notice = session('admin_notice');
@endphp

@section('content')
@include('pages.admin.partials.sidebar', ['active' => 'applications'])

<main class="min-h-screen bg-background md:ml-64">
    <x-admin.topbar
        :admin="$admin"
        title="Semua Aplikan"
        subtitle="History pengajuan lengkap"
        title-class="text-xl font-extrabold tracking-tighter text-blue-800"
        subtitle-class="text-[10px] font-medium uppercase tracking-[0.2em] text-slate-400"
        height-class="h-16"
    >
        <x-slot:actions>
            <a
                href="{{ route('admin.dashboard') }}"
                class="rounded-xl bg-primary px-4 py-2 text-sm font-bold text-white shadow-lg shadow-primary/20 transition hover:bg-blue-700"
            >
                Antrean Keputusan
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

        <section class="mb-8 rounded-3xl bg-gradient-to-br from-slate-900 to-slate-700 px-8 py-8 text-white shadow-2xl">
            <span class="mb-2 block text-xs font-black uppercase tracking-[0.24em] text-secondary-container">Semua Aplikan</span>
            <h1 class="text-3xl font-black leading-tight tracking-tight">Daftar lengkap pengajuan KIP-K</h1>
            <p class="mt-3 max-w-3xl text-sm font-medium leading-7 text-slate-200">
                Halaman ini menampilkan semua pengajuan (Submitted, Verified, Rejected) sebagai history lengkap.
                Untuk fokus pada pekerjaan harian (pengajuan menunggu keputusan), kembali ke <a href="{{ route('admin.dashboard') }}" class="font-bold underline">Antrean Keputusan</a>.
            </p>
        </section>

        <section class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
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
                    <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">History Pengajuan</p>
                    <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-on-surface">Semua aplikan dari berbagai status</h2>
                    <p class="mt-2 max-w-2xl text-sm font-medium text-on-surface-variant">Gunakan filter untuk menelusuri data sebelumnya — verified, rejected, atau yang belum diputuskan.</p>
                </div>

                <div class="rounded-2xl border border-outline-variant bg-surface-container-low px-4 py-3 text-right">
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Hasil</p>
                    <p class="mt-1 text-lg font-black text-on-surface">{{ number_format($applications->total()) }}</p>
                </div>
            </div>

            <div class="border-b border-slate-100 px-6 py-5">
                <form method="GET" action="{{ route('admin.applications.index') }}" class="flex w-full flex-col gap-3">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
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

                        <button
                            type="submit"
                            class="rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white transition-colors hover:bg-primary/90"
                        >
                            Terapkan
                        </button>

                        <a
                            href="{{ route('admin.applications.index') }}"
                            class="rounded-xl bg-surface-container px-4 py-3 text-center text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-200"
                        >
                            Reset
                        </a>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
                        <select name="status" class="cursor-pointer rounded-xl border-none bg-surface-container px-4 py-3 text-sm font-semibold focus:ring-primary/20">
                            @foreach ($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>

                        <select name="decision" class="cursor-pointer rounded-xl border-none bg-surface-container px-4 py-3 text-sm font-semibold focus:ring-primary/20">
                            @foreach ($decisionOptions as $value => $label)
                                <option value="{{ $value }}" @selected($filters['decision'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>

                        <select name="source" class="cursor-pointer rounded-xl border-none bg-surface-container px-4 py-3 text-sm font-semibold focus:ring-primary/20">
                            @foreach ($sourceOptions as $value => $label)
                                <option value="{{ $value }}" @selected($filters['source'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>

                        <select name="priority" class="cursor-pointer rounded-xl border-none bg-surface-container px-4 py-3 text-sm font-semibold focus:ring-primary/20">
                            @foreach ($priorityOptions as $value => $label)
                                <option value="{{ $value }}" @selected($filters['priority'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>

                        <select name="recommendation" class="cursor-pointer rounded-xl border-none bg-surface-container px-4 py-3 text-sm font-semibold focus:ring-primary/20">
                            @foreach ($recommendationOptions as $value => $label)
                                <option value="{{ $value }}" @selected($filters['recommendation'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>

                        <select name="disagreement" class="cursor-pointer rounded-xl border-none bg-surface-container px-4 py-3 text-sm font-semibold focus:ring-primary/20">
                            @foreach ($disagreementOptions as $value => $label)
                                <option value="{{ $value }}" @selected($filters['disagreement'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>
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
                                        <h3 class="mt-4 text-lg font-extrabold text-on-surface">Tidak ada data yang cocok</h3>
                                        <p class="mt-2 text-sm font-medium text-on-surface-variant">
                                            Coba ubah filter atau reset.
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

                    @for ($pg = $pageStart; $pg <= $pageEnd; $pg++)
                        <a
                            href="{{ $applications->url($pg) }}"
                            class="flex h-9 w-9 items-center justify-center rounded-lg text-xs font-bold {{ $pg === $applications->currentPage() ? 'bg-primary text-white' : 'border border-slate-100 text-slate-600 hover:bg-slate-50' }}"
                        >
                            {{ $pg }}
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
