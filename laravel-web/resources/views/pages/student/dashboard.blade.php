@extends('layouts.portal')

@section('title', 'Dashboard Mahasiswa | KIP-K UNAIR')
@section('description', 'Dashboard mahasiswa untuk memantau pengajuan KIP-K Universitas Airlangga')

@php
    $statusClasses = [
        'Submitted' => 'bg-secondary-container text-on-secondary-container border-t-2 border-secondary',
        'Verified' => 'bg-emerald-100 text-emerald-700 border-t-2 border-emerald-500',
        'Rejected' => 'bg-error-container text-on-error-container border-t-2 border-error',
    ];

    $statusLabels = [
        'Submitted' => 'Menunggu',
        'Verified' => 'Lolos',
        'Rejected' => 'Indikasi',
    ];

    $latestStatus = $summary['latest_status'] ?? 'Belum ada';
    $latestStatusClass = $statusClasses[$latestStatus] ?? 'bg-slate-100 text-slate-600 border-t-2 border-slate-300';
    $studentName = $student->name ?? 'Mahasiswa';
    $notice = session('student_notice');
    $latestApplication = $summary['latest_application'] ?? null;
    $latestStatusLabel = $statusLabels[$latestStatus] ?? $latestStatus;
    $latestDecisionReady = (bool) ($summary['latest_decision_ready'] ?? false);
    $latestDecisionStatus = $summary['latest_decision_status'] ?? null;
    $latestDecisionAt = $summary['latest_decision_at'] ?? null;

    $statusFilterOptions = [
        ['value' => '', 'label' => 'Semua'],
        ['value' => 'Submitted', 'label' => 'Menunggu'],
        ['value' => 'Verified', 'label' => 'Lolos'],
        ['value' => 'Rejected', 'label' => 'Indikasi'],
    ];

    $statusHeadline = match ($latestStatus) {
        'Verified' => 'Pengajuan terbaru Anda sudah dinyatakan lolos.',
        'Rejected' => 'Pengajuan terbaru Anda masuk kategori indikasi.',
        'Submitted' => 'Pengajuan terbaru Anda sedang menunggu keputusan admin.',
        default => 'Belum ada pengajuan aktif pada akun ini.',
    };

    $statusDescription = match ($latestStatus) {
        'Verified' => 'Pantau halaman detail untuk melihat keputusan final dan catatan admin jika tersedia.',
        'Rejected' => 'Silakan buka detail pengajuan untuk melihat catatan admin dan dokumen yang sudah Anda kirim.',
        'Submitted' => 'Sistem sudah menerima data mentah Anda. Admin akan meninjau dan memberi keputusan final setelah melihat keseluruhan data.',
        default => 'Mulai pengajuan pertama Anda untuk masuk ke alur verifikasi KIP-K UNAIR.',
    };

    $decisionBannerClasses = match ($latestStatus) {
        'Verified' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'Rejected' => 'border-red-200 bg-error-container text-on-error-container',
        'Submitted' => 'border-yellow-200 bg-secondary-fixed text-on-secondary-fixed',
        default => 'border-slate-200 bg-surface-container-low text-slate-700',
    };

    $decisionNotificationClasses = match ($latestDecisionStatus) {
        'Verified' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'Rejected' => 'border-red-200 bg-error-container text-on-error-container',
        default => 'border-slate-200 bg-white text-slate-700',
    };

    $statCards = [
        ['label' => 'Total Pengajuan', 'value' => $summary['total'], 'icon' => 'article', 'tone' => 'bg-blue-50 text-blue-700'],
        ['label' => 'Menunggu', 'value' => $summary['submitted'], 'icon' => 'schedule', 'tone' => 'bg-yellow-50 text-yellow-700'],
        ['label' => 'Lolos', 'value' => $summary['verified'], 'icon' => 'check_circle', 'tone' => 'bg-emerald-50 text-emerald-700'],
        ['label' => 'Dokumen', 'value' => $summary['has_documents'] ? 'Ada' : 'Belum', 'icon' => 'picture_as_pdf', 'tone' => 'bg-slate-100 text-slate-700'],
    ];
