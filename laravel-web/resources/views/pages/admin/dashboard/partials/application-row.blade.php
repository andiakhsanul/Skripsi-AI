@props([
    'application',
    'statusBadgeClasses',
    'statusDisplayLabels',
    'priorityMeta',
])

@php
    $student = $application->student;
    $snapshot = $application->modelSnapshot;
    $studentName = $student?->name ?? $application->applicant_name ?? 'Nama belum tersedia';
    $studentEmail = $student?->email ?? $application->applicant_email ?? 'Email belum tersedia';
    $studentInitials = collect(preg_split('/\s+/', trim($studentName)) ?: [])
        ->filter()
        ->map(fn ($part) => strtoupper(mb_substr($part, 0, 1)))
        ->take(2)
        ->implode('');
    $statusClass = $statusBadgeClasses[$application->status] ?? 'bg-slate-100 text-slate-600 border border-slate-200';
    $statusLabel = $statusDisplayLabels[$application->status] ?? $application->status;
    $priority = $snapshot?->review_priority ?? 'normal';
    $priorityDisplay = $priorityMeta[$priority] ?? $priorityMeta['normal'];
    $recommendationBadge = match ($snapshot?->final_recommendation) {
        'Indikasi' => 'bg-error-container text-on-error-container border border-red-200',
        'Layak' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
        default => 'bg-slate-100 text-slate-600 border border-slate-200',
    };
    $pdfUrl = $application->submitted_pdf_path
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($application->submitted_pdf_path)
        : $application->source_document_link;
    $documentLabel = $application->submitted_pdf_path ? 'PDF' : 'Berkas';
@endphp

<tr class="transition-colors {{ $snapshot?->final_recommendation === 'Indikasi' && $application->status === 'Submitted' ? 'bg-red-50/30 hover:bg-red-50/50' : 'hover:bg-slate-50/50' }}">
    <td class="px-6 py-5">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700">
                {{ $studentInitials !== '' ? $studentInitials : 'MH' }}
            </div>
            <div>
                <p class="text-sm font-bold text-on-surface">{{ $studentName }}</p>
                <p class="text-[11px] font-medium text-slate-400">{{ $studentEmail }}</p>
            </div>
        </div>
    </td>
    <td class="px-6 py-5">
        <p class="text-sm font-medium text-on-surface">{{ $application->faculty ?? 'Fakultas belum tersedia' }}</p>
        <p class="text-[11px] text-slate-400">{{ $application->study_program ?? 'Program studi belum tersedia' }}</p>
        <p class="mt-1 text-[11px] text-slate-400">Dibuat {{ $application->created_at?->format('d/m/Y H:i') ?? '-' }}</p>
    </td>
    <td class="px-6 py-5">
        <span class="rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-tight {{ $statusClass }}">
            {{ $statusLabel }}
        </span>
    </td>
    <td class="px-6 py-5">
        <div class="flex items-center gap-2">
            <div class="h-2 w-2 rounded-full {{ $priorityDisplay['dot'] }}"></div>
            <span class="text-xs {{ $priorityDisplay['text'] }}">{{ $priorityDisplay['label'] }}</span>
        </div>
    </td>
    <td class="px-6 py-5">
        <p>
            <span class="rounded-full px-3 py-1 text-[11px] font-black uppercase tracking-[0.14em] {{ $recommendationBadge }}">
                {{ $snapshot?->final_recommendation ?? 'Belum diproses model' }}
            </span>
        </p>
        <p class="text-[11px] text-slate-400">
            @if ($snapshot?->model_ready)
                CatBoost {{ $snapshot->catboost_label ?? '-' }} · NB {{ $snapshot->naive_bayes_label ?? '-' }}
            @else
                Belum ada snapshot model
            @endif
        </p>
        @if ($snapshot?->disagreement_flag)
            <p class="mt-1 inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-700">Disagreement model</p>
        @endif
    </td>
    <td class="px-6 py-5">
        <div class="flex flex-wrap justify-end gap-2">
            <a
                href="{{ route('admin.applications.show', $application) }}"
                class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 transition-colors hover:bg-slate-50"
            >
                Tinjau
            </a>

            @if ($pdfUrl)
                <a
                    href="{{ $pdfUrl }}"
                    target="_blank"
                    class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 transition-colors hover:bg-slate-50"
                >
                    {{ $documentLabel }}
                </a>
            @endif

            <span class="inline-flex items-center rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-500">
                {{ $application->status === 'Submitted' ? 'Review Detail' : 'Selesai' }}
            </span>
        </div>
    </td>
</tr>
