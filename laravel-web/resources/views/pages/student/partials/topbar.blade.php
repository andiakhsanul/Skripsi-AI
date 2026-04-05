@php
    $studentName = $student->name ?? 'Mahasiswa';
    $initials = collect(preg_split('/\s+/', trim($studentName)) ?: [])
        ->filter()
        ->map(fn ($part) => strtoupper(mb_substr($part, 0, 1)))
        ->take(2)
        ->implode('');
@endphp

<header class="fixed top-0 z-50 w-full border-b border-slate-100 bg-white/80 shadow-sm backdrop-blur-md">
    <div class="mx-auto flex h-16 w-full max-w-screen-2xl items-center justify-between px-6">
        <div class="flex items-center gap-8">
            <a href="{{ route('student.dashboard') }}" class="text-xl font-extrabold tracking-tighter text-blue-800">KIP-K UNAIR</a>
            <nav class="hidden items-center gap-6 text-sm font-medium tracking-tight md:flex">
                <a href="{{ route('student.dashboard') }}" class="{{ request()->routeIs('student.dashboard') ? 'border-b-2 border-yellow-500 pb-1 text-blue-700' : 'text-slate-600 transition-colors hover:text-blue-700' }}">Dashboard</a>
                <a href="{{ route('student.applications.create') }}" class="{{ request()->routeIs('student.applications.create') ? 'border-b-2 border-yellow-500 pb-1 text-blue-700' : 'text-slate-600 transition-colors hover:text-blue-700' }}">Ajukan KIP-K</a>
                <a href="{{ route('student.dashboard', ['status' => 'Submitted']) }}" class="text-slate-600 transition-colors hover:text-blue-700">Status Saya</a>
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
                <button type="submit" class="rounded-lg p-2 text-slate-500 transition-colors hover:bg-slate-50 hover:text-error" title="Keluar">
                    <span class="material-symbols-outlined block">logout</span>
                </button>
            </form>
        </div>
    </div>
</header>