@endphp

@section('content')
@include('pages.student.partials.topbar', ['student' => $student])

<main class="mx-auto max-w-7xl px-6 pb-12 pt-24">
    @if($notice)
        <div class="mb-6 rounded-2xl border px-4 py-3 text-sm font-semibold {{ ($notice['type'] ?? 'success') === 'error' ? 'border-red-200 bg-error-container text-on-error-container' : 'border-emerald-100 bg-emerald-50 text-emerald-700' }}">
            <p class="font-black uppercase tracking-[0.18em]">{{ $notice['title'] ?? 'Informasi' }}</p>
            <p class="mt-1">{{ $notice['message'] ?? '' }}</p>
        </div>
    @endif

    @if ($latestDecisionReady && $latestApplication)
        <div class="mb-6 rounded-2xl border px-5 py-4 shadow-sm {{ $decisionNotificationClasses }}">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined mt-0.5">notifications_active</span>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] opacity-80">Notifikasi Keputusan</p>
                        <p class="mt-1 text-sm font-black">
                            Admin sudah memberi keputusan final: {{ $statusLabels[$latestDecisionStatus] ?? $latestDecisionStatus }}
                        </p>
                        <p class="mt-1 text-sm opacity-90">
                            {{ $latestDecisionAt ? 'Diputuskan pada '.$latestDecisionAt->translatedFormat('d M Y H:i') : 'Silakan buka detail pengajuan terbaru untuk melihat hasil lengkap.' }}
                        </p>
                    </div>
                </div>

                <a href="{{ route('student.applications.show', $latestApplication->id) }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-current/15 bg-white/40 px-4 py-3 text-sm font-black transition hover:bg-white/60">
                    <span class="material-symbols-outlined text-base">visibility</span>
                    Lihat Keputusan
                </a>
            </div>
        </div>
    @endif

    <section class="relative mb-10 overflow-hidden rounded-xl shadow-2xl">
        <div class="absolute inset-0 z-0">
            <img
                src="https://lh3.googleusercontent.com/aida-public/AB6AXuBVts2doSzTs3tFdAYgJGujqxXPTwCKQZ9GUmDfBCwtE5sLOXD8DxOjw-V5F1ri4WkSNKDs2K870BunAT7HfND0UCIKcH-LO4D7P58KeGI1084B-r8hkt08G64pzeDIdem2Y0H3yg433LRaLAZHc0MEeX-gkdQpStVkOTrlJ4vzAfY1S019f1QGwFTG0aDcT3ImqWRStDy1RUWqushrU9od7hTW5tRY12nJs5MBwkZKuPm-8xwXpNZe1FQqPCkEaFgV8flAbQjLWQs"
                alt="UNAIR Campus"
                class="h-full w-full object-cover"
            />
            <div class="absolute inset-0 bg-gradient-to-r from-primary/90 to-primary/45"></div>
        </div>

        <div class="relative z-10 flex flex-col items-start justify-between gap-8 p-8 md:flex-row md:items-center md:p-12">
            <div class="max-w-xl">
                <span class="mb-2 block text-[10px] font-bold uppercase tracking-widest text-secondary">Portal Mahasiswa KIP-K</span>
                <h1 class="font-headline text-3xl font-extrabold tracking-tight text-white md:text-4xl">Ajukan data sekali, lalu pantau hasilnya dengan tenang.</h1>
                <p class="mt-4 text-sm leading-relaxed text-white/80">
                    Lengkapi data pengajuan KIP-K, unggah dokumen PDF, lalu tunggu hasil rekomendasi sistem dan keputusan final admin di portal ini.
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="{{ route('student.applications.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-secondary px-8 py-3.5 font-bold text-on-secondary-fixed shadow-lg shadow-secondary/20 transition-all hover:-translate-y-0.5 active:scale-95">
                        <span class="material-symbols-outlined">add</span>
                        Ajukan KIP-K
                    </a>
                    @if ($latestApplication)
                        <a href="{{ route('student.applications.show', $latestApplication->id) }}" class="inline-flex items-center gap-2 rounded-lg border border-white/25 bg-white/10 px-6 py-3.5 text-sm font-bold text-white backdrop-blur-md transition-all hover:bg-white/20">
                            <span class="material-symbols-outlined">visibility</span>
                            Lihat Hasil Terbaru
                        </a>
                    @endif
                </div>
            </div>

            <div class="w-full rounded-xl border border-white/30 bg-white/20 p-6 backdrop-blur-md md:w-80">
                <h3 class="flex items-center gap-2 text-lg font-bold text-white">
                    <span class="material-symbols-outlined">assignment_turned_in</span>
                    Ringkasan Status
                </h3>
                <div class="mt-4 space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-white/70">Pengajuan Aktif</span>
                        <span class="text-sm font-medium text-white">{{ $summary['total'] }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-white/70">Status Terbaru</span>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold uppercase {{ $latestStatusClass }}">{{ $latestStatusLabel }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-white/70">Dokumen Tersedia</span>
                        <span class="text-sm font-medium text-white">{{ $summary['has_documents'] ? 'Ya' : 'Belum' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-10 rounded-2xl border px-5 py-5 shadow-sm {{ $decisionBannerClasses }}">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.2em] opacity-80">Hasil Terbaru</p>
                <h2 class="mt-2 text-xl font-black">{{ $statusHeadline }}</h2>
                <p class="mt-2 max-w-3xl text-sm leading-7 opacity-90">{{ $statusDescription }}</p>
                @if ($latestApplication && $latestApplication->admin_decision_note)
                    <p class="mt-3 text-sm font-medium leading-7 opacity-90">
                        Catatan admin: {{ $latestApplication->admin_decision_note }}
                    </p>
                @endif
            </div>

            @if ($latestApplication)
                <div class="flex flex-col gap-2 text-sm font-semibold md:items-end">
                    <span>Status: {{ $latestStatusLabel }}</span>
                    <span>
                        Dikirim: {{ $latestApplication->created_at?->translatedFormat('d M Y H:i') ?? '-' }}
                    </span>
                    <a href="{{ route('student.applications.show', $latestApplication->id) }}" class="mt-1 inline-flex items-center gap-2 rounded-xl border border-current/15 bg-white/40 px-4 py-3 text-sm font-black transition hover:bg-white/60">
                        <span class="material-symbols-outlined text-base">arrow_forward</span>
                        Buka Detail Pengajuan
                    </a>
                </div>
            @endif
        </div>
    </section>

    <section class="mb-10 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($statCards as $card)
            <div class="rounded-xl bg-white p-5 shadow-sm">
                <div class="mb-4 flex items-start justify-between">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl {{ $card['tone'] }}">
                        <span class="material-symbols-outlined">{{ $card['icon'] }}</span>
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">{{ $card['label'] }}</span>
                </div>
                <div>
                    <p class="text-3xl font-black text-on-surface">{{ is_numeric($card['value']) ? number_format((int) $card['value']) : $card['value'] }}</p>
                </div>
            </div>
        @endforeach
    </section>

    <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
        <section class="lg:col-span-2">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-headline text-xl font-extrabold tracking-tight text-on-surface">Riwayat Pengajuan</h2>
                <form method="GET" action="{{ route('student.dashboard') }}" class="relative">
                    @if ($filters['status'])
                        <input type="hidden" name="status" value="{{ $filters['status'] }}" />
                    @endif
                    <span class="material-symbols-outlined absolute inset-y-0 left-3 flex items-center text-sm text-slate-400">search</span>
                    <input
                        type="text"
                        name="q"
                        value="{{ $filters['q'] }}"
                        placeholder="Cari ID pengajuan..."
                        class="w-52 rounded-lg bg-surface px-4 py-2 pl-9 text-sm shadow-sm outline-none transition-all focus:ring-2 focus:ring-primary/20"
                    />
                </form>
            </div>

            <div class="mb-4 flex flex-wrap gap-2">
                @foreach ($statusFilterOptions as $option)
                    @php
                        $active = ($filters['status'] ?? '') === $option['value'];
                        $params = array_filter([
                            'status' => $option['value'] !== '' ? $option['value'] : null,
                            'q' => $filters['q'] ?: null,
                        ]);
                    @endphp
                    <a
                        href="{{ route('student.dashboard', $params) }}"
                        class="{{ $active ? 'border-primary bg-primary-container text-primary' : 'border-slate-200 bg-white text-slate-500 hover:border-primary/30 hover:text-primary' }} inline-flex items-center rounded-full border px-4 py-2 text-xs font-black uppercase tracking-[0.18em] transition"
                    >
                        {{ $option['label'] }}
                    </a>
                @endforeach
            </div>

            <div class="overflow-hidden rounded-xl bg-surface shadow-sm">
                @if($applications->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-left">
                            <thead>
                                <tr class="border-b border-slate-100 bg-surface-container-low">
                                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-500">ID Pengajuan</th>
                                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-500">Tanggal Kirim</th>
                                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-500">Status</th>
                                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-500">Dokumen</th>
                                    <th class="px-6 py-4 text-center text-[10px] font-bold uppercase tracking-widest text-slate-500">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                @foreach($applications as $application)
                                    @php
                                        $statusClass = $statusClasses[$application->status] ?? 'bg-slate-100 text-slate-600 border-t-2 border-slate-300';
                                        $displayId = 'KIPK-'.($application->created_at?->format('Y') ?? now()->format('Y')).'-'.str_pad((string) $application->id, 3, '0', STR_PAD_LEFT);
                                        $canEdit = $application->canBeRevisedByStudent();
                                    @endphp
                                    <tr class="transition-colors hover:bg-slate-50/60">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="flex h-8 w-8 items-center justify-center rounded bg-blue-50 text-blue-600">
                                                    <span class="material-symbols-outlined text-sm">article</span>
                                                </div>
                                                <span class="text-sm font-bold text-on-surface">{{ $displayId }}</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-on-surface-variant">{{ $application->created_at?->translatedFormat('d M Y') }}</td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex w-fit items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold uppercase {{ $statusClass }}">
                                                {{ $statusLabels[$application->status] ?? $application->status }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-semibold text-on-surface">
                                            {{ $application->hasSubmittedPdf() ? 'Tersedia' : 'Belum ada' }}
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex justify-center gap-2">
                                                @if($application->submitted_pdf_path)
                                                    <a
                                                        href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($application->submitted_pdf_path) }}"
                                                        target="_blank"
                                                        class="rounded p-2 text-primary transition-colors hover:bg-primary-container"
                                                        title="Lihat PDF"
                                                    >
                                                        <span class="material-symbols-outlined">picture_as_pdf</span>
                                                    </a>
                                                @else
                                                    <span class="rounded p-2 text-slate-300" title="Belum ada PDF">
                                                        <span class="material-symbols-outlined">picture_as_pdf</span>
                                                    </span>
                                                @endif

                                                <a
                                                    href="{{ route('student.applications.show', $application->id) }}"
                                                    class="rounded p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-primary"
                                                    title="Lihat detail pengajuan"
                                                >
                                                    <span class="material-symbols-outlined">visibility</span>
                                                </a>

                                                @if($canEdit)
                                                    <a
                                                        href="{{ route('student.applications.edit', $application->id) }}"
                                                        class="rounded p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-primary"
                                                        title="Revisi pengajuan"
                                                    >
                                                        <span class="material-symbols-outlined">edit_square</span>
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-slate-100 px-6 py-4">
                        {{ $applications->links() }}
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center px-6 py-20 text-center">
                        <div class="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-surface-container text-slate-300">
                            <span class="material-symbols-outlined text-4xl">folder_open</span>
                        </div>
                        <h3 class="text-lg font-bold text-on-surface">Belum ada pengajuan</h3>
                        <p class="mt-2 max-w-xs text-sm text-on-surface-variant">Anda belum memiliki riwayat pengajuan KIP-K pada akun ini. Mulai dari form pengajuan, unggah satu PDF, lalu pantau hasilnya dari dashboard ini.</p>
                        <div class="mt-6 grid max-w-xl gap-3 md:grid-cols-3">
                            <div class="rounded-2xl bg-surface-container-low px-4 py-4 text-left">
                                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Langkah 1</p>
                                <p class="mt-2 text-sm font-black text-on-surface">Isi data mentah</p>
                            </div>
                            <div class="rounded-2xl bg-surface-container-low px-4 py-4 text-left">
                                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Langkah 2</p>
                                <p class="mt-2 text-sm font-black text-on-surface">Unggah PDF pendukung</p>
                            </div>
                            <div class="rounded-2xl bg-surface-container-low px-4 py-4 text-left">
                                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Langkah 3</p>
                                <p class="mt-2 text-sm font-black text-on-surface">Tunggu hasil admin</p>
                            </div>
                        </div>
                        <a href="{{ route('student.applications.create') }}" class="mt-6 inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-3 text-sm font-bold text-white transition-colors hover:bg-blue-700">
                            <span class="material-symbols-outlined text-sm">add</span>
                            Buat Pengajuan Pertama
                        </a>
                    </div>
                @endif
            </div>
        </section>

        <aside class="flex flex-col gap-6">
            <section class="rounded-xl border-t-4 border-secondary bg-white p-6 shadow-sm">
                <div class="mb-6 flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary-container text-primary">
                        <span class="material-symbols-outlined">person</span>
                    </div>
                    <div>
                        <h4 class="font-bold text-on-surface">Profil Mahasiswa</h4>
                        <p class="text-[10px] uppercase tracking-widest text-on-surface-variant">Ringkasan Portal</p>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="rounded-lg bg-surface-container-low p-3">
                        <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">Nama</p>
                        <p class="mt-1 text-sm font-semibold text-on-surface">{{ $studentName }}</p>
                    </div>
                    <div class="rounded-lg bg-surface-container-low p-3">
                        <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">Email</p>
                        <p class="mt-1 text-sm font-semibold text-on-surface">{{ $student->email }}</p>
                    </div>
                    <div class="rounded-lg bg-surface-container-low p-3">
                        <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">Role</p>
                        <p class="mt-1 text-sm font-semibold capitalize text-on-surface">Mahasiswa</p>
                    </div>
                </div>
            </section>

            <section class="rounded-xl bg-tertiary p-6 text-white shadow-lg">
                <h4 class="text-lg font-bold">Butuh bantuan?</h4>
                <p class="mt-2 text-sm text-white/70">
                    Gunakan panel ini sebagai pengingat alur mahasiswa: submit data mentah, tunggu review admin, lalu cek kembali detail pengajuan setelah ada keputusan.
                </p>
                <div class="mt-6 space-y-3">
                    <div class="rounded-lg bg-white/10 px-4 py-3">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-white/60">Panduan Cepat</p>
                        <p class="mt-2 text-sm font-medium text-white">Simpan revisi hanya selama admin belum memberi keputusan final.</p>
                    </div>
                    <div class="rounded-lg bg-white/10 px-4 py-3">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-white/60">Dokumen</p>
                        <p class="mt-2 text-sm font-medium text-white">Pastikan PDF pendukung Anda selalu memuat bukti terbaru dan terbaca jelas.</p>
                    </div>
                </div>
                <div class="mt-6 grid grid-cols-2 gap-3">
                    <a href="{{ route('student.applications.create') }}" class="rounded-lg bg-white/10 py-2 text-center text-xs font-bold transition-all hover:bg-white/20">Form Pengajuan</a>
                    @if ($latestApplication)
                        <a href="{{ route('student.applications.show', $latestApplication->id) }}" class="rounded-lg bg-white/10 py-2 text-center text-xs font-bold transition-all hover:bg-white/20">Hasil Terbaru</a>
                    @else
                        <button type="button" class="rounded-lg bg-white/10 py-2 text-xs font-bold transition-all hover:bg-white/20">Menunggu Data</button>
                    @endif
                </div>
            </section>
        </aside>
    </div>
</main>

@include('pages.student.partials.footer')
@endsection
