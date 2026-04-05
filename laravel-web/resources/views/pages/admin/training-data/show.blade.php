@extends('layouts.portal')

@section('title', 'Koreksi Data Training | KIP-K UNAIR')
@section('description', 'Halaman admin untuk meninjau dan mengoreksi data training yang dipakai retrain model KIP-K UNAIR')

@php
    $notice = session('admin_notice');
    $snapshot = $application->modelSnapshot;
    $student = $application->student;
    $adminName = $admin->name ?? 'Admin';
    $adminInitials = collect(preg_split('/\s+/', trim($adminName)) ?: [])
        ->filter()
        ->map(fn ($part) => strtoupper(mb_substr($part, 0, 1)))
        ->take(2)
        ->implode('');

    $displayName = $student?->name ?? $application->applicant_name ?? 'Mahasiswa';
    $displayMeta = collect([
        $application->faculty,
        $application->study_program,
        $student?->email ?? $application->applicant_email,
    ])->filter()->implode(' • ');

    $trainingFieldGroups = [
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
            <a href="{{ route('admin.applications.show', $application) }}" class="flex items-center gap-3 rounded-md px-4 py-3 font-semibold text-slate-500 transition-transform duration-200 hover:-translate-y-0.5 hover:bg-white">
                <span class="material-symbols-outlined">verified_user</span>
                <span class="text-sm">Review Pengajuan</span>
            </a>
            <a href="{{ route('admin.training-data.show', $application) }}" class="flex items-center gap-3 rounded-md border-t-2 border-yellow-500 bg-white px-4 py-3 font-semibold text-blue-700 shadow-sm transition-transform duration-200 hover:-translate-y-0.5">
                <span class="material-symbols-outlined">fact_check</span>
                <span class="text-sm">Koreksi Training</span>
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
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-on-surface">Koreksi Data Training</h1>
                <p class="mt-1 text-sm font-medium text-on-surface-variant">{{ $displayName }} @if($displayMeta !== '') • {{ $displayMeta }} @endif</p>
            </div>

            <div class="flex items-center gap-4">
                <div class="rounded-full border px-4 py-1.5 text-xs font-black uppercase tracking-[0.18em] {{ $trainingRow->admin_corrected ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-yellow-200 bg-yellow-50 text-yellow-700' }}">
                    {{ $trainingRow->admin_corrected ? 'SUDAH DIKOREKSI' : 'BELUM DIKOREKSI' }}
                </div>
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

            @if ($errors->any())
                <div class="rounded-2xl border border-red-200 bg-error-container px-5 py-4 text-on-error-container">
                    <p class="text-sm font-black uppercase tracking-[0.18em]">Periksa kembali input koreksi</p>
                    <ul class="mt-2 space-y-1 text-sm font-medium">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.05fr)_360px]">
                <div class="rounded-3xl border-t-4 border-secondary bg-white p-8 shadow-lg">
                    <div class="mb-6 flex items-start justify-between gap-6">
                        <div>
                            <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Dataset Retrain</p>
                            <h2 class="mt-1 text-2xl font-extrabold text-on-surface">Pastikan encoding final sesuai dokumen dan keputusan admin</h2>
                            <p class="mt-3 max-w-3xl text-sm font-medium leading-7 text-on-surface-variant">
                                Row ini adalah data canonical yang akan dipakai oleh Flask saat retrain CatBoost dan Naive Bayes.
                                Koreksi di halaman ini tidak mengubah data mentah mahasiswa, tetapi memperbaiki dataset training yang sudah final.
                            </p>
                        </div>

                        <div class="rounded-2xl bg-primary px-5 py-4 text-white shadow-lg shadow-primary/20">
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-blue-100">Label Saat Ini</p>
                            <p class="mt-2 text-3xl font-black">{{ $trainingRow->label }}</p>
                            <p class="mt-1 text-xs text-blue-100">Class {{ $trainingRow->label_class }}</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.training-data.update', $application) }}" class="space-y-8">
                        @csrf
                        @method('PUT')

                        @foreach ($trainingFieldGroups as $group)
                            <section class="space-y-4">
                                <div>
                                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">{{ $group['title'] }}</p>
                                </div>

                                <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-{{ count($group['fields']) >= 3 ? '3' : '2' }}">
                                    @foreach ($group['fields'] as $field => $meta)
                                        @php
                                            $label = is_array($meta) ? $meta['label'] : $meta;
                                            $optionKey = is_array($meta)
                                                ? $meta['options']
                                                : ($group['type'] === 'binary' ? 'binary' : ($group['type'] === 'income' ? 'income' : 'binary'));
                                            $options = $fieldOptions[$optionKey] ?? [];
                                        @endphp

                                        <div class="space-y-2">
                                            <label for="{{ $field }}" class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">{{ $label }}</label>
                                            <select
                                                id="{{ $field }}"
                                                name="{{ $field }}"
                                                class="w-full rounded-2xl border-none bg-surface-container py-3 text-sm font-semibold text-on-surface focus:ring-2 focus:ring-primary/20"
                                            >
                                                @foreach ($options as $value => $optionLabel)
                                                    <option value="{{ $value }}" @selected((int) old($field, $trainingRow->{$field}) === (int) $value)>{{ $optionLabel }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach

                        <section class="grid grid-cols-1 gap-5 md:grid-cols-2">
                            <div class="space-y-2">
                                <label for="label" class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Label Final Training</label>
                                <select
                                    id="label"
                                    name="label"
                                    class="w-full rounded-2xl border-none bg-surface-container py-3 text-sm font-semibold text-on-surface focus:ring-2 focus:ring-primary/20"
                                >
                                    <option value="Layak" @selected(old('label', $trainingRow->label) === 'Layak')>Layak</option>
                                    <option value="Indikasi" @selected(old('label', $trainingRow->label) === 'Indikasi')>Indikasi</option>
                                </select>
                            </div>

                            <div class="space-y-2">
                                <label for="correction_note" class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Catatan Koreksi</label>
                                <textarea
                                    id="correction_note"
                                    name="correction_note"
                                    rows="4"
                                    class="w-full rounded-2xl border-none bg-surface-container p-4 text-sm font-medium text-on-surface placeholder:text-slate-400 focus:ring-2 focus:ring-primary/20"
                                    placeholder="Tuliskan alasan koreksi agar audit retrain tetap jelas."
                                >{{ old('correction_note', $trainingRow->correction_note) }}</textarea>
                            </div>
                        </section>

                        <div class="flex flex-wrap items-center gap-3">
                            <button type="submit" class="flex items-center justify-center gap-2 rounded-2xl bg-primary px-5 py-3 text-sm font-black text-white shadow-lg shadow-primary/20 transition hover:bg-blue-700">
                                <span class="material-symbols-outlined text-lg">save</span>
                                Simpan Koreksi Training
                            </button>

                            <a href="{{ route('admin.applications.show', $application) }}" class="rounded-2xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                                Kembali ke Review Pengajuan
                            </a>
                        </div>
                    </form>
                </div>

                <div class="space-y-6">
                    <div class="rounded-3xl bg-white p-6 shadow-lg">
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Legenda Encoding</p>
                        <div class="mt-4 space-y-4 text-sm font-medium text-slate-600">
                            @foreach ($legend as $label => $description)
                                <div class="rounded-2xl bg-surface-container p-4">
                                    <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">{{ str_replace('_', ' ', $label) }}</p>
                                    <p class="mt-2 leading-6">{{ $description }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="rounded-3xl bg-white p-6 shadow-lg">
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Konteks Pengajuan</p>
                        <div class="mt-4 space-y-4 text-sm font-medium text-slate-600">
                            <div class="rounded-2xl bg-surface-container p-4">
                                <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Status Final Admin</p>
                                <p class="mt-2 text-lg font-black text-on-surface">{{ $application->status }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $application->admin_decided_at?->format('d M Y H:i') ?? '-' }}</p>
                            </div>

                            @if ($snapshot)
                                <div class="rounded-2xl bg-surface-container p-4">
                                    <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Snapshot Model</p>
                                    <p class="mt-2 text-sm font-bold text-on-surface">CatBoost {{ $snapshot->catboost_label ?? '-' }} · NB {{ $snapshot->naive_bayes_label ?? '-' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Rekomendasi akhir: {{ $snapshot->final_recommendation ?? '-' }}</p>
                                </div>
                            @endif

                            <div class="rounded-2xl bg-surface-container p-4">
                                <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Metadata Training</p>
                                <p class="mt-2 text-sm font-bold text-on-surface">Schema v{{ $trainingRow->schema_version }} · Encoding v{{ $trainingRow->encoding_version }}</p>
                                <p class="mt-1 text-xs text-slate-500">Dikunci final pada {{ $trainingRow->finalized_at?->format('d M Y H:i') ?? '-' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>
@endsection
