@extends('layouts.portal')

@section('title', 'Dashboard Mahasiswa | KIP-K UNAIR')
@section('description', 'Dashboard mahasiswa untuk memantau pengajuan KIP-K Universitas Airlangga')

@php
    $studentName = $student->name ?? 'Mahasiswa';
    $initials = collect(preg_split('/\s+/', trim($studentName)) ?: [])
        ->filter()
        ->map(fn ($part) => strtoupper(mb_substr($part, 0, 1)))
        ->take(2)
        ->implode('');

    $statusClasses = [
        'Submitted' => 'bg-secondary-container text-on-secondary-container border-t-2 border-secondary',
        'Verified' => 'bg-emerald-100 text-emerald-700 border-t-2 border-emerald-500',
        'Rejected' => 'bg-error-container text-on-error-container border-t-2 border-error',
    ];

    $latestStatus = $summary['latest_status'] ?? 'Belum ada';
    $latestStatusClass = $statusClasses[$latestStatus] ?? 'bg-slate-100 text-slate-600 border-t-2 border-slate-300';

    $statCards = [
        ['label' => 'Total Pengajuan', 'value' => $summary['total'], 'icon' => 'article', 'tone' => 'bg-blue-50 text-blue-700'],
        ['label' => 'Diproses', 'value' => $summary['submitted'], 'icon' => 'schedule', 'tone' => 'bg-yellow-50 text-yellow-700'],
        ['label' => 'Lolos', 'value' => $summary['verified'], 'icon' => 'check_circle', 'tone' => 'bg-emerald-50 text-emerald-700'],
        ['label' => 'Indikasi', 'value' => $summary['rejected'], 'icon' => 'warning', 'tone' => 'bg-error-container text-error'],
    ];
@endphp

@section('content')
<header class="fixed top-0 z-50 w-full border-b border-slate-100 bg-white/80 shadow-sm backdrop-blur-md">
    <div class="mx-auto flex h-16 w-full max-w-screen-2xl items-center justify-between px-6">
        <div class="flex items-center gap-8">
            <a href="{{ route('student.dashboard') }}" class="text-xl font-extrabold tracking-tighter text-blue-800">KIP-K UNAIR</a>
            <nav class="hidden items-center gap-6 text-sm font-medium tracking-tight md:flex">
                <a href="{{ route('student.dashboard') }}" class="border-b-2 border-yellow-500 pb-1 text-blue-700">Dashboard</a>
                <span class="cursor-not-allowed text-slate-400">Pengajuan</span>
                <span class="cursor-not-allowed text-slate-400">Dokumen</span>
                <span class="cursor-not-allowed text-slate-400">Pesan</span>
            </nav>
        </div>

        <div class="flex items-center gap-4">
            <div class="hidden text-right md:block">
                <p class="text-sm font-bold text-on-surface">{{ $studentName }}</p>
                <p class="text-[11px] font-medium text-slate-400">{{ $student->email }}</p>
            </div>
            <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 border-white bg-primary-container font-bold text-primary shadow-sm">
                {{ $initials !== '' ? $initials : 'MH' }}
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-lg p-2 text-slate-500 transition-colors hover:bg-slate-50 hover:text-error" title="Logout">
                    <span class="material-symbols-outlined block">logout</span>
                </button>
            </form>
        </div>
    </div>
</header>

