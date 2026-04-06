@props([
    'admin',
    'title',
    'subtitle' => null,
    'eyebrow' => null,
    'titleClass' => 'text-xl font-extrabold tracking-tight text-on-surface',
    'subtitleClass' => 'text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400',
    'heightClass' => 'h-20',
])

@php
    $adminName = $admin->name ?? 'Admin';
    $adminInitials = collect(preg_split('/\s+/', trim($adminName)) ?: [])
        ->filter()
        ->map(fn ($part) => strtoupper(mb_substr($part, 0, 1)))
        ->take(2)
        ->implode('');
@endphp

<header class="sticky top-0 z-30 flex {{ $heightClass }} items-center justify-between border-b border-slate-100 bg-white/80 px-6 backdrop-blur-md md:px-10">
    <div class="flex min-w-0 items-center gap-4">
        @isset($leading)
            {{ $leading }}
        @endisset

        <div class="min-w-0">
            @if ($eyebrow)
                <p class="text-[10px] font-medium uppercase tracking-[0.2em] text-slate-400">{{ $eyebrow }}</p>
            @endif
            <h1 class="{{ $titleClass }}">{{ $title }}</h1>
            @if ($subtitle)
                <p class="{{ $subtitleClass }}">{{ $subtitle }}</p>
            @endif
        </div>
    </div>

    <div class="flex items-center gap-4">
        @isset($actions)
            <div class="hidden items-center gap-3 md:flex">
                {{ $actions }}
            </div>
        @endisset

        @isset($meta)
            <div class="flex items-center gap-3">
                {{ $meta }}
            </div>
        @endisset

        <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 border-primary/10 bg-primary-container font-bold text-primary shadow-sm">
            {{ $adminInitials !== '' ? $adminInitials : 'AD' }}
        </div>
    </div>
</header>
