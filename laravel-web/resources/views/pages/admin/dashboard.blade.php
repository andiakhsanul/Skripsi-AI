@extends('layouts.portal')

@section('title', 'Dasbor Admin | KIP-K UNAIR')
@section('description', 'Dashboard admin untuk monitoring pengajuan KIP-K mahasiswa UNAIR')

@php
    $applicationStats = $summary['applications'];
    $trainingStats = $summary['training_data'];
    $activeSchema = $summary['active_schema'];
    $adminName = $admin->name ?? 'Admin';
    $adminInitials = collect(preg_split('/\s+/', trim($adminName)) ?: [])
        ->filter()
        ->map(fn ($part) => strtoupper(mb_substr($part, 0, 1)))
        ->take(2)
        ->implode('');

    $statusOptions = [
        '' => 'Semua status',
        'Submitted' => 'Menunggu',
        'Verified' => 'Terverifikasi',
        'Rejected' => 'Ditolak',
    ];

    $priorityOptions = [
        '' => 'Semua prioritas',
        'high' => 'Tinggi',
        'normal' => 'Normal',
    ];

    $statusDisplayLabels = [
        'Submitted' => 'Menunggu',
        'Verified' => 'Terverifikasi',
        'Rejected' => 'Ditolak',
    ];

    $statusBadgeClasses = [
        'Submitted' => 'bg-yellow-50 text-yellow-700 border border-yellow-100',
        'Verified' => 'bg-emerald-50 text-emerald-600 border border-emerald-100',
        'Rejected' => 'bg-error-container text-error border border-error/10',
    ];

    $priorityMeta = [
        'high' => [
            'dot' => 'bg-error',
            'text' => 'text-error font-bold',
            'label' => 'Tinggi',
        ],
        'normal' => [
            'dot' => 'bg-slate-300',
            'text' => 'text-slate-500 font-medium',
            'label' => 'Normal',
        ],
    ];

    $statCards = [
        [
            'label' => 'Total Pengajuan',
            'value' => $applicationStats['total'],
            'hint' => 'Semua pengajuan yang tercatat',
            'hint_class' => 'text-slate-500',
            'border' => 'border-primary',
            'icon_wrap' => 'bg-primary/10 text-primary',
            'icon' => 'groups',
        ],
        [
            'label' => 'Menunggu',
            'value' => $applicationStats['submitted'],
            'hint' => 'Menunggu review admin',
            'hint_class' => 'text-yellow-600',
            'border' => 'border-yellow-500',
            'icon_wrap' => 'bg-yellow-50 text-yellow-600',
            'icon' => 'schedule',
        ],
        [
            'label' => 'Terverifikasi',
            'value' => $applicationStats['verified'],
            'hint' => 'Diputuskan layak',
            'hint_class' => 'text-emerald-600',
            'border' => 'border-emerald-500',
            'icon_wrap' => 'bg-emerald-50 text-emerald-600',
            'icon' => 'check_circle',
        ],
        [
            'label' => 'Ditolak',
            'value' => $applicationStats['rejected'],
            'hint' => 'Diputuskan indikasi',
            'hint_class' => 'text-error',
            'border' => 'border-error',
            'icon_wrap' => 'bg-error-container text-error',
            'icon' => 'cancel',
        ],
        [
            'label' => 'Prioritas Tinggi',
            'value' => $applicationStats['high_priority_pending'],
            'hint' => 'Perlu tindakan cepat',
            'hint_class' => 'text-primary',
            'border' => 'border-primary-container',
            'icon_wrap' => 'bg-primary-container text-primary',
            'icon' => 'priority_high',
        ],
        [
            'label' => 'Data Latih',
            'value' => $trainingStats['total_active'],
            'hint' => 'Siap untuk retrain',
            'hint_class' => 'text-slate-500',
            'border' => 'border-slate-900',
            'icon_wrap' => 'bg-slate-100 text-slate-800',
            'icon' => 'model_training',
        ],
    ];

    $pageStart = max(1, $applications->currentPage() - 1);
    $pageEnd = min($applications->lastPage(), $applications->currentPage() + 1);
@endphp

@section('content')
<div
    id="dashboard-notice"
    class="hidden fixed right-4 top-4 z-50 max-w-sm rounded-2xl border px-4 py-3 text-sm font-semibold shadow-2xl"
></div>

