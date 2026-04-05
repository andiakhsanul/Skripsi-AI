@extends('layouts.portal')

@section('title', 'Status Pengajuan | KIP-K UNAIR')
@section('description', 'Halaman mahasiswa untuk melihat status dan hasil pengajuan KIP-K')

@php
    $notice = session('student_notice');
    $snapshot = $application->modelSnapshot;
    $applicantName = $application->applicant_name ?: ($student->name ?? 'Mahasiswa');
    $applicantEmail = $application->applicant_email ?: ($student->email ?? '-');
    $displayId = 'KIPK-'.($application->created_at?->format('Y') ?? now()->format('Y')).'-'.str_pad((string) $application->id, 3, '0', STR_PAD_LEFT);
    $lastUpdatedAt = $application->admin_decided_at ?? $application->updated_at ?? $application->created_at;

    $statusLabels = [
        'Submitted' => 'Menunggu Verifikasi',
        'Verified' => 'Lolos / Terverifikasi',
        'Rejected' => 'Indikasi / Ditolak',
    ];

    $statusClasses = [
        'Submitted' => 'bg-secondary-container text-on-secondary-container border border-secondary/30',
        'Verified' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
        'Rejected' => 'bg-error-container text-on-error-container border border-red-200',
    ];

    $statusLabel = $statusLabels[$application->status] ?? $application->status;
    $statusClass = $statusClasses[$application->status] ?? 'bg-slate-100 text-slate-700 border border-slate-200';
    $decisionStatus = $application->admin_decision ?? $application->status;
    $decisionVariant = match ($decisionStatus) {
        'Verified' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'Rejected' => 'border-red-200 bg-error-container text-on-error-container',
        default => 'border-yellow-200 bg-secondary-fixed text-on-secondary-fixed',
    };

    $cards = [
        [
            'label' => 'Dokumen PDF',
            'value' => $application->submitted_pdf_original_name ?? 'Tidak tersedia',
            'note' => $application->submitted_pdf_uploaded_at?->format('d M Y H:i') ? 'Diunggah pada '.$application->submitted_pdf_uploaded_at->format('d M Y H:i') : 'Belum ada waktu unggah',
            'icon' => 'picture_as_pdf',
            'tone' => 'bg-primary-container text-primary',
            'border' => 'border-primary',
        ],
        [
            'label' => 'Tanggal Pengajuan',
            'value' => $application->created_at?->translatedFormat('d F Y') ?? '-',
            'note' => $application->submission_source === 'online_student' ? 'Dikirim langsung dari portal mahasiswa.' : 'Berasal dari import admin offline.',
            'icon' => 'event_available',
            'tone' => 'bg-secondary-container text-on-secondary-container',
            'border' => 'border-secondary',
        ],
        [
            'label' => 'Status Saat Ini',
            'value' => $statusLabel,
            'note' => $application->admin_decision ? 'Keputusan final admin sudah tersedia.' : 'Masih menunggu keputusan akhir admin.',
            'icon' => 'fact_check',
            'tone' => $application->status === 'Rejected' ? 'bg-error-container text-error' : 'bg-primary-container text-primary',
            'border' => $application->status === 'Rejected' ? 'border-error' : 'border-emerald-500',
        ],
    ];

    $rawSections = [
        [
            'title' => 'Bantuan Sosial',
            'icon' => 'badge',
            'items' => [
                ['label' => 'KIP', 'value' => (int) $application->kip === 1 ? 'Ya' : 'Tidak'],
                ['label' => 'PKH', 'value' => (int) $application->pkh === 1 ? 'Ya' : 'Tidak'],
                ['label' => 'KKS', 'value' => (int) $application->kks === 1 ? 'Ya' : 'Tidak'],
                ['label' => 'DTKS', 'value' => (int) $application->dtks === 1 ? 'Ya' : 'Tidak'],
                ['label' => 'SKTM', 'value' => (int) $application->sktm === 1 ? 'Ya' : 'Tidak'],
            ],
        ],
        [
            'title' => 'Ekonomi Keluarga',
            'icon' => 'payments',
            'items' => [
                ['label' => 'Penghasilan Ayah', 'value' => 'Rp '.number_format((int) $application->penghasilan_ayah_rupiah, 0, ',', '.')],
                ['label' => 'Penghasilan Ibu', 'value' => 'Rp '.number_format((int) $application->penghasilan_ibu_rupiah, 0, ',', '.')],
                ['label' => 'Penghasilan Gabungan', 'value' => 'Rp '.number_format((int) $application->penghasilan_gabungan_rupiah, 0, ',', '.')],
                ['label' => 'Jumlah Tanggungan', 'value' => $application->jumlah_tanggungan_raw ?? '-'],
                ['label' => 'Anak Ke-', 'value' => $application->anak_ke_raw ?? '-'],
            ],
        ],
        [
            'title' => 'Kondisi Rumah Tangga',
            'icon' => 'home',
            'items' => [
                ['label' => 'Status Orang Tua', 'value' => $application->status_orangtua_text ?? '-'],
                ['label' => 'Status Rumah', 'value' => $application->status_rumah_text ?? '-'],
                ['label' => 'Daya Listrik', 'value' => $application->daya_listrik_text ?? '-'],
            ],
        ],
    ];
