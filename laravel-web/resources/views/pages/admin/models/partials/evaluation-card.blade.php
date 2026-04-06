@php
    $metrics = $metrics ?? null;
@endphp

@if ($metrics)
    <div class="rounded-2xl bg-surface-container p-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-sm font-black text-on-surface">{{ $metrics['label'] }}</p>
                <p class="mt-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">
                    Evaluasi {{ $metrics['evaluation_dataset'] === 'validation' ? 'Validation' : 'Training' }}
                </p>
            </div>
            <span class="rounded-full bg-primary-container px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-on-primary-container">
                Threshold {{ number_format((float) ($metrics['threshold'] ?? 0), 4) }}
            </span>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
            <div class="rounded-xl bg-white px-4 py-3">
                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Precision Indikasi</p>
                <p class="mt-1 text-lg font-black text-on-surface">{{ number_format((float) ($metrics['precision_indikasi'] ?? 0), 4) }}</p>
            </div>
            <div class="rounded-xl bg-white px-4 py-3">
                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Recall Indikasi</p>
                <p class="mt-1 text-lg font-black text-on-surface">{{ number_format((float) ($metrics['recall_indikasi'] ?? 0), 4) }}</p>
            </div>
            <div class="rounded-xl bg-white px-4 py-3">
                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">F1 Indikasi</p>
                <p class="mt-1 text-lg font-black text-on-surface">{{ number_format((float) ($metrics['f1_indikasi'] ?? 0), 4) }}</p>
            </div>
            <div class="rounded-xl bg-white px-4 py-3">
                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Balanced Accuracy</p>
                <p class="mt-1 text-lg font-black text-on-surface">{{ number_format((float) ($metrics['balanced_accuracy'] ?? 0), 4) }}</p>
            </div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-[1.1fr_220px]">
            <div class="rounded-xl bg-white px-4 py-4">
                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Cara Membaca</p>
                <p class="mt-2 text-sm font-medium leading-6 text-slate-600">
                    Precision menunjukkan seberapa sering prediksi <strong>Indikasi</strong> benar. Recall menunjukkan seberapa baik model menangkap kasus yang memang <strong>Indikasi</strong>.
                </p>
            </div>

            <div class="rounded-xl bg-white px-4 py-4">
                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Confusion Matrix</p>
                <div class="mt-3 grid grid-cols-2 gap-2 text-center">
                    <div class="rounded-lg bg-slate-50 px-3 py-3">
                        <p class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">TN</p>
                        <p class="mt-1 text-base font-black text-on-surface">{{ $metrics['confusion_matrix']['tn'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 px-3 py-3">
                        <p class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">FP</p>
                        <p class="mt-1 text-base font-black text-on-surface">{{ $metrics['confusion_matrix']['fp'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 px-3 py-3">
                        <p class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">FN</p>
                        <p class="mt-1 text-base font-black text-on-surface">{{ $metrics['confusion_matrix']['fn'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 px-3 py-3">
                        <p class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">TP</p>
                        <p class="mt-1 text-base font-black text-on-surface">{{ $metrics['confusion_matrix']['tp'] ?? 0 }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
