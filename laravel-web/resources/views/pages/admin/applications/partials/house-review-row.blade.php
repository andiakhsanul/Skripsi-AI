@php
    $requiredValues = [
        'penghasilan_ayah_rupiah' => $application->penghasilan_ayah_rupiah,
        'penghasilan_ibu_rupiah' => $application->penghasilan_ibu_rupiah,
        'penghasilan_gabungan_rupiah' => $application->penghasilan_gabungan_rupiah,
        'jumlah_tanggungan_raw' => $application->jumlah_tanggungan_raw,
        'anak_ke_raw' => $application->anak_ke_raw,
        'status_orangtua_text' => $application->status_orangtua_text,
        'status_rumah_text' => $application->status_rumah_text,
        'daya_listrik_text' => $application->daya_listrik_text,
    ];

    $missingFields = collect($requiredValues)
        ->filter(fn ($value) => blank($value) && $value !== 0)
        ->keys()
        ->map(fn ($field) => str_replace('_', ' ', $field))
        ->values();

    $fieldName = fn (string $field): string => "applications[{$application->id}][{$field}]";
    $inputBaseClass = 'rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-on-surface focus:border-primary focus:ring-2 focus:ring-primary/15';
@endphp

<tr class="border-b border-slate-100 align-top last:border-b-0">
    <td class="px-5 py-4">
        <div class="space-y-1">
            <p class="text-sm font-black text-on-surface">{{ $application->applicant_name ?? 'Mahasiswa' }}</p>
            <p class="text-xs font-medium text-slate-500">{{ $application->faculty ?? '-' }}</p>
            <p class="text-xs font-medium text-slate-400">{{ $application->study_program ?? '-' }}</p>
        </div>
    </td>

    <td class="px-5 py-4">
        <div class="space-y-1 text-xs font-medium text-slate-500">
            <p><span class="font-bold text-slate-700">Ref:</span> {{ $application->source_reference_number ?? '-' }}</p>
            <p><span class="font-bold text-slate-700">Baris:</span> {{ $application->source_row_number ?? '-' }}</p>
            <p><span class="font-bold text-slate-700">Status:</span> {{ $application->status }}</p>
        </div>
    </td>

    <td class="px-5 py-4">
        @if ($missingFields->isEmpty())
            <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700">
                Lengkap
            </span>
        @else
            <div class="max-w-xs space-y-2">
                <span class="inline-flex rounded-full bg-error-container px-3 py-1 text-xs font-black text-on-error-container">
                    {{ $missingFields->count() }} data kosong
                </span>
                <p class="text-xs font-medium leading-5 text-slate-500">
                    {{ $missingFields->implode(', ') }}
                </p>
            </div>
        @endif
    </td>

    <td class="px-5 py-4">
        <div class="space-y-2 text-xs font-medium text-slate-500">
            <p><span class="font-bold text-slate-700">Orang tua:</span> {{ $application->status_orangtua_text ?? '-' }}</p>
            <p><span class="font-bold text-slate-700">Rumah:</span> {{ $application->status_rumah_text ?? '-' }}</p>
            <p><span class="font-bold text-slate-700">Listrik:</span> {{ $application->daya_listrik_text ?? '-' }}</p>
            <p><span class="font-bold text-slate-700">Gabungan:</span> Rp {{ number_format((int) ($application->penghasilan_gabungan_rupiah ?? 0), 0, ',', '.') }}</p>
        </div>
    </td>

    <td class="px-5 py-4">
        <div class="flex flex-col gap-2">
            @if ($application->source_document_link)
                <a
                    href="{{ $application->source_document_link }}"
                    target="_blank"
                    rel="noreferrer"
                    class="inline-flex items-center gap-2 text-xs font-bold text-primary hover:underline"
                >
                    <span class="material-symbols-outlined text-base">description</span>
                    Buka Dokumen
                </a>
            @else
                <span class="text-xs font-medium text-slate-400">Dokumen belum tersedia</span>
            @endif

            @if ($application->modelSnapshot || $application->currentEncoding || $application->trainingRows->isNotEmpty())
                <p class="rounded-xl bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700">
                    Perubahan akan membersihkan snapshot, encoding, dan data training lama untuk pengajuan ini.
                </p>
            @endif
        </div>
    </td>

    <td class="px-5 py-4">
        <input type="hidden" name="{{ $fieldName('id') }}" value="{{ $application->id }}" />

        <div class="min-w-[680px] space-y-4">
            <div>
                <p class="mb-2 text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">Kartu Pendukung</p>
                <div class="grid grid-cols-5 gap-2">
                    @foreach (['kip' => 'KIP', 'pkh' => 'PKH', 'kks' => 'KKS', 'dtks' => 'DTKS', 'sktm' => 'SKTM'] as $field => $label)
                        <label class="space-y-1">
                            <span class="text-[11px] font-bold text-slate-500">{{ $label }}</span>
                            <select name="{{ $fieldName($field) }}" class="{{ $inputBaseClass }} w-full">
                                @foreach ($binaryOptions as $value => $optionLabel)
                                    <option value="{{ $value }}" @selected((int) $application->{$field} === (int) $value)>{{ $optionLabel }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <p class="mb-2 text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">Ekonomi dan Keluarga</p>
                <div class="grid grid-cols-4 gap-2">
                    <label class="space-y-1">
                        <span class="text-[11px] font-bold text-slate-500">Penghasilan Ayah</span>
                        <input type="number" min="0" name="{{ $fieldName('penghasilan_ayah_rupiah') }}" value="{{ $application->penghasilan_ayah_rupiah }}" placeholder="Rupiah" class="{{ $inputBaseClass }} w-full" />
                    </label>
                    <label class="space-y-1">
                        <span class="text-[11px] font-bold text-slate-500">Penghasilan Ibu</span>
                        <input type="number" min="0" name="{{ $fieldName('penghasilan_ibu_rupiah') }}" value="{{ $application->penghasilan_ibu_rupiah }}" placeholder="Rupiah" class="{{ $inputBaseClass }} w-full" />
                    </label>
                    <label class="space-y-1">
                        <span class="text-[11px] font-bold text-slate-500">Tanggungan</span>
                        <input type="number" min="0" name="{{ $fieldName('jumlah_tanggungan_raw') }}" value="{{ $application->jumlah_tanggungan_raw }}" placeholder="0" class="{{ $inputBaseClass }} w-full" />
                    </label>
                    <label class="space-y-1">
                        <span class="text-[11px] font-bold text-slate-500">Anak Ke</span>
                        <input type="number" min="0" name="{{ $fieldName('anak_ke_raw') }}" value="{{ $application->anak_ke_raw }}" placeholder="0" class="{{ $inputBaseClass }} w-full" />
                    </label>
                </div>
                <p class="mt-2 text-[11px] font-medium text-slate-400">
                    Penghasilan gabungan akan dihitung ulang otomatis dari ayah + ibu saat disimpan.
                </p>
            </div>

            <div>
                <p class="mb-2 text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">Kondisi Rumah Tangga</p>
                <div class="grid grid-cols-3 gap-2">
                    <label class="space-y-1">
                        <span class="text-[11px] font-bold text-slate-500">Status Orang Tua</span>
                        <input type="text" name="{{ $fieldName('status_orangtua_text') }}" value="{{ $application->status_orangtua_text }}" placeholder="ayah=hidup; ibu=hidup" class="{{ $inputBaseClass }} w-full" />
                    </label>
                    <label class="space-y-1">
                        <span class="text-[11px] font-bold text-slate-500">Status Rumah</span>
                        <select name="{{ $fieldName('status_rumah_text') }}" class="{{ $inputBaseClass }} w-full">
                            <option value="">Kosongkan dulu</option>
                            @foreach ($houseStatusOptions as $option)
                                <option value="{{ $option }}" @selected($application->status_rumah_text === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="space-y-1">
                        <span class="text-[11px] font-bold text-slate-500">Daya Listrik</span>
                        <input type="text" name="{{ $fieldName('daya_listrik_text') }}" value="{{ $application->daya_listrik_text }}" placeholder="450 / 900 / 1300" class="{{ $inputBaseClass }} w-full" />
                    </label>
                </div>
            </div>
        </div>
    </td>
</tr>