@endphp

@section('content')
@include('pages.student.partials.topbar', ['student' => $student])

<main class="mx-auto max-w-6xl px-6 pb-16 pt-24">
    @if ($notice)
        <div class="mb-8 rounded-2xl border px-5 py-4 text-sm font-semibold {{ ($notice['type'] ?? 'success') === 'error' ? 'border-red-200 bg-error-container text-on-error-container' : 'border-emerald-100 bg-emerald-50 text-emerald-700' }}">
            <p class="font-black uppercase tracking-[0.18em]">{{ $notice['title'] ?? 'Informasi' }}</p>
            <p class="mt-1">{{ $notice['message'] ?? '' }}</p>
        </div>
    @endif

    <section class="mb-8 rounded-3xl bg-white p-8 shadow-lg">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-2xl">
                <a href="{{ route('student.dashboard') }}" class="inline-flex items-center gap-1 text-sm font-black text-primary transition-all hover:gap-2">
                    <span class="material-symbols-outlined text-sm">arrow_back</span>
                    Kembali ke Dashboard
                </a>
                <span class="mt-5 block text-[10px] font-black uppercase tracking-[0.22em] text-primary">Detail Pengajuan</span>
                <h1 class="mt-2 text-3xl font-black tracking-tight text-on-surface">Pengajuan {{ $displayId }}</h1>
                <p class="mt-3 text-sm leading-7 text-slate-500">
                    Anda hanya perlu memantau status pengajuan di halaman ini. Jika admin sudah memberi keputusan, hasil akhir akan tampil otomatis.
                </p>

                <div class="mt-5 inline-flex flex-col gap-1 rounded-2xl bg-surface-container-low px-5 py-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Pemohon</p>
                    <p class="text-base font-black text-on-surface">{{ $applicantName }}</p>
                    <p class="text-sm font-medium text-slate-500">{{ $applicantEmail }}</p>
                </div>
            </div>

            <div class="flex flex-col items-start gap-2 lg:items-end">
                <div class="rounded-full px-4 py-2 text-xs font-black uppercase tracking-[0.18em] {{ $statusClass }}">
                    {{ $statusLabel }}
                </div>
                <span class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">
                    Terakhir diperbarui: {{ $lastUpdatedAt?->translatedFormat('d M Y H:i') ?? '-' }}
                </span>
                @if ($canEdit ?? false)
                    <a href="{{ route('student.applications.edit', $application->id) }}" class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-3 text-sm font-black text-slate-600 transition hover:bg-slate-50">
                        <span class="material-symbols-outlined text-base">edit_square</span>
                        Revisi Pengajuan
                    </a>
                @endif
            </div>
        </div>
    </section>

    <section class="mb-8 grid gap-4 md:grid-cols-3">
        @foreach ($cards as $card)
            <div class="rounded-2xl border-t-4 {{ $card['border'] }} bg-white p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl {{ $card['tone'] }}">
                        <span class="material-symbols-outlined">{{ $card['icon'] }}</span>
                    </div>
                    <span class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">{{ $card['label'] }}</span>
                </div>
                <p class="mt-5 text-lg font-black leading-6 text-on-surface">{{ $card['value'] }}</p>
                <p class="mt-2 text-xs font-medium leading-5 text-slate-500">{{ $card['note'] }}</p>
            </div>
        @endforeach
    </section>

    <section class="mb-8 rounded-3xl border-l-8 px-6 py-6 shadow-sm {{ $decisionVariant }} {{ $decisionStatus === 'Verified' ? 'border-l-emerald-500' : ($decisionStatus === 'Rejected' ? 'border-l-error' : 'border-l-secondary') }}">
        <div class="flex gap-4">
            <span class="material-symbols-outlined text-3xl">
                {{ $decisionStatus === 'Verified' ? 'verified' : ($decisionStatus === 'Rejected' ? 'gpp_maybe' : 'schedule') }}
            </span>
            <div>
                <h2 class="text-sm font-black uppercase tracking-[0.18em]">
                    {{ $application->admin_decision ? 'Keputusan Final Admin' : 'Status Review Saat Ini' }}
                </h2>
                <p class="mt-2 text-sm leading-7">
                    @if ($application->admin_decision)
                        Hasil akhir pengajuan Anda saat ini adalah
                        <span class="font-black">{{ $statusLabels[$application->admin_decision] ?? $application->admin_decision }}</span>.
                        {{ $application->admin_decision_note ? 'Catatan admin tersedia di bawah ini.' : 'Buka riwayat dan ringkasan di bawah untuk detail lengkap.' }}
                    @else
                        Pengajuan Anda masih dalam tahap peninjauan. Rekomendasi sistem sudah tersedia, tetapi keputusan akhir tetap menunggu admin.
                    @endif
                </p>
            </div>
        </div>
    </section>

    <section class="mb-8 grid gap-8 lg:grid-cols-[minmax(0,1.2fr)_360px]">
        <div class="rounded-3xl bg-white p-8 shadow-lg">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-container text-primary">
                    <span class="material-symbols-outlined">timeline</span>
                </div>
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Alur Hasil</p>
                    <h2 class="text-xl font-black text-on-surface">Status dan keputusan</h2>
                </div>
            </div>

            <div class="mt-6 grid gap-5 md:grid-cols-2">
                <div class="rounded-2xl bg-surface-container p-5">
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Rekomendasi Sistem</p>
                    <p class="mt-2 text-lg font-black text-on-surface">{{ $snapshot?->final_recommendation ?? 'Belum diproses' }}</p>
                    <p class="mt-2 text-sm text-slate-500">CatBoost: {{ $snapshot?->catboost_label ?? '-' }} · Naive Bayes: {{ $snapshot?->naive_bayes_label ?? '-' }}</p>
                </div>
                <div class="rounded-2xl bg-surface-container p-5">
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Keputusan Admin</p>
                    <p class="mt-2 text-lg font-black text-on-surface">{{ $application->admin_decision ? ($statusLabels[$application->admin_decision] ?? $application->admin_decision) : 'Belum ada keputusan final' }}</p>
                    <p class="mt-2 text-sm text-slate-500">{{ $application->admin_decided_at ? 'Diputuskan pada '.$application->admin_decided_at->format('d M Y H:i') : 'Masih menunggu review admin.' }}</p>
                </div>
            </div>
        </div>

        <aside class="space-y-6">
            <div class="rounded-3xl bg-white p-6 shadow-lg">
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Dokumen Pengajuan</p>
                @if ($documentUrl)
                    <a href="{{ $documentUrl }}" target="_blank" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-primary px-4 py-4 text-sm font-black text-white shadow-lg shadow-primary/20 transition hover:bg-blue-700">
                        <span class="material-symbols-outlined text-sm">description</span>
                        Buka PDF Pendukung
                    </a>
                @else
                    <p class="mt-4 text-sm text-slate-500">Dokumen belum tersedia.</p>
                @endif
            </div>

            <div class="rounded-3xl bg-white p-6 shadow-lg">
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Riwayat Status</p>
                <div class="mt-5 space-y-4">
                    @forelse ($application->logs as $log)
                        <div class="flex gap-3">
                            <div class="mt-1 h-3 w-3 rounded-full {{ $log->to_status === 'Rejected' ? 'bg-error' : ($log->to_status === 'Verified' ? 'bg-emerald-500' : 'bg-primary') }}"></div>
                            <div>
                                <p class="text-sm font-bold text-on-surface">{{ $statusLabels[$log->to_status] ?? $log->to_status }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $log->created_at?->format('d M Y H:i') ?? '-' }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Belum ada riwayat status.</p>
                    @endforelse
                </div>

                @if ($application->admin_decision_note)
                    <div class="mt-6 rounded-2xl border border-slate-100 bg-surface-container-low p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Catatan Admin</p>
                        <p class="mt-2 text-sm leading-7 text-slate-600">{{ $application->admin_decision_note }}</p>
                    </div>
                @endif
            </div>
        </aside>
    </section>

    <section class="rounded-3xl bg-white p-8 shadow-lg">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Data yang Anda Kirim</p>
                <h2 class="mt-1 text-xl font-black text-on-surface">Ringkasan data mentah</h2>
            </div>
            <a href="{{ route('student.dashboard') }}" class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50">
                <span class="material-symbols-outlined text-sm">arrow_back</span>
                Kembali ke Dashboard
            </a>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            @foreach ($rawSections as $section)
                <article class="rounded-2xl border border-slate-100 bg-surface-container-low p-5 shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white text-primary shadow-sm">
                            <span class="material-symbols-outlined">{{ $section['icon'] }}</span>
                        </div>
                        <h3 class="text-sm font-black text-on-surface">{{ $section['title'] }}</h3>
                    </div>
                    <div class="mt-4 space-y-3">
                        @foreach ($section['items'] as $item)
                            <div class="rounded-xl bg-white px-4 py-3">
                                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">{{ $item['label'] }}</p>
                                <p class="mt-1 text-sm font-semibold text-on-surface">{{ $item['value'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </article>
            @endforeach
        </div>
    </section>
</main>

@include('pages.student.partials.footer')
@endsection