<aside class="fixed left-0 top-0 hidden h-screen w-64 flex-col border-r border-slate-100 bg-slate-50 md:flex">
    <div class="p-6">
        <p class="text-lg font-black uppercase tracking-[0.2em] text-blue-900">KIP-K UNAIR</p>
    </div>

    <nav class="flex flex-1 flex-col gap-2 p-4">
        <a
            href="{{ route('admin.dashboard') }}"
            class="flex items-center gap-3 rounded-md border-t-2 border-yellow-500 bg-white px-4 py-3 font-semibold text-blue-700 shadow-sm transition-transform duration-200 hover:-translate-y-0.5"
        >
            <span class="material-symbols-outlined">dashboard_customize</span>
            <span class="text-sm">Dasbor Admin</span>
        </a>

        <span class="flex cursor-not-allowed items-center gap-3 px-4 py-3 text-slate-500">
            <span class="material-symbols-outlined">assignment_ind</span>
            <span class="text-sm">Pengajuan</span>
        </span>

        <span class="flex cursor-not-allowed items-center gap-3 px-4 py-3 text-slate-500">
            <span class="material-symbols-outlined">verified_user</span>
            <span class="text-sm">Verifikasi</span>
        </span>

        <span class="flex cursor-not-allowed items-center gap-3 px-4 py-3 text-slate-500">
            <span class="material-symbols-outlined">analytics</span>
            <span class="text-sm">Laporan</span>
        </span>

        <div class="mt-8 px-4">
            <a
                href="{{ route('admin.models.retrain') }}"
                class="flex w-full items-center justify-center rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white shadow-lg shadow-primary/20 transition-all hover:scale-[1.02] active:scale-95"
            >
                Latih Ulang Model
            </a>
        </div>
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

