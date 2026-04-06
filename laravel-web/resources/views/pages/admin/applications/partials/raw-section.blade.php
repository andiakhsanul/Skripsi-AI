<article class="overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-lg">
    <div class="border-b border-slate-100 px-6 py-5">
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl {{ $section['icon_wrap'] }}">
                <span class="material-symbols-outlined">{{ $section['icon'] }}</span>
            </div>
            <div>
                <h3 class="text-base font-extrabold text-slate-800">{{ $section['title'] }}</h3>
                <p class="mt-1 text-xs font-medium leading-5 text-slate-500">{{ $section['subtitle'] }}</p>
            </div>
        </div>
    </div>

    <div class="grid gap-3 p-5">
        @foreach ($section['items'] as $item)
            <div class="rounded-2xl bg-surface-container-low p-4">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">{{ $item['label'] }}</p>
                <p class="mt-2 text-base font-black text-slate-800">{{ $item['value'] }}</p>
                <p class="mt-1 text-xs font-medium leading-5 text-slate-500">{{ $item['note'] }}</p>
            </div>
        @endforeach
    </div>
</article>
