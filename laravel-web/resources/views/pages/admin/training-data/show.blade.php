@extends('layouts.portal')

@section('title', 'Koreksi Data Training | KIP-K UNAIR')
@section('description', 'Halaman admin untuk meninjau dan mengoreksi data training yang dipakai retrain model KIP-K UNAIR')

@php
    $notice = session('admin_notice');
    $snapshot = $application->modelSnapshot;
    $displayName = $page['display_name'];
    $displayMeta = $page['display_meta'];
    $score = $page['score'];
    $trainingStatus = $page['training_status'];
    $trainingFieldGroups = $page['training_field_groups'];
    $legendCards = $page['legend_cards'];
    $contextNotes = $page['context_notes'];
@endphp

@section('content')
<main class="min-h-screen bg-background">
    @include('pages.admin.partials.sidebar', ['active' => 'training', 'application' => $application, 'showTrainingLink' => true])

    <div class="md:ml-64">
        <x-admin.topbar
            :admin="$admin"
            title="Koreksi Data Training"
            :subtitle="trim($displayName.($displayMeta !== '' ? ' • '.$displayMeta : ''))"
            subtitle-class="mt-1 text-sm font-medium text-on-surface-variant"
        >
            <x-slot:meta>
                <div class="flex items-center gap-2 rounded-full border px-4 py-1.5 text-xs font-black uppercase tracking-[0.18em] {{ $trainingStatus['classes'] }}">
                    <span class="material-symbols-outlined text-sm">{{ $trainingStatus['icon'] }}</span>
                    {{ $trainingRow->admin_corrected ? 'Sudah Dikoreksi' : 'Belum Dikoreksi' }}
                </div>
            </x-slot:meta>
        </x-admin.topbar>

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

            <section class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <div class="rounded-3xl border-t-4 border-secondary bg-white p-6 shadow-lg md:col-span-2">
                    <div class="mb-5 flex items-center gap-3">
                        <span class="material-symbols-outlined text-primary">info</span>
                        <h2 class="text-lg font-bold text-on-surface">Legenda Encoding dan Panduan Koreksi</h2>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($legendCards as $card)
                            <div class="rounded-2xl bg-surface-container p-4">
                                <p class="text-xs font-black uppercase tracking-[0.18em] text-primary">{{ $card['title'] }}</p>
                                <p class="mt-2 text-xs leading-6 text-on-surface-variant">{{ $card['description'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="relative overflow-hidden rounded-3xl {{ $score['tone'] }} p-6">
                    <div class="absolute -right-10 -bottom-10 h-32 w-32 rounded-full bg-white/10 blur-2xl"></div>
                    <div class="relative z-10">
                        <p class="text-sm font-black uppercase tracking-[0.2em] text-white/80">Skor AI Saat Ini</p>
                        <div class="mt-3 text-5xl font-extrabold">{{ number_format($score['current'], 1, ',', '.') }}<span class="text-xl opacity-70">%</span></div>
                        <div class="mt-4 inline-flex items-center gap-2 rounded-full bg-white/20 px-3 py-1 text-xs font-black uppercase tracking-[0.16em] backdrop-blur-sm">
                            <span class="material-symbols-outlined text-sm">stars</span>
                            Klasifikasi: {{ $score['label'] }}
                        </div>
                        <p class="mt-4 text-sm leading-6 text-white/85">
                            Nilai ini membantu admin membaca kecenderungan model sebelum menetapkan koreksi akhir pada data training.
                        </p>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.05fr)_360px]">
                <div class="overflow-hidden rounded-3xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-slate-100 bg-surface-container-low px-6 py-4">
                        <h2 class="flex items-center gap-2 text-lg font-bold text-on-surface">
                            <span class="material-symbols-outlined text-primary">edit_square</span>
                            Panel Koreksi Parameter Data
                        </h2>
                        <span class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Pastikan sesuai dokumen fisik</span>
                    </div>

                    <form method="POST" action="{{ route('admin.training-data.update', $application) }}" class="p-8">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 gap-x-8 gap-y-6 md:grid-cols-2 lg:grid-cols-4">
                            @foreach ($trainingFieldGroups as $group)
                                @foreach ($group['fields'] as $field => $meta)
                                    @php
                                        $label = is_array($meta) ? $meta['label'] : $meta;
                                        $optionKey = is_array($meta)
                                            ? $meta['options']
                                            : ($group['type'] === 'binary' ? 'binary' : ($group['type'] === 'income' ? 'income' : 'binary'));
                                        $options = $fieldOptions[$optionKey] ?? [];
                                    @endphp

                                    <div class="space-y-1.5">
                                        <label for="{{ $field }}" class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">{{ $label }}</label>
                                        <select
                                            id="{{ $field }}"
                                            name="{{ $field }}"
                                            class="w-full rounded-xl border-none bg-surface-container py-3 text-sm font-semibold text-on-surface focus:ring-2 focus:ring-primary/20"
                                        >
                                            @foreach ($options as $value => $optionLabel)
                                                <option value="{{ $value }}" @selected((int) old($field, $trainingRow->{$field}) === (int) $value)>{{ $optionLabel }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endforeach

                                @if (! $loop->last)
                                    <div class="col-span-full border-t border-slate-100 pt-1"></div>
                                @endif
                            @endforeach

                            <div class="space-y-1.5 lg:col-span-1">
                                <label for="label" class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Label Final Training</label>
                                <select
                                    id="label"
                                    name="label"
                                    class="w-full rounded-xl border-none bg-primary-container py-3 text-sm font-extrabold uppercase tracking-tight text-on-primary-container focus:ring-2 focus:ring-primary/20"
                                >
                                    <option value="Layak" @selected(old('label', $trainingRow->label) === 'Layak')>Layak</option>
                                    <option value="Indikasi" @selected(old('label', $trainingRow->label) === 'Indikasi')>Indikasi</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-10 space-y-6 border-t border-slate-100 pt-10">
                            <div class="space-y-2">
                                <label for="correction_note" class="text-sm font-bold text-on-surface">Catatan Verifikator / Alasan Koreksi</label>
                                <textarea
                                    id="correction_note"
                                    name="correction_note"
                                    rows="4"
                                    class="w-full rounded-xl border border-slate-200 bg-surface-container-low p-4 text-sm font-medium text-on-surface placeholder:text-slate-400 focus:ring-2 focus:ring-primary/20"
                                    placeholder="Tuliskan temuan atau alasan jika terdapat perubahan data encoding yang signifikan..."
                                >{{ old('correction_note', $trainingRow->correction_note) }}</textarea>
                            </div>

                            <div class="flex flex-col items-start justify-between gap-4 lg:flex-row lg:items-center">
                                <div class="flex items-center gap-3 text-slate-400">
                                    <span class="material-symbols-outlined text-lg">history</span>
                                    <span class="text-xs font-medium italic">
                                        Terakhir diperbarui pada {{ $trainingRow->updated_at?->format('d M Y, H:i') ?? '-' }}
                                    </span>
                                </div>

                                <div class="flex flex-wrap gap-3">
                                    <a href="{{ route('admin.applications.show', $application) }}" class="rounded-xl bg-surface-container px-6 py-3.5 text-sm font-bold text-on-surface transition-colors hover:bg-surface-container-high">
                                        Batalkan
                                    </a>
                                    <button type="submit" class="flex items-center gap-2 rounded-xl bg-primary px-10 py-3.5 text-sm font-bold text-on-primary shadow-lg shadow-primary/20 transition-colors hover:bg-blue-700">
                                        <span class="material-symbols-outlined">save</span>
                                        Simpan Koreksi Training
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="space-y-6">
                    <div class="rounded-3xl bg-white p-6 shadow-lg">
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Legenda Lengkap</p>
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

            <section class="grid grid-cols-1 gap-6 pb-12 md:grid-cols-2">
                @foreach ($contextNotes as $note)
                    <article class="rounded-3xl border border-slate-100 bg-white p-6 shadow-lg">
                        <div class="flex items-start gap-4">
                            <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl {{ $note['icon_wrap'] }}">
                                <span class="material-symbols-outlined text-3xl">{{ $note['icon'] }}</span>
                            </div>
                            <div>
                                <h3 class="font-bold text-on-surface">{{ $note['title'] }}</h3>
                                <p class="mt-2 text-xs leading-6 text-on-surface-variant">{{ $note['description'] }}</p>
                                @if ($note['href'])
                                    <a href="{{ $note['href'] }}" target="_blank" class="mt-3 inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline">
                                        {{ $note['cta'] }}
                                        <span class="material-symbols-outlined text-sm">open_in_new</span>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </section>

            <x-admin.footer />
        </div>
    </div>
</main>
@endsection
