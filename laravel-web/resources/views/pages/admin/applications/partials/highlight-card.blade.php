<div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
    <div class="flex items-start justify-between gap-3">
        <div class="flex h-11 w-11 items-center justify-center rounded-2xl {{ $highlight['icon_wrap'] }}">
            <span class="material-symbols-outlined">{{ $highlight['icon'] }}</span>
        </div>
        <span class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">{{ $highlight['label'] }}</span>
    </div>
    <p class="mt-5 text-lg font-black leading-6 text-slate-800">{{ $highlight['value'] }}</p>
    <p class="mt-2 text-xs font-medium leading-5 text-slate-500">{{ $highlight['note'] }}</p>
</div>
