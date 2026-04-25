@php
    $active = $active ?? 'dashboard';
    $application = $application ?? null;
    $showTrainingLink = $showTrainingLink ?? false;

    $navItems = [
        [
            'key' => 'dashboard',
            'label' => 'Antrean Keputusan',
            'icon' => 'inbox',
            'href' => route('admin.dashboard'),
        ],
        [
            'key' => 'applications',
            'label' => 'Semua Aplikan',
            'icon' => 'groups',
            'href' => route('admin.applications.index'),
        ],
        [
            'key' => 'house-review',
            'label' => 'Kelengkapan Data',
            'icon' => 'edit_note',
            'href' => route('admin.applications.house-review'),
        ],
    ];

    if ($application) {
        $navItems[] = [
            'key' => 'review',
            'label' => 'Review Pengajuan',
            'icon' => 'verified_user',
            'href' => route('admin.applications.show', $application),
        ];
    }

    if ($application && $showTrainingLink) {
        $navItems[] = [
            'key' => 'training',
            'label' => 'Koreksi Training',
            'icon' => 'fact_check',
            'href' => route('admin.training-data.show', $application),
        ];
    }

    $navItems[] = [
        'key' => 'retrain',
        'label' => 'Retrain Model',
        'icon' => 'model_training',
        'href' => route('admin.models.retrain'),
    ];
@endphp

<aside class="fixed left-0 top-0 hidden h-screen w-64 flex-col border-r border-slate-100 bg-slate-50 md:flex">
    <div class="p-6">
        <p class="text-lg font-black uppercase tracking-[0.2em] text-blue-900">KIP-K UNAIR</p>
    </div>

    <nav class="flex flex-1 flex-col gap-2 p-4">
        @foreach ($navItems as $item)
            @php
                $isActive = $active === $item['key'];
            @endphp
            <a
                href="{{ $item['href'] }}"
                class="flex items-center gap-3 rounded-md px-4 py-3 font-semibold transition-transform duration-200 hover:-translate-y-0.5 {{ $isActive ? 'border-t-2 border-yellow-500 bg-white text-blue-700 shadow-sm' : 'text-slate-500 hover:bg-white' }}"
            >
                <span class="material-symbols-outlined">{{ $item['icon'] }}</span>
                <span class="text-sm">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="border-t border-slate-100 bg-slate-100/50 p-4">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button
                type="submit"
                class="flex w-full items-center gap-3 px-4 py-3 text-left font-semibold text-slate-500 transition-colors hover:text-error"
            >
                <span class="material-symbols-outlined">logout</span>
                <span class="text-sm">Keluar</span>
            </button>
        </form>
    </div>
</aside>
