@extends('layouts.portal')

@section('title', 'Ajukan KIP-K | KIP-K UNAIR')
@section('description', 'Form pengajuan mahasiswa untuk submit data dan dokumen KIP-K Universitas Airlangga')

@php
    $studentName = $student->name ?? 'Mahasiswa';
    $binaryOptions = $options['binary'];
    $statusOrangtuaOptions = $options['status_orangtua'];
    $statusRumahOptions = $options['status_rumah'];
    $dayaListrikOptions = $options['daya_listrik'];
    $fieldValue = fn (string $field, mixed $default = null) => old($field, $default);
    $selectedBinary = fn (string $field) => (string) $fieldValue($field, '');
    $selectedStatusRumah = fn (string $option) => $fieldValue('status_rumah_text') === $option;
    $formAction = route('student.applications.store');
    $submitLabel = 'Kirim Pengajuan';
    $heroTitle = 'Pengajuan KIP-K';
    $heroDescription = 'Lengkapi seluruh data dengan sebenar-benarnya. Pengajuan hanya dapat dilakukan SATU KALI — pastikan data benar sebelum submit. Setelah dikirim, Anda hanya bisa cek status pengajuan.';
    $progressLabel = 'Form siap dikirim';

    $binaryFields = [
        'kip' => ['label' => 'KIP', 'description' => 'Kartu Indonesia Pintar', 'icon' => 'credit_card'],
        'pkh' => ['label' => 'PKH', 'description' => 'Program Keluarga Harapan', 'icon' => 'family_restroom'],
        'kks' => ['label' => 'KKS', 'description' => 'Kartu Keluarga Sejahtera', 'icon' => 'payments'],
        'dtks' => ['label' => 'DTKS', 'description' => 'Data Terpadu Kesejahteraan Sosial', 'icon' => 'database'],
        'sktm' => ['label' => 'SKTM', 'description' => 'Surat Keterangan Tidak Mampu', 'icon' => 'description'],
    ];

    $formSteps = [
        ['number' => '1', 'title' => 'Bantuan Sosial', 'description' => 'Dokumen dan bantuan'],
        ['number' => '2', 'title' => 'Penghasilan', 'description' => 'Ayah, ibu, gabungan'],
        ['number' => '3', 'title' => 'Kondisi Keluarga', 'description' => 'Tanggungan dan status'],
        ['number' => '4', 'title' => 'Standar Hidup', 'description' => 'Rumah dan listrik'],
        ['number' => '5', 'title' => 'Dokumen PDF', 'description' => 'Unggah berkas pendukung'],
    ];

    $requirements = [
        'Slip gaji atau surat keterangan penghasilan terbaru',
        'Bukti bantuan sosial jika memilih Ya pada KIP, PKH, KKS, DTKS, atau SKTM',
        'Bukti tagihan listrik atau foto meter rumah',
        'Satu file PDF gabungan maksimal 10 MB',
    ];

    $incomeSummary = (int) $fieldValue('penghasilan_ayah_rupiah', 0) + (int) $fieldValue('penghasilan_ibu_rupiah', 0);
@endphp

@section('content')
@include('pages.student.partials.topbar', ['student' => $student])

