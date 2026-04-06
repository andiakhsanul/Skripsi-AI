<article class="rounded-3xl border border-slate-100 bg-white p-6 shadow-lg">
    <div class="flex items-start gap-4">
        <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl {{ $card['icon_wrap'] }}">
            <span class="material-symbols-outlined text-3xl">{{ $card['icon'] }}</span>
        </div>
        <div>
            <h3 class="font-bold text-on-surface">{{ $card['title'] }}</h3>
            <p class="mt-2 text-xs leading-6 text-on-surface-variant">{{ $card['description'] }}</p>
            @if (! empty($card['href']) && ! empty($card['cta']))
                <a href="{{ $card['href'] }}" target="_blank" class="mt-3 inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline">
                    {{ $card['cta'] }}
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                </a>
            @endif
        </div>
    </div>
</article>
