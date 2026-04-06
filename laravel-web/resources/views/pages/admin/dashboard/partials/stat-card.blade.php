@props([
    'label',
    'value',
    'hint',
    'border',
    'iconWrap',
    'icon',
    'hintClass' => 'text-slate-500',
])

<div class="relative overflow-hidden rounded-2xl border-b-4 {{ $border }} bg-white p-6 shadow-lg transition-transform duration-300 hover:-translate-y-0.5 hover:shadow-xl">
    <div class="absolute -bottom-5 -right-5 opacity-10">
        <span class="material-symbols-outlined text-[88px]">{{ $icon }}</span>
    </div>

    <div class="mb-5 flex items-center justify-between">
        <div class="flex h-12 w-12 items-center justify-center rounded-2xl {{ $iconWrap }}">
            <span class="material-symbols-outlined">{{ $icon }}</span>
        </div>
        <span class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">{{ $label }}</span>
    </div>

    <p class="text-3xl font-black text-on-surface">{{ number_format($value) }}</p>
    <p class="mt-2 text-sm font-medium {{ $hintClass }}">{{ $hint }}</p>
</div>