<main class="min-h-screen md:ml-64">
    <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-100 bg-white/80 px-6 backdrop-blur-md md:px-8">
        <div>
            <h2 class="text-xl font-extrabold tracking-tighter text-blue-800">Administrator</h2>
            <p class="text-[10px] font-medium uppercase tracking-[0.2em] text-slate-400">Divisi Kemahasiswaan</p>
        </div>

        <div class="flex items-center gap-4 md:gap-6">
            <div class="hidden items-center gap-4 text-slate-600 md:flex">
                <a
                    href="{{ url('/api/admin/stats') }}"
                    target="_blank"
                    class="rounded-lg p-2 transition-all hover:bg-slate-50"
                    title="Lihat API statistik"
                >
                    <span class="material-symbols-outlined block">monitoring</span>
                </a>
                <a
                    href="{{ route('admin.models.retrain') }}"
                    class="rounded-lg p-2 transition-all hover:bg-slate-50"
                    title="Latih ulang model"
                >
                    <span class="material-symbols-outlined block">model_training</span>
                </a>
            </div>

            <div class="flex items-center gap-3 border-l border-slate-100 pl-4 md:pl-6">
                <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 border-primary/10 bg-primary-container font-bold text-primary shadow-sm">
                    {{ $adminInitials !== '' ? $adminInitials : 'AD' }}
                </div>
                <div class="hidden text-right md:block">
                    <p class="text-sm font-bold text-on-surface">{{ $adminName }}</p>
                    <p class="text-[11px] font-medium text-slate-400">{{ $admin->email }}</p>
                </div>
            </div>
        </div>
    </header>

    <div class="mx-auto w-full max-w-screen-2xl p-6 md:p-8">
        <section class="mb-8 flex flex-col justify-between gap-4 xl:flex-row xl:items-end">
            <div>
                <h1 class="mb-1 text-3xl font-extrabold tracking-tight text-on-surface">Dasbor Beasiswa</h1>
                <p class="font-medium text-on-surface-variant">
                    Monitoring pengajuan mahasiswa, hasil machine learning, dan keputusan admin dalam satu ruang kerja.
                </p>
                <div class="mt-4 flex flex-wrap gap-3 text-xs font-semibold">
                    <span class="rounded-full bg-white px-4 py-2 text-slate-600 shadow-sm ring-1 ring-outline-variant">
                        Skema aktif:
                        <span class="text-primary">{{ $activeSchema?->version ? 'v'.$activeSchema->version : 'Belum ada' }}</span>
                    </span>
                    <span class="rounded-full bg-white px-4 py-2 text-slate-600 shadow-sm ring-1 ring-outline-variant">
                        Snapshot siap:
                        <span class="text-primary">{{ $applicationStats['model_ready'] }}</span>
                    </span>
                    <span class="rounded-full bg-white px-4 py-2 text-slate-600 shadow-sm ring-1 ring-outline-variant">
                        Koreksi admin:
                        <span class="text-primary">{{ $trainingStats['admin_corrected'] }}</span>
                    </span>
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <a
                    href="{{ route('admin.dashboard', $filters) }}"
                    class="flex items-center gap-2 rounded-xl border border-outline-variant bg-surface px-5 py-2.5 text-sm font-semibold text-on-surface transition-all hover:bg-surface-container"
                >
                    <span class="material-symbols-outlined text-lg">refresh</span>
                    Muat Ulang
                </a>

                <a
                    href="{{ route('admin.models.retrain') }}"
                    class="flex items-center gap-2 rounded-xl bg-secondary px-5 py-2.5 text-sm font-bold text-on-secondary shadow-lg shadow-secondary/20 transition-all hover:scale-[1.02]"
                >
                    <span class="material-symbols-outlined text-lg">model_training</span>
                    Latih Ulang Model
                </a>
            </div>
        </section>

        <section class="mb-10 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            @foreach ($statCards as $card)
                <div class="flex flex-col justify-between rounded-xl bg-white p-5 shadow-sm transition-shadow duration-300 hover:shadow-lg border-t-2 {{ $card['border'] }}">
                    <div class="mb-4 flex items-start justify-between">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg {{ $card['icon_wrap'] }}">
                            <span class="material-symbols-outlined">{{ $card['icon'] }}</span>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">{{ $card['label'] }}</span>
                    </div>

                    <div>
                        <span class="mb-1 block text-3xl font-black text-on-surface">{{ number_format($card['value']) }}</span>
                        <p class="text-[11px] font-bold {{ $card['hint_class'] }}">{{ $card['hint'] }}</p>
                    </div>
                </div>
            @endforeach
        </section>

        <section class="mb-10 grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
            <div class="rounded-xl border border-slate-100 bg-white p-6 shadow-lg">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                        <span class="material-symbols-outlined">policy</span>
                    </div>
                    <div>
                        <p class="text-sm font-black uppercase tracking-[0.2em] text-slate-400">Aturan Alur Kerja</p>
                        <h2 class="text-xl font-extrabold text-on-surface">Keputusan akhir tetap di tangan admin</h2>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    <div class="rounded-2xl bg-surface-container p-4">
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Data Mahasiswa</p>
                        <p class="mt-2 text-sm font-semibold text-on-surface">Mahasiswa mengirim 13 atribut utama dan dokumen pendukung melalui portal atau impor admin.</p>
                    </div>
                    <div class="rounded-2xl bg-surface-container p-4">
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Mesin Prediksi</p>
                        <p class="mt-2 text-sm font-semibold text-on-surface">CatBoost menjadi rekomendasi utama, sementara Naive Bayes dipakai sebagai pembanding hasil.</p>
                    </div>
                    <div class="rounded-2xl bg-surface-container p-4">
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Keputusan Akhir</p>
                        <p class="mt-2 text-sm font-semibold text-on-surface">Status terverifikasi atau ditolak menjadi dasar data latih ketika proses retrain dijalankan.</p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-100 bg-white p-6 shadow-lg">
                <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Ringkasan Sistem</p>
                <div class="mt-4 space-y-4">
                    <div class="rounded-2xl bg-surface-container p-4">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Sumber Skema</p>
                        <p class="mt-2 text-sm font-semibold text-on-surface">
                            {{ $activeSchema?->source_file_name ?? 'Belum ada file schema aktif' }}
                        </p>
                    </div>

                    <div class="rounded-2xl bg-surface-container p-4">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Antrean Prioritas Tinggi</p>
                        <p class="mt-2 text-2xl font-black text-error">{{ $applicationStats['high_priority_pending'] }}</p>
                    </div>

                    <div class="rounded-2xl bg-surface-container p-4">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Koreksi Data Latih</p>
                        <p class="mt-2 text-2xl font-black text-on-surface">{{ $trainingStats['admin_corrected'] }}</p>
                        <p class="mt-1 text-xs font-medium text-slate-500">Jumlah record training yang sudah dikoreksi manual oleh admin.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-100 bg-white shadow-lg">
            <div class="flex flex-col gap-4 border-b border-slate-100 p-6 lg:flex-row lg:items-center lg:justify-between">
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

                        <button
                            type="submit"
                            class="rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white transition-colors hover:bg-primary/90"
                        >
                            Terapkan
                        </button>

                        <a
                            href="{{ route('admin.dashboard') }}"
                            class="rounded-xl bg-surface-container px-4 py-3 text-center text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-200"
                        >
                            Reset
                        </a>
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
                            @php
                                $student = $application->student;
                                $snapshot = $application->modelSnapshot;
                                $studentName = $student?->name ?? $application->applicant_name ?? 'Nama belum tersedia';
                                $studentEmail = $student?->email ?? $application->applicant_email ?? 'Email belum tersedia';
                                $studentInitials = collect(preg_split('/\s+/', trim($studentName)) ?: [])
                                    ->filter()
                                    ->map(fn ($part) => strtoupper(mb_substr($part, 0, 1)))
                                    ->take(2)
                                    ->implode('');
                                $statusClass = $statusBadgeClasses[$application->status] ?? 'bg-slate-100 text-slate-600 border border-slate-200';
                                $statusLabel = $statusDisplayLabels[$application->status] ?? $application->status;
                                $priority = $snapshot?->review_priority ?? 'normal';
                                $priorityDisplay = $priorityMeta[$priority] ?? $priorityMeta['normal'];
                                $pdfUrl = $application->submitted_pdf_path
                                    ? \Illuminate\Support\Facades\Storage::disk('public')->url($application->submitted_pdf_path)
                                    : $application->source_document_link;
                                $documentLabel = $application->submitted_pdf_path ? 'PDF' : 'Berkas';
                            @endphp

                            <tr class="transition-colors hover:bg-slate-50/50">
                                <td class="px-6 py-5">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700">
                                            {{ $studentInitials !== '' ? $studentInitials : 'MH' }}
                                        </div>
                                        <div>
                                            <p class="text-sm font-bold text-on-surface">{{ $studentName }}</p>
                                            <p class="text-[11px] font-medium text-slate-400">{{ $studentEmail }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <p class="text-sm font-medium text-on-surface">{{ $application->faculty ?? 'Fakultas belum tersedia' }}</p>
                                    <p class="text-[11px] text-slate-400">{{ $application->study_program ?? 'Program studi belum tersedia' }}</p>
                                    <p class="mt-1 text-[11px] text-slate-400">Dibuat {{ $application->created_at?->format('d/m/Y H:i') ?? '-' }}</p>
                                </td>
                                <td class="px-6 py-5">
                                    <span class="rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-tight {{ $statusClass }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-2 rounded-full {{ $priorityDisplay['dot'] }}"></div>
                                        <span class="text-xs {{ $priorityDisplay['text'] }}">{{ $priorityDisplay['label'] }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <p class="text-sm font-semibold text-on-surface">
                                        {{ $snapshot?->final_recommendation ?? 'Belum diproses model' }}
                                    </p>
                                    <p class="text-[11px] text-slate-400">
                                        @if ($snapshot?->model_ready)
                                            CatBoost {{ $snapshot->catboost_label ?? '-' }} · NB {{ $snapshot->naive_bayes_label ?? '-' }}
                                        @else
                                            Belum ada snapshot model
                                        @endif
                                    </p>
                                    @if ($snapshot?->disagreement_flag)
                                        <p class="mt-1 text-[11px] font-semibold text-error">CatBoost dan Naive Bayes memberi hasil yang berbeda.</p>
                                    @endif
                                </td>
                                <td class="px-6 py-5">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <a
                                            href="{{ url('/api/admin/applications/'.$application->id) }}"
                                            target="_blank"
                                            class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 transition-colors hover:bg-slate-50"
                                        >
                                            Lihat
                                        </a>

                                        @if ($pdfUrl)
                                            <a
                                                href="{{ $pdfUrl }}"
                                                target="_blank"
                                                class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 transition-colors hover:bg-slate-50"
                                            >
                                                {{ $documentLabel }}
                                            </a>
                                        @endif

                                        @if ($application->status === 'Submitted')
                                            <button
                                                type="button"
                                                data-decision-endpoint="{{ url('/api/admin/applications/'.$application->id.'/verify') }}"
                                                data-decision-action="verify"
                                                data-student-name="{{ $studentName }}"
                                                class="rounded-lg bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700 transition-colors hover:bg-emerald-100"
                                            >
                                                Verifikasi
                                            </button>
                                            <button
                                                type="button"
                                                data-decision-endpoint="{{ url('/api/admin/applications/'.$application->id.'/reject') }}"
                                                data-decision-action="reject"
                                                data-student-name="{{ $studentName }}"
                                                class="rounded-lg bg-error-container px-3 py-2 text-xs font-bold text-error transition-colors hover:bg-red-100"
                                            >
                                                Tolak
                                            </button>
                                        @else
                                            <span class="inline-flex items-center rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-500">
                                                Selesai
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
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

    <footer class="mt-auto w-full border-t border-slate-100 bg-white py-8">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-8 md:flex-row">
            <div class="text-left">
                <p class="mb-1 font-bold text-slate-900">Sistem Manajemen KIP-K</p>
                <p class="text-[10px] font-medium uppercase tracking-[0.18em] text-slate-400">
                    © {{ now()->year }} Universitas Airlangga. Dashboard admin KIP-K.
                </p>
            </div>
            <div class="flex gap-6">
                <a class="text-[10px] font-medium uppercase tracking-[0.18em] text-slate-400 transition-all duration-300 hover:text-blue-500 hover:underline" href="{{ url('/api/admin/stats') }}" target="_blank">
                    API Statistik
                </a>
                <a class="text-[10px] font-medium uppercase tracking-[0.18em] text-slate-400 transition-all duration-300 hover:text-blue-500 hover:underline" href="{{ url('/api/admin/applications') }}" target="_blank">
                    API Pengajuan
                </a>
                <a class="text-[10px] font-medium uppercase tracking-[0.18em] text-slate-400 transition-all duration-300 hover:text-blue-500 hover:underline" href="{{ url('/api/admin/parameters/schema-versions') }}" target="_blank">
                    API Skema
                </a>
            </div>
        </div>
    </footer>
</main>
@endsection

@push('scripts')
<script>
    (() => {
        const notice = document.getElementById('dashboard-notice');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        const showNotice = (message, type = 'success') => {
            if (!notice) {
                return;
            }

            const palette = type === 'error'
                ? ['bg-error-container', 'border-red-200', 'text-on-error-container']
                : ['bg-white', 'border-slate-200', 'text-slate-700'];

            notice.className = `fixed right-4 top-4 z-50 max-w-sm rounded-2xl border px-4 py-3 text-sm font-semibold shadow-2xl ${palette.join(' ')}`;
            notice.textContent = message;
            notice.classList.remove('hidden');

            window.clearTimeout(window.__dashboardNoticeTimer);
            window.__dashboardNoticeTimer = window.setTimeout(() => {
                notice.classList.add('hidden');
            }, 4000);
        };

        const postJson = async (endpoint, payload, button) => {
            if (!endpoint || !csrfToken) {
                showNotice('Konfigurasi request belum lengkap.', 'error');
                return null;
            }

            const originalLabel = button?.innerHTML;

            if (button) {
                button.disabled = true;
                button.innerHTML = 'Memproses...';
            }

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload),
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message || 'Request gagal diproses.');
                }

                return data;
            } catch (error) {
                showNotice(error.message || 'Terjadi kesalahan saat memproses request.', 'error');
                return null;
            } finally {
                if (button) {
                    button.disabled = false;
                    button.innerHTML = originalLabel;
                }
            }
        };

        document.querySelectorAll('[data-decision-endpoint]').forEach((button) => {
            button.addEventListener('click', async () => {
                const action = button.dataset.decisionAction === 'verify' ? 'verify' : 'reject';
                const studentName = button.dataset.studentName || 'mahasiswa ini';
                const promptLabel = action === 'verify'
                    ? `Catatan verifikasi untuk ${studentName} (boleh kosong):`
                    : `Alasan reject untuk ${studentName} (boleh kosong):`;
                const note = window.prompt(promptLabel, '');

                if (note === null) {
                    return;
                }

                const data = await postJson(button.dataset.decisionEndpoint, { note }, button);

                if (!data) {
                    return;
                }

                showNotice(data.message || 'Status pengajuan berhasil diperbarui.');
                window.setTimeout(() => window.location.reload(), 700);
            });
        });
    })();
</script>
@endpush
