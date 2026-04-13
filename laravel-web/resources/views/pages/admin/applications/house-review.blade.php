@extends('layouts.portal')

@section('title', 'Kelengkapan Data Mentah | KIP-K UNAIR')
@section('description', 'Halaman admin untuk melengkapi data mentah applicant offline sebelum encoding')

@php
    $summaryCards = $page['summary_cards'];
    $guides = $page['guides'];
    $filterOptions = $page['filter_options'];
    $binaryOptions = $page['binary_options'];
    $houseStatusOptions = $page['house_status_options'];
@endphp

@section('content')
@include('pages.admin.partials.sidebar', ['active' => 'house-review'])

<main class="min-h-screen bg-background md:ml-64">
    <x-admin.topbar
        :admin="$admin"
        title="Kelengkapan Data Mentah"
        subtitle="Data Mentah Applicant Offline"
        title-class="text-xl font-extrabold tracking-tighter text-blue-800"
        subtitle-class="text-[10px] font-medium uppercase tracking-[0.2em] text-slate-400"
        height-class="h-16"
    >
        <x-slot:actions>
            <a
                href="{{ route('admin.dashboard', ['scope' => 'all']) }}"
                class="rounded-xl bg-surface-container px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-200"
            >
                Kembali ke Dasbor
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

        <section class="mb-8 grid grid-cols-1 gap-6 xl:grid-cols-12">
            <div class="relative overflow-hidden rounded-3xl bg-primary px-8 py-10 text-white shadow-2xl xl:col-span-7 xl:px-10">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_rgba(255,255,255,0.22),_transparent_38%),linear-gradient(145deg,rgba(255,255,255,0.08),transparent_55%)]"></div>
                <div class="absolute -right-20 top-8 h-56 w-56 rounded-full bg-white/10 blur-3xl"></div>
                <div class="absolute -bottom-16 left-12 h-40 w-40 rounded-full bg-secondary/20 blur-3xl"></div>

                <div class="relative z-10">
                    <span class="mb-4 block text-sm font-black uppercase tracking-[0.24em] text-secondary-container">Pembersihan Data Mentah</span>
                    <h1 class="max-w-3xl text-4xl font-black leading-tight tracking-tight">Lengkapi semua data mentah wajib sebelum dipakai ke encoding dan model.</h1>
                    <p class="mt-5 max-w-2xl text-sm font-medium leading-7 text-blue-100/90">
                        Halaman ini dipakai untuk merapikan kartu pendukung, penghasilan, tanggungan, status orang tua, rumah, dan listrik.
                        Perubahan di sini belum memasukkan apa pun ke data training, sehingga aman dipakai sebagai tahap cleansing awal.
                    </p>

                    <div class="mt-6 grid gap-4 md:grid-cols-3">
                        @foreach ($guides as $guide)
                            <article class="rounded-2xl border border-white/12 bg-white/10 p-4 backdrop-blur-sm">
                                <p class="text-sm font-semibold leading-6 text-blue-100/95">{{ $guide }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="space-y-4 xl:col-span-5">
                @foreach ($summaryCards as $card)
                    <article class="rounded-3xl bg-white p-6 shadow-lg">
                        <div class="flex items-center gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl {{ $card['tone'] }}">
                                <span class="material-symbols-outlined">{{ $card['icon'] }}</span>
                            </div>
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">{{ $card['label'] }}</p>
                                <p class="mt-1 text-3xl font-black text-on-surface">{{ $card['value'] }}</p>
                                <p class="mt-1 text-sm font-medium text-slate-500">{{ $card['hint'] }}</p>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="overflow-hidden rounded-3xl bg-white shadow-lg">
            <div class="flex flex-col gap-4 border-b border-slate-100 px-6 py-6 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Tabel Koreksi</p>
                    <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-on-surface">Kelola kelengkapan data applicant</h2>
                    <p class="mt-2 max-w-2xl text-sm font-medium text-on-surface-variant">Secara default tabel fokus ke row yang masih punya data wajib kosong. Gunakan dokumen pendukung per baris untuk melengkapi nilai yang belum tersedia.</p>
                </div>

                <form method="GET" action="{{ route('admin.applications.house-review') }}" class="flex w-full flex-col gap-3 lg:w-auto lg:flex-row">
                    <div class="relative w-full lg:w-72">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">search</span>
                        <input
                            type="text"
                            name="q"
                            value="{{ $filters['q'] }}"
                            placeholder="Cari nama, prodi, atau referensi..."
                            class="w-full rounded-xl border-none bg-surface-container py-3 pl-12 pr-4 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-primary/20"
                        />
                    </div>

                    <select
                        name="house_state"
                        class="cursor-pointer rounded-xl border-none bg-surface-container px-4 py-3 text-sm font-semibold focus:ring-primary/20"
                    >
                        @foreach ($filterOptions as $value => $label)
                            <option value="{{ $value }}" @selected($filters['house_state'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>

                    <button
                        type="submit"
                        class="rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white transition hover:bg-blue-700"
                    >
                        Terapkan
                    </button>

                    <a
                        href="{{ route('admin.applications.house-review') }}"
                        class="rounded-xl bg-surface-container px-4 py-3 text-center text-sm font-semibold text-slate-600 transition hover:bg-slate-200"
                    >
                        Reset
                    </a>
                </form>
            </div>

            <form method="POST" action="{{ route('admin.applications.house-review.batch-update') }}" data-raw-completion-form>
                @csrf
                <input type="hidden" name="q" value="{{ $filters['q'] }}">
                <input type="hidden" name="house_state" value="{{ $filters['house_state'] }}">

                <div class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/70 px-6 py-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-sm font-bold text-slate-700">Perubahan pada halaman ini akan disimpan sekaligus.</p>
                        <p class="text-xs font-medium text-slate-500">Draft tersimpan otomatis di browser. Jika session habis, login ulang lalu buka halaman ini untuk memulihkan isian yang belum sempat disubmit.</p>
                        <p class="mt-1 text-xs font-black uppercase tracking-[0.14em] text-amber-600" data-draft-status></p>
                    </div>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-black text-white shadow-lg shadow-primary/20 transition hover:bg-blue-700"
                    >
                        <span class="material-symbols-outlined text-base">save</span>
                        Simpan Perubahan Halaman Ini
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-black uppercase tracking-[0.18em] text-slate-500">
                                <th class="px-5 py-4">Applicant</th>
                                <th class="px-5 py-4">Referensi</th>
                                <th class="px-5 py-4">Kelengkapan Saat Ini</th>
                                <th class="px-5 py-4">Konteks Ringkas</th>
                                <th class="px-5 py-4">Dokumen</th>
                                <th class="px-5 py-4">Data Mentah yang Dilengkapi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($applications as $application)
                                @include('pages.admin.applications.partials.house-review-row', [
                                    'application' => $application,
                                    'binaryOptions' => $binaryOptions,
                                    'houseStatusOptions' => $houseStatusOptions,
                                ])
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-16 text-center">
                                        <p class="text-sm font-bold text-slate-500">Tidak ada applicant yang sesuai dengan filter saat ini.</p>
                                        <p class="mt-2 text-sm text-slate-400">Coba ubah pencarian atau tampilkan semua data.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($applications->isNotEmpty())
                    <div class="flex justify-end border-t border-slate-100 bg-slate-50/70 px-6 py-4">
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-black text-white shadow-lg shadow-primary/20 transition hover:bg-blue-700"
                        >
                            <span class="material-symbols-outlined text-base">save</span>
                            Simpan Perubahan Halaman Ini
                        </button>
                    </div>
                @endif
            </form>

            @if ($applications->hasPages())
                <div class="border-t border-slate-100 px-6 py-4">
                    {{ $applications->links() }}
                </div>
            @endif
        </section>
    </div>

    <x-admin.footer />
</main>

@push('scripts')
<script>
(() => {
    const form = document.querySelector('[data-raw-completion-form]');
    if (!form || !window.localStorage) {
        return;
    }

    const noticeWasSuccessful = @json(($notice['type'] ?? null) === 'success');
    const storageKey = `kipk:admin:raw-completion:${window.location.pathname}:${window.location.search}`;
    const statusNode = document.querySelector('[data-draft-status]');
    const fields = Array.from(form.querySelectorAll('input[name^="applications["], select[name^="applications["], textarea[name^="applications["]'));

    const setStatus = (message) => {
        if (statusNode) {
            statusNode.textContent = message;
        }
    };

    const readValues = () => Object.fromEntries(fields.map((field) => [field.name, field.value]));

    const saveDraft = () => {
        localStorage.setItem(storageKey, JSON.stringify({
            saved_at: new Date().toISOString(),
            values: readValues(),
        }));
        setStatus('Draft tersimpan otomatis di browser.');
    };

    const restoreDraft = () => {
        const raw = localStorage.getItem(storageKey);
        if (!raw) {
            return;
        }

        try {
            const draft = JSON.parse(raw);
            const values = draft.values || {};
            let restored = 0;

            fields.forEach((field) => {
                if (!Object.prototype.hasOwnProperty.call(values, field.name)) {
                    return;
                }
                if (field.value === values[field.name]) {
                    return;
                }
                field.value = values[field.name];
                field.classList.add('ring-2', 'ring-amber-300');
                restored += 1;
            });

            if (restored > 0) {
                setStatus(`Draft lokal dipulihkan untuk ${restored} input. Klik simpan untuk menyimpan ke database.`);
            }
        } catch (error) {
            localStorage.removeItem(storageKey);
        }
    };

    if (noticeWasSuccessful) {
        localStorage.removeItem(storageKey);
    } else {
        restoreDraft();
    }

    fields.forEach((field) => {
        field.addEventListener('change', saveDraft);
        field.addEventListener('input', saveDraft);
    });
})();
</script>
@endpush
@endsection