<main class="mx-auto max-w-7xl px-6 pb-12 pt-24">
    @if(session('status'))
        <div class="mb-6 rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
            {{ session('status') }}
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
                <span class="mb-2 block text-[10px] font-bold uppercase tracking-widest text-secondary">Welcome back, Airlangga Student</span>
                <h1 class="font-headline text-3xl font-extrabold tracking-tight text-white md:text-4xl">Empowering your academic excellence.</h1>
                <p class="mt-4 text-sm leading-relaxed text-white/80">
                    Monitor riwayat pengajuan, status verifikasi, dan dokumen KIP-K Anda dalam satu portal mahasiswa.
                </p>
                <button type="button" class="mt-6 flex cursor-not-allowed items-center gap-2 rounded-lg bg-secondary px-8 py-3.5 font-bold text-on-secondary-fixed shadow-lg shadow-secondary/20 opacity-80">
                    <span class="material-symbols-outlined">add</span>
                    Form Pengajuan Menyusul
                </button>
            </div>

            <div class="w-full rounded-xl border border-white/30 bg-white/20 p-6 backdrop-blur-md md:w-80">
                <h3 class="flex items-center gap-2 text-lg font-bold text-white">
                    <span class="material-symbols-outlined">assignment_turned_in</span>
                    Status Summary
                </h3>
                <div class="mt-4 space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-white/70">Total Pengajuan</span>
                        <span class="text-sm font-medium text-white">{{ $summary['total'] }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-white/70">Status Terakhir</span>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold uppercase {{ $latestStatusClass }}">{{ $latestStatus }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-white/70">Dokumen Tersedia</span>
                        <span class="text-sm font-medium text-white">{{ $summary['has_documents'] ? 'Ya' : 'Belum' }}</span>
                    </div>
                </div>
            </div>
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
                    <p class="text-3xl font-black text-on-surface">{{ number_format($card['value']) }}</p>
                </div>
            </div>
        @endforeach
    </section>

    <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
        <section class="lg:col-span-2">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-headline text-xl font-extrabold tracking-tight text-on-surface">History of Applications</h2>
                <form method="GET" action="{{ route('student.dashboard') }}" class="relative">
                    <span class="material-symbols-outlined absolute inset-y-0 left-3 flex items-center text-sm text-slate-400">search</span>
                    <input
                        type="text"
                        name="q"
                        value="{{ $filters['q'] }}"
                        placeholder="Cari ID atau status..."
                        class="w-52 rounded-lg bg-surface px-4 py-2 pl-9 text-sm shadow-sm outline-none transition-all focus:ring-2 focus:ring-primary/20"
                    />
                </form>
            </div>

            <div class="overflow-hidden rounded-xl bg-surface shadow-sm">
                @if($applications->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-left">
                            <thead>
                                <tr class="border-b border-slate-100 bg-surface-container-low">
                                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-500">Application ID</th>
                                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-500">Submission Date</th>
                                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-500">Status</th>
                                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-slate-500">Recommendation</th>
                                    <th class="px-6 py-4 text-center text-[10px] font-bold uppercase tracking-widest text-slate-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                @foreach($applications as $application)
                                    @php
                                        $statusClass = $statusClasses[$application->status] ?? 'bg-slate-100 text-slate-600 border-t-2 border-slate-300';
                                        $displayId = 'KIPK-'.($application->created_at?->format('Y') ?? now()->format('Y')).'-'.str_pad((string) $application->id, 3, '0', STR_PAD_LEFT);
                                        $snapshot = $application->modelSnapshot;
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
                                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold uppercase {{ $statusClass }}">
                                                {{ $application->status }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-semibold text-on-surface">
                                            {{ $snapshot?->final_recommendation ?? 'Belum diproses model' }}
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
                                                    href="{{ url('/api/student/applications/'.$application->id) }}"
                                                    target="_blank"
                                                    class="rounded p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-primary"
                                                    title="Lihat detail JSON"
                                                >
                                                    <span class="material-symbols-outlined">visibility</span>
                                                </a>
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
                        <p class="mt-2 max-w-xs text-sm text-on-surface-variant">Anda belum memiliki riwayat pengajuan KIP-K pada akun ini.</p>
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
                        <h4 class="font-bold text-on-surface">Student Profile</h4>
                        <p class="text-[10px] uppercase tracking-widest text-on-surface-variant">Portal Summary</p>
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
                        <p class="mt-1 text-sm font-semibold capitalize text-on-surface">{{ $student->role }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-xl bg-tertiary p-6 text-white shadow-lg">
                <h4 class="text-lg font-bold">Need assistance?</h4>
                <p class="mt-2 text-sm text-white/70">
                    Hubungi helpdesk KIP-K UNAIR jika Anda membutuhkan bantuan terkait status pengajuan atau kelengkapan dokumen.
                </p>
                <div class="mt-6 grid grid-cols-2 gap-3">
                    <button type="button" class="rounded-lg bg-white/10 py-2 text-xs font-bold transition-all hover:bg-white/20">Documentation</button>
                    <button type="button" class="rounded-lg bg-white/10 py-2 text-xs font-bold transition-all hover:bg-white/20">Help Center</button>
                </div>
            </section>
        </aside>
    </div>
</main>

<footer class="border-t border-slate-100 bg-white py-8">
    <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-8 md:flex-row">
        <div class="flex flex-col items-center gap-1 md:items-start">
            <span class="font-bold text-slate-900">KIP-K Management System</span>
            <span class="text-[10px] uppercase tracking-widest text-slate-400">© 2026 Universitas Airlangga</span>
        </div>
        <div class="flex gap-6">
            <a href="#" class="text-[10px] uppercase tracking-widest text-slate-400 transition-all hover:text-blue-500 hover:underline">Privacy Policy</a>
            <a href="#" class="text-[10px] uppercase tracking-widest text-slate-400 transition-all hover:text-blue-500 hover:underline">Terms of Service</a>
            <a href="#" class="text-[10px] uppercase tracking-widest text-slate-400 transition-all hover:text-blue-500 hover:underline">Contact Support</a>
        </div>
    </div>
</footer>
@endsection
