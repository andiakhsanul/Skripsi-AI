@props([
    'href',
    'label',
    'active' => false,
    'activeClass',
    'inactiveClass',
])

<a
    href="{{ $href }}"
    class="rounded-full px-4 py-2 text-xs font-black uppercase tracking-[0.18em] transition {{ $active ? $activeClass : $inactiveClass }}"
>
    {{ $label }}
</a>
