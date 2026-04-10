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
        @php
            $hasHouseStatus = filled($application->status_rumah_text);
        @endphp
        <span class="inline-flex rounded-full px-3 py-1 text-xs font-black {{ $hasHouseStatus ? 'bg-emerald-50 text-emerald-700' : 'bg-error-container text-on-error-container' }}">
            {{ $hasHouseStatus ? $application->status_rumah_text : 'Belum diisi' }}
        </span>
    </td>
    <td class="px-5 py-4">
        <div class="space-y-2 text-xs font-medium text-slate-500">
            <p><span class="font-bold text-slate-700">Orang tua:</span> {{ $application->status_orangtua_text ?? '-' }}</p>
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
        <div class="flex min-w-[240px] flex-col gap-3">
            <input type="hidden" name="applications[{{ $application->id }}][id]" value="{{ $application->id }}" />

            <select
                name="applications[{{ $application->id }}][status_rumah_text]"
                class="rounded-xl border-none bg-surface-container px-4 py-3 text-sm font-semibold text-on-surface focus:ring-2 focus:ring-primary/20"
            >
                <option value="">Kosongkan dulu</option>
                @foreach ($houseStatusOptions as $option)
                    <option value="{{ $option }}" @selected($application->status_rumah_text === $option)>{{ $option }}</option>
                @endforeach
            </select>

            <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">
                Perubahan tersimpan saat tombol submit halaman ditekan.
            </p>
        </div>
    </td>
</tr>