<main class="mx-auto max-w-6xl px-6 pb-16 pt-24">
    <section class="mb-8 rounded-3xl bg-white p-8 shadow-lg">
        <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div>
                <span class="text-[10px] font-black uppercase tracking-[0.22em] text-primary">Form Mahasiswa</span>
                <h1 class="mt-2 text-3xl font-black tracking-tight text-on-surface">{{ $heroTitle }}</h1>
                <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-500">
                    {{ $heroDescription }}
                </p>

                <div class="mt-6 overflow-hidden rounded-full bg-surface-container h-2">
                    <div class="h-full w-full rounded-full bg-primary shadow-[0_0_10px_rgba(19,91,236,0.25)]"></div>
                </div>

                <div class="mt-3 flex items-center justify-between text-[10px] font-black uppercase tracking-[0.18em] text-primary">
                    <span>{{ $progressLabel }}</span>
                    <span>5 langkah pengisian</span>
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    @foreach ($formSteps as $step)
                        <div class="rounded-2xl border border-slate-100 bg-surface-container-low px-4 py-4">
                            <div class="flex items-center gap-3">
                                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-primary text-xs font-black text-white">{{ $step['number'] }}</span>
                                <div>
                                    <p class="text-xs font-black text-on-surface">{{ $step['title'] }}</p>
                                    <p class="text-[11px] font-medium text-slate-500">{{ $step['description'] }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <aside class="rounded-3xl border-t-4 border-secondary bg-secondary-fixed p-6 shadow-lg">
                <div class="rounded-2xl bg-primary px-5 py-5 text-white shadow-xl shadow-primary/15">
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-blue-100">Pemohon Aktif</p>
                    <p class="mt-2 text-lg font-black">{{ $studentName }}</p>
                    <p class="mt-1 text-sm text-blue-100/90">{{ $student->email }}</p>
                </div>

                <div class="mt-4 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-4">
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined text-amber-700">warning</span>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-amber-800">Penting</p>
                            <p class="mt-2 text-sm font-black text-amber-900">Pengajuan hanya bisa dilakukan SATU KALI</p>
                            <p class="mt-2 text-xs leading-6 text-amber-800">
                                Periksa kembali seluruh data sebelum submit. Setelah dikirim, Anda tidak dapat mengubah atau mengirim ulang — hanya bisa cek status keputusan admin.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-6">
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-on-secondary-fixed">Yang Perlu Disiapkan</p>
                    <div class="mt-4 space-y-3">
                        @foreach ($requirements as $requirement)
                            <div class="flex gap-3 rounded-2xl bg-white/70 px-4 py-4">
                                <span class="material-symbols-outlined text-on-secondary-fixed">task_alt</span>
                                <p class="text-sm leading-6 text-on-secondary-fixed-variant">{{ $requirement }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </aside>
        </div>
    </section>

    @if ($errors->any())
        <div class="mb-8 rounded-2xl border border-red-200 bg-error-container px-5 py-4 text-on-error-container">
            <p class="text-sm font-black uppercase tracking-[0.18em]">Periksa kembali form Anda</p>
            <ul class="mt-2 space-y-1 text-sm font-medium">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $formAction }}" enctype="multipart/form-data" class="space-y-10 pb-12">
        @csrf

        <section class="rounded-3xl bg-white p-8 shadow-lg">
            <div class="mb-6 flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary text-sm font-black text-white">0</span>
                <div>
                    <h2 class="text-xl font-black text-on-surface">Data Akademik</h2>
                    <p class="text-sm text-slate-500">Program studi dan fakultas Anda. Data ini hanya disimpan sebagai informasi pengajuan dan tidak digunakan oleh model AI.</p>
                </div>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <div class="space-y-2">
                    <label for="study_program" class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Program Studi</label>
                    <input
                        id="study_program"
                        name="study_program"
                        type="text"
                        value="{{ $fieldValue('study_program') }}"
                        required
                        maxlength="255"
                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-4 text-sm font-semibold text-on-surface focus:border-primary focus:ring-2 focus:ring-primary/15"
                        placeholder="Contoh: S1 Teknik Informatika"
                    />
                </div>

                <div class="space-y-2">
                    <label for="faculty" class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Fakultas</label>
                    <input
                        id="faculty"
                        name="faculty"
                        type="text"
                        value="{{ $fieldValue('faculty') }}"
                        required
                        maxlength="255"
                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-4 text-sm font-semibold text-on-surface focus:border-primary focus:ring-2 focus:ring-primary/15"
                        placeholder="Contoh: Fakultas Sains dan Teknologi"
                    />
                </div>
            </div>
        </section>

        <section class="rounded-3xl bg-white p-8 shadow-lg">
            <div class="mb-6 flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary text-sm font-black text-white">1</span>
                <div>
                    <h2 class="text-xl font-black text-on-surface">Kepemilikan Dokumen dan Bantuan Sosial</h2>
                    <p class="text-sm text-slate-500">Pilih kondisi yang sesuai dengan dokumen atau status keluarga Anda.</p>
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-5">
                @foreach ($binaryFields as $field => $meta)
                    <div class="rounded-2xl border border-slate-100 bg-surface-container-low p-5 shadow-sm">
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-primary shadow-sm">
                                <span class="material-symbols-outlined">{{ $meta['icon'] }}</span>
                            </div>
                            <div>
                                <p class="text-sm font-black text-on-surface">{{ $meta['label'] }}</p>
                                <p class="mt-1 text-xs font-medium leading-5 text-slate-500">{{ $meta['description'] }}</p>
                            </div>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-2" data-radio-card-group="binary">
                            @foreach ($binaryOptions as $option)
                                <label
                                    data-radio-card
                                    class="cursor-pointer rounded-xl border px-3 py-3 text-center text-sm font-bold transition {{ $selectedBinary($field) === (string) $option['value'] ? 'border-primary bg-primary-container text-primary shadow-sm shadow-primary/10' : 'border-slate-200 bg-white text-slate-600 hover:border-primary/40 hover:bg-primary/5' }}"
                                >
                                    <input
                                        type="radio"
                                        class="sr-only"
                                        name="{{ $field }}"
                                        value="{{ $option['value'] }}"
                                        @checked($selectedBinary($field) === (string) $option['value'])
                                        required
                                    />
                                    <span class="block">{{ $option['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="rounded-3xl bg-white p-8 shadow-lg">
                <div class="mb-6 flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary text-sm font-black text-white">2</span>
                    <div>
                        <h2 class="text-xl font-black text-on-surface">Penghasilan Keluarga</h2>
                        <p class="text-sm text-slate-500">Nominal gabungan akan dihitung otomatis dari penghasilan ayah dan ibu.</p>
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div class="space-y-2">
                        <label for="penghasilan_ayah_rupiah" class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Penghasilan Ayah</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-sm font-black text-slate-400">Rp</span>
                            <input id="penghasilan_ayah_rupiah" name="penghasilan_ayah_rupiah" type="number" min="0" value="{{ $fieldValue('penghasilan_ayah_rupiah') }}" class="w-full rounded-2xl border border-slate-200 bg-white py-4 pl-12 pr-4 text-sm font-semibold text-on-surface focus:border-primary focus:ring-2 focus:ring-primary/15" placeholder="0" />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label for="penghasilan_ibu_rupiah" class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Penghasilan Ibu</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-sm font-black text-slate-400">Rp</span>
                            <input id="penghasilan_ibu_rupiah" name="penghasilan_ibu_rupiah" type="number" min="0" value="{{ $fieldValue('penghasilan_ibu_rupiah') }}" class="w-full rounded-2xl border border-slate-200 bg-white py-4 pl-12 pr-4 text-sm font-semibold text-on-surface focus:border-primary focus:ring-2 focus:ring-primary/15" placeholder="0" />
                        </div>
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <label class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Estimasi Penghasilan Gabungan</label>
                        <div class="rounded-2xl border border-primary/10 bg-primary-container/40 px-5 py-4">
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-primary">Dihitung Sistem</p>
                            <p id="combined-income-display" class="mt-2 text-2xl font-black text-primary">Rp {{ number_format($incomeSummary, 0, ',', '.') }}</p>
                            <p class="mt-1 text-xs font-medium text-slate-500">Nilai ini hanya tampilan bantu. Sistem akan menghitung ulang saat Anda submit.</p>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="rounded-3xl border-t-4 border-secondary bg-secondary-fixed p-6 shadow-lg">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-secondary text-on-secondary">
                        <span class="material-symbols-outlined">info</span>
                    </div>
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-on-secondary-fixed">Catatan Penting</p>
                        <h3 class="text-lg font-black text-on-secondary-fixed">Isi sesuai dokumen asli</h3>
                    </div>
                </div>
                <p class="mt-4 text-sm leading-7 text-on-secondary-fixed-variant">
                    Gunakan nominal yang sesuai dengan slip gaji, surat keterangan penghasilan, atau dokumen resmi lain yang akan Anda unggah dalam PDF.
                </p>
            </aside>
        </section>

        <section class="rounded-3xl bg-white p-8 shadow-lg">
            <div class="mb-6 flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary text-sm font-black text-white">3</span>
                <div>
                    <h2 class="text-xl font-black text-on-surface">Kondisi Keluarga</h2>
                    <p class="text-sm text-slate-500">Data ini dipakai untuk membantu penilaian kerentanan rumah tangga.</p>
                </div>
            </div>

            <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                <div class="space-y-2">
                    <label for="jumlah_tanggungan_raw" class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Jumlah Tanggungan</label>
                    <input id="jumlah_tanggungan_raw" name="jumlah_tanggungan_raw" type="number" min="0" value="{{ $fieldValue('jumlah_tanggungan_raw') }}" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-4 text-sm font-semibold text-on-surface focus:border-primary focus:ring-2 focus:ring-primary/15" placeholder="Contoh: 4" />
                </div>

                <div class="space-y-2">
                    <label for="anak_ke_raw" class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Anak Ke-</label>
                    <input id="anak_ke_raw" name="anak_ke_raw" type="number" min="1" value="{{ $fieldValue('anak_ke_raw') }}" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-4 text-sm font-semibold text-on-surface focus:border-primary focus:ring-2 focus:ring-primary/15" placeholder="Contoh: 2" />
                </div>

                <div class="space-y-2 md:col-span-2">
                    <label for="status_orangtua_text" class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Status Orang Tua</label>
                    <select id="status_orangtua_text" name="status_orangtua_text" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-4 text-sm font-semibold text-on-surface focus:border-primary focus:ring-2 focus:ring-primary/15">
                        <option value="">Pilih status orang tua</option>
                        @foreach ($statusOrangtuaOptions as $option)
                            <option value="{{ $option }}" @selected($fieldValue('status_orangtua_text') === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>

        <section class="rounded-3xl bg-white p-8 shadow-lg">
            <div class="mb-6 flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary text-sm font-black text-white">4</span>
                <div>
                    <h2 class="text-xl font-black text-on-surface">Standar Hidup</h2>
                    <p class="text-sm text-slate-500">Pilih kondisi hunian dan kelistrikan yang paling sesuai dengan keadaan Anda saat ini.</p>
                </div>
            </div>

            <div class="grid gap-8 lg:grid-cols-2">
                <div class="space-y-4">
                    <label class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Status Rumah</label>
                    <div class="grid gap-3" data-radio-card-group="status-rumah">
                        @foreach ($statusRumahOptions as $option)
                            <label
                                data-radio-card
                                class="flex cursor-pointer items-center justify-between rounded-2xl border px-4 py-4 text-sm font-semibold transition {{ $selectedStatusRumah($option) ? 'border-primary bg-primary/5 text-primary shadow-sm shadow-primary/10' : 'border-slate-200 bg-surface-container-low text-on-surface hover:border-primary/40 hover:bg-primary/5' }}"
                            >
                                <span>{{ $option }}</span>
                                <input
                                    type="radio"
                                    name="status_rumah_text"
                                    value="{{ $option }}"
                                    class="text-primary focus:ring-primary"
                                    @checked($selectedStatusRumah($option))
                                    required
                                />
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="space-y-4">
                    <label for="daya_listrik_text" class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Daya Listrik</label>
                    <select id="daya_listrik_text" name="daya_listrik_text" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-4 text-sm font-semibold text-on-surface focus:border-primary focus:ring-2 focus:ring-primary/15">
                        <option value="">Pilih daya listrik rumah</option>
                        @foreach ($dayaListrikOptions as $option)
                            <option value="{{ $option }}" @selected($fieldValue('daya_listrik_text') === $option)>{{ $option }}</option>
                        @endforeach
                    </select>

                    <div class="rounded-2xl border border-dashed border-slate-300 bg-surface-container-low p-4">
                        <p class="text-xs leading-6 text-slate-500">
                            Gunakan pilihan yang paling sesuai dengan meter listrik atau bukti pembayaran listrik yang akan Anda gabungkan ke dalam PDF pendukung.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-3xl bg-white p-8 shadow-lg">
            <div class="mb-6 flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary text-sm font-black text-white">5</span>
                <div>
                    <h2 class="text-xl font-black text-on-surface">Dokumen Pendukung</h2>
                    <p class="text-sm text-slate-500">Unggah satu file PDF yang merangkum seluruh bukti pendukung sesuai ketentuan admin.</p>
                </div>
            </div>

            <label for="submitted_pdf" class="group relative block cursor-pointer">
                <input id="submitted_pdf" name="submitted_pdf" type="file" accept="application/pdf" class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0" />
                <div class="rounded-3xl border-4 border-dashed border-slate-200 bg-surface-container-low p-10 text-center transition-all group-hover:border-primary/40 group-hover:bg-primary/5">
                    <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-primary-container text-primary transition-transform group-hover:scale-105">
                        <span class="material-symbols-outlined text-5xl">upload_file</span>
                    </div>
                    <p class="mt-5 text-lg font-black text-on-surface">Tarik dan lepas file PDF di sini</p>
                    <p class="mt-2 text-sm text-slate-500">Atau klik area ini untuk memilih file dari perangkat Anda.</p>
                    <div class="mt-5 inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-[10px] font-black uppercase tracking-[0.18em] text-slate-500 shadow-sm">
                        <span class="material-symbols-outlined text-sm">picture_as_pdf</span>
                        PDF maksimal 10 MB
                    </div>
                    <p class="mt-4 text-xs font-medium text-slate-500">Gabungkan slip gaji, kartu bantuan, dan bukti lainnya ke satu PDF.</p>
                </div>
            </label>

            <div class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-surface-container-low px-4 py-4">
                <p class="text-xs leading-6 text-slate-500">
                    Nama file yang dipilih akan langsung terkirim bersama data mentah Anda. Sistem tidak akan memproses pengajuan tanpa dokumen PDF pendukung.
                </p>
            </div>
        </section>

        <div class="flex flex-col gap-3 pt-4 sm:flex-row">
            <button type="submit" class="inline-flex flex-1 items-center justify-center gap-2 rounded-2xl bg-primary px-8 py-4 text-base font-black text-white shadow-xl shadow-primary/20 transition hover:bg-blue-700">
                <span class="material-symbols-outlined text-sm">send</span>
                {{ $submitLabel }}
            </button>
            <a href="{{ route('student.dashboard') }}" class="inline-flex items-center justify-center rounded-2xl border-2 border-slate-200 px-6 py-4 text-sm font-bold text-slate-600 transition hover:bg-slate-50">
                Batal
            </a>
        </div>
    </form>
</main>

@include('pages.student.partials.footer')
@endsection

@push('scripts')
<script>
    (() => {
        const fatherInput = document.getElementById('penghasilan_ayah_rupiah');
        const motherInput = document.getElementById('penghasilan_ibu_rupiah');
        const combinedDisplay = document.getElementById('combined-income-display');
        const pdfInput = document.getElementById('submitted_pdf');
        const uploadContainer = pdfInput?.closest('label');
        const activeClassesByGroup = {
            binary: ['border-primary', 'bg-primary-container', 'text-primary', 'shadow-sm', 'shadow-primary/10'],
            'status-rumah': ['border-primary', 'bg-primary/5', 'text-primary', 'shadow-sm', 'shadow-primary/10'],
        };
        const inactiveClassesByGroup = {
            binary: ['border-slate-200', 'bg-white', 'text-slate-600'],
            'status-rumah': ['border-slate-200', 'bg-surface-container-low', 'text-on-surface'],
        };

        const formatter = new Intl.NumberFormat('id-ID');
        const syncRadioCardGroup = (group) => {
            const groupName = group.dataset.radioCardGroup;
            const activeClasses = activeClassesByGroup[groupName] ?? [];
            const inactiveClasses = inactiveClassesByGroup[groupName] ?? [];

            group.querySelectorAll('[data-radio-card]').forEach((card) => {
                const input = card.querySelector('input[type="radio"]');
                const isChecked = Boolean(input?.checked);

                activeClasses.forEach((className) => card.classList.toggle(className, isChecked));
                inactiveClasses.forEach((className) => card.classList.toggle(className, ! isChecked));
            });
        };

        const updateCombinedIncome = () => {
            const father = Number.parseInt(fatherInput.value || '0', 10) || 0;
            const mother = Number.parseInt(motherInput.value || '0', 10) || 0;
            combinedDisplay.textContent = `Rp ${formatter.format(father + mother)}`;
        };

        if (fatherInput && motherInput && combinedDisplay) {
            fatherInput.addEventListener('input', updateCombinedIncome);
            motherInput.addEventListener('input', updateCombinedIncome);
            updateCombinedIncome();
        }

        document.querySelectorAll('[data-radio-card-group]').forEach((group) => {
            syncRadioCardGroup(group);

            group.querySelectorAll('input[type="radio"]').forEach((input) => {
                input.addEventListener('change', () => syncRadioCardGroup(group));
            });
        });

        if (pdfInput && uploadContainer) {
            pdfInput.addEventListener('change', () => {
                const existingBadge = uploadContainer.querySelector('[data-selected-file]');
                const file = pdfInput.files?.[0];

                if (!file) {
                    existingBadge?.remove();

                    return;
                }

                if (existingBadge) {
                    existingBadge.textContent = file.name;

                    return;
                }

                const badge = document.createElement('p');
                badge.dataset.selectedFile = 'true';
                badge.className = 'mt-4 text-sm font-black text-primary';
                badge.textContent = file.name;
                uploadContainer.querySelector('div')?.appendChild(badge);
            });
        }
    })();
</script>
@endpush
