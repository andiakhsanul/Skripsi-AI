@extends('layouts.portal')

@section('title', 'Monitoring Retrain | KIP-K UNAIR')
@section('description', 'Halaman admin untuk memantau progres pelatihan ulang model KIP-K UNAIR')

@php
    $notice = session('admin_notice');
    $activeModel = $payload['active_model'];
    $latestAttempt = $payload['latest_attempt'];
    $statusToneClasses = $page['status_tone_classes'];
    $statusDotClasses = $page['status_dot_classes'];
    $steps = [
        'fetching_data' => 'Ambil data',
        'encoding' => 'Encoding fitur',
        'data_quality_check' => 'Validasi data',
        'split_dataset' => 'Split dataset',
        'training_catboost_eval' => 'CatBoost evaluasi',
        'cv_threshold_catboost' => 'CV threshold CatBoost',
        'training_catboost_final' => 'CatBoost final',
        'training_naive_bayes_eval' => 'Naive Bayes evaluasi',
        'cv_threshold_naive_bayes' => 'CV threshold NB',
        'training_naive_bayes_final' => 'Naive Bayes final',
        'building_evaluation' => 'Susun evaluasi',
        'persisting_models' => 'Simpan artefak',
        'done' => 'Selesai',
    ];
@endphp

@section('content')
<main class="min-h-screen bg-background">
    @include('pages.admin.partials.sidebar', ['active' => 'retrain'])

    <div class="md:ml-64">
        <x-admin.topbar
            :admin="$admin"
            title="Monitoring Retrain"
            subtitle="Pantau proses pelatihan ulang model secara langsung"
        >
            <x-slot:meta>
                <div class="flex items-center gap-2 rounded-full border px-4 py-1.5 text-xs font-black uppercase tracking-[0.18em] {{ $statusToneClasses }}">
                    <span class="h-2.5 w-2.5 rounded-full {{ $statusDotClasses }}"></span>
                    Model: {{ $payload['model_status']['label'] }}
                </div>
            </x-slot:meta>
        </x-admin.topbar>

        <div class="mx-auto max-w-7xl space-y-6 p-6 md:p-10">
            @if ($notice)
                <div class="rounded-2xl border px-5 py-4 {{ ($notice['type'] ?? 'success') === 'error' ? 'border-red-200 bg-error-container text-on-error-container' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">
                    <p class="text-sm font-black uppercase tracking-[0.18em]">{{ $notice['title'] ?? 'Informasi Sistem' }}</p>
                    <p class="mt-1 text-sm font-medium">{{ $notice['message'] ?? '' }}</p>
                </div>
            @endif

            <section class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
                <div class="rounded-3xl bg-white p-6 shadow-lg md:p-8">
                    <div class="flex flex-col gap-4 border-b border-slate-100 pb-6 md:flex-row md:items-start md:justify-between">
                        <div>
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Progres Retrain</p>
                            <h2 id="monitorStatusTitle" class="mt-2 text-2xl font-black text-on-surface md:text-3xl">Membaca status proses</h2>
                            <p id="monitorStepText" class="mt-2 text-sm font-semibold text-slate-500">Menghubungi service ML...</p>
                        </div>
                        <span id="monitorStatusBadge" class="inline-flex w-fit items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-slate-600">
                            <span class="h-2.5 w-2.5 rounded-full bg-slate-400"></span>
                            Memuat
                        </span>
                    </div>

                    <div class="mt-8">
                        <div class="flex items-end justify-between gap-4">
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Estimasi Penyelesaian</p>
                                <p id="monitorProgressLabel" class="mt-1 text-4xl font-black text-on-surface">0%</p>
                            </div>
                            <div class="text-right text-xs font-bold text-slate-500">
                                <p>Berjalan: <span id="monitorElapsed">-</span></p>
                                <p class="mt-1">Update: <span id="monitorUpdatedAt">-</span></p>
                            </div>
                        </div>
                        <div class="mt-5 h-4 overflow-hidden rounded-full bg-surface-container">
                            <div id="monitorProgressBar" class="h-full w-0 rounded-full bg-primary transition-all duration-500"></div>
                        </div>
                    </div>

                    <div class="mt-8 grid gap-3 md:grid-cols-3">
                        <div class="rounded-2xl bg-surface-container p-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Mulai</p>
                            <p id="monitorStartedAt" class="mt-2 text-sm font-bold text-on-surface">-</p>
                        </div>
                        <div class="rounded-2xl bg-surface-container p-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Selesai</p>
                            <p id="monitorFinishedAt" class="mt-2 text-sm font-bold text-on-surface">-</p>
                        </div>
                        <div class="rounded-2xl bg-surface-container p-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Data Dipakai</p>
                            <p id="monitorRowsUsed" class="mt-2 text-sm font-bold text-on-surface">-</p>
                        </div>
                    </div>

                    <div id="monitorErrorPanel" class="mt-6 hidden rounded-2xl border border-red-200 bg-error-container p-4 text-on-error-container">
                        <p class="text-[11px] font-black uppercase tracking-[0.18em]">Error Retrain</p>
                        <p id="monitorErrorText" class="mt-2 break-words text-sm font-medium"></p>
                    </div>
                </div>

                <aside class="space-y-6">
                    <div class="rounded-3xl bg-white p-6 shadow-lg">
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Kontrol Proses</p>
                        <div class="mt-4 grid gap-3">
                            <button
                                id="refreshStatusButton"
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 px-4 py-3 text-sm font-black text-slate-700 transition hover:bg-slate-50"
                            >
                                <span class="material-symbols-outlined text-lg">refresh</span>
                                Refresh Status
                            </button>
                            <button
                                id="cancelTrainingButton"
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-2xl border-2 border-error bg-error/10 px-4 py-3 text-sm font-black text-error transition hover:bg-error hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
                                disabled
                            >
                                <span class="material-symbols-outlined text-lg">stop_circle</span>
                                Batalkan Retrain
                            </button>
                            <a
                                href="{{ route('admin.models.retrain') }}"
                                class="inline-flex items-center justify-center gap-2 rounded-2xl bg-slate-900 px-4 py-3 text-sm font-black text-white transition hover:bg-slate-800"
                            >
                                <span class="material-symbols-outlined text-lg">model_training</span>
                                Kembali ke Panel
                            </a>
                        </div>
                    </div>

                    <div class="rounded-3xl bg-white p-6 shadow-lg">
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Model Aktif</p>
                        <div class="mt-4 rounded-2xl bg-surface-container p-5">
                            <p class="break-words text-sm font-black text-on-surface">{{ $activeModel?->version_name ?? 'Belum ada model aktif' }}</p>
                            <p class="mt-2 text-xs font-semibold text-slate-500">
                                {{ optional($activeModel?->activated_at ?? $activeModel?->trained_at)->format('d M Y H:i') ?? 'Menunggu model siap' }}
                            </p>
                        </div>
                    </div>

                    <div class="rounded-3xl bg-white p-6 shadow-lg">
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Percobaan Terakhir</p>
                        <div class="mt-4 rounded-2xl bg-surface-container p-5">
                            <p class="break-words text-sm font-black text-on-surface">{{ $latestAttempt?->version_name ?? 'Belum ada percobaan' }}</p>
                            <p class="mt-2 text-xs font-semibold text-slate-500">{{ $latestAttempt?->status ? strtoupper($latestAttempt->status) : '-' }}</p>
                        </div>
                    </div>
                </aside>
            </section>

            <section class="grid grid-cols-1 gap-6 xl:grid-cols-[420px_minmax(0,1fr)]">
                <div class="rounded-3xl bg-white p-6 shadow-lg">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Tahapan</p>
                            <h3 class="mt-1 text-lg font-extrabold text-on-surface">Pipeline Training</h3>
                        </div>
                        <span id="monitorStepCounter" class="rounded-full bg-primary-container px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-on-primary-container">0/13</span>
                    </div>
                    <div class="space-y-3">
                        @foreach ($steps as $stepKey => $stepLabel)
                            <div class="flex items-center gap-3 rounded-2xl bg-surface-container-low p-3" data-training-step="{{ $stepKey }}">
                                <span class="training-step-dot flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-black text-slate-500">
                                    {{ $loop->iteration }}
                                </span>
                                <p class="training-step-label text-sm font-bold text-slate-500">{{ $stepLabel }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-3xl bg-white p-6 shadow-lg">
                    <div class="mb-4">
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Ringkasan Hasil</p>
                        <h3 class="mt-1 text-lg font-extrabold text-on-surface">Metrik akan muncul setelah training selesai</h3>
                    </div>
                    <div id="monitorResultEmpty" class="rounded-2xl bg-surface-container p-5">
                        <p class="text-sm font-semibold text-slate-600">Belum ada ringkasan hasil dari proses yang sedang dipantau.</p>
                    </div>
                    <div id="monitorResultGrid" class="hidden grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="rounded-2xl bg-surface-container p-5">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Versi Model</p>
                            <p id="resultVersionName" class="mt-2 break-words text-sm font-black text-on-surface">-</p>
                        </div>
                        <div class="rounded-2xl bg-surface-container p-5">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Rows Used</p>
                            <p id="resultRowsUsed" class="mt-2 text-sm font-black text-on-surface">-</p>
                        </div>
                        <div class="rounded-2xl bg-surface-container p-5">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">CatBoost Accuracy</p>
                            <p id="resultCatboostAccuracy" class="mt-2 text-sm font-black text-on-surface">-</p>
                        </div>
                        <div class="rounded-2xl bg-surface-container p-5">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Naive Bayes Accuracy</p>
                            <p id="resultNaiveBayesAccuracy" class="mt-2 text-sm font-black text-on-surface">-</p>
                        </div>
                    </div>
                </div>
            </section>

            <x-admin.footer />
        </div>
    </div>
</main>
@endsection

@push('scripts')
<script>
    (() => {
        const statusEndpoint = @json(route('admin.models.retrain.status'));
        const cancelEndpoint = @json(route('admin.models.retrain.cancel'));
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const initialPayload = @json($initialTrainingStatus);
        const orderedSteps = @json(array_keys($steps));
        const stepLabels = @json($steps);
        const terminalStatuses = ['completed', 'failed', 'cancelled'];
        let pollTimer = null;

        const $ = (id) => document.getElementById(id);

        const statusText = {
            idle: 'Tidak Ada Proses',
            running: 'Sedang Berjalan',
            cancelling: 'Membatalkan',
            completed: 'Selesai',
            failed: 'Gagal',
            cancelled: 'Dibatalkan',
            unknown: 'Tidak Diketahui',
        };

        const statusTitle = {
            idle: 'Belum ada retrain berjalan',
            running: 'Retrain model sedang berjalan',
            cancelling: 'Retrain sedang dibatalkan',
            completed: 'Retrain selesai',
            failed: 'Retrain gagal',
            cancelled: 'Retrain dibatalkan',
            unknown: 'Status retrain tidak tersedia',
        };

        const statusTone = {
            idle: 'bg-slate-100 text-slate-600',
            running: 'bg-blue-50 text-primary',
            cancelling: 'bg-amber-50 text-amber-700',
            completed: 'bg-emerald-50 text-emerald-700',
            failed: 'bg-error-container text-on-error-container',
            cancelled: 'bg-slate-100 text-slate-600',
            unknown: 'bg-error-container text-on-error-container',
        };

        const dotTone = {
            idle: 'bg-slate-400',
            running: 'bg-primary',
            cancelling: 'bg-amber-500',
            completed: 'bg-emerald-500',
            failed: 'bg-error',
            cancelled: 'bg-slate-500',
            unknown: 'bg-error',
        };

        function trainingFrom(payload) {
            return payload?.training ?? {
                status: 'unknown',
                current_step: null,
                step_index: 0,
                total_steps: orderedSteps.length,
                progress_percent: 0,
                elapsed_seconds: null,
                started_at: null,
                finished_at: null,
                error: payload?.detail ?? payload?.message ?? null,
                extra_info: {},
                result_summary: null,
                is_cancellable: false,
            };
        }

        function formatSeconds(seconds) {
            if (seconds === null || seconds === undefined) {
                return '-';
            }

            const total = Math.max(0, Number(seconds));
            const minutes = Math.floor(total / 60);
            const rest = Math.round(total % 60);

            return minutes > 0 ? `${minutes}m ${rest}s` : `${rest}s`;
        }

        function formatDate(value) {
            if (!value) {
                return '-';
            }

            const date = new Date(value);
            if (Number.isNaN(date.getTime())) {
                return '-';
            }

            return new Intl.DateTimeFormat('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            }).format(date);
        }

        function formatMetric(value) {
            if (value === null || value === undefined || value === '') {
                return '-';
            }

            const number = Number(value);
            return Number.isNaN(number) ? String(value) : number.toFixed(4);
        }

        function renderSteps(training) {
            const currentIndex = orderedSteps.indexOf(training.current_step);
            const stepIndex = currentIndex >= 0 ? currentIndex : Number(training.step_index ?? 0);
            const status = training.status ?? 'unknown';

            document.querySelectorAll('[data-training-step]').forEach((node, index) => {
                const dot = node.querySelector('.training-step-dot');
                const label = node.querySelector('.training-step-label');
                const isActive = index === stepIndex && ['running', 'cancelling'].includes(status);
                const isDone = status === 'completed' || index < stepIndex;

                node.className = `flex items-center gap-3 rounded-2xl p-3 ${isActive ? 'bg-primary-container' : 'bg-surface-container-low'}`;
                dot.className = `training-step-dot flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-black ${isDone ? 'bg-emerald-500 text-white' : isActive ? 'bg-primary text-white' : 'bg-slate-200 text-slate-500'}`;
                label.className = `training-step-label text-sm font-bold ${isDone || isActive ? 'text-on-surface' : 'text-slate-500'}`;
            });

            $('monitorStepCounter').textContent = `${Math.min(stepIndex + 1, orderedSteps.length)}/${orderedSteps.length}`;
        }

        function renderResult(summary) {
            if (!summary) {
                $('monitorResultEmpty').classList.remove('hidden');
                $('monitorResultGrid').classList.add('hidden');
                $('monitorResultGrid').classList.remove('grid');
                return;
            }

            $('monitorResultEmpty').classList.add('hidden');
            $('monitorResultGrid').classList.remove('hidden');
            $('monitorResultGrid').classList.add('grid');
            $('resultVersionName').textContent = summary.model_version_name ?? '-';
            $('resultRowsUsed').textContent = summary.rows_used ?? '-';
            $('resultCatboostAccuracy').textContent = formatMetric(summary.catboost_accuracy);
            $('resultNaiveBayesAccuracy').textContent = formatMetric(summary.naive_bayes_accuracy);
        }

        function render(payload) {
            const training = trainingFrom(payload);
            const status = training.status ?? 'unknown';
            const progress = Math.max(0, Math.min(100, Number(training.progress_percent ?? 0)));
            const stepLabel = stepLabels[training.current_step] ?? training.current_step ?? '-';
            const summary = training.result_summary;

            $('monitorStatusTitle').textContent = statusTitle[status] ?? statusTitle.unknown;
            $('monitorStepText').textContent = status === 'running' || status === 'cancelling'
                ? `Tahap aktif: ${stepLabel}`
                : `Tahap terakhir: ${stepLabel}`;
            $('monitorStatusBadge').className = `inline-flex w-fit items-center gap-2 rounded-full px-4 py-2 text-xs font-black uppercase tracking-[0.16em] ${statusTone[status] ?? statusTone.unknown}`;
            $('monitorStatusBadge').innerHTML = `<span class="h-2.5 w-2.5 rounded-full ${dotTone[status] ?? dotTone.unknown}"></span>${statusText[status] ?? statusText.unknown}`;
            $('monitorProgressLabel').textContent = `${progress.toFixed(1).replace('.0', '')}%`;
            $('monitorProgressBar').style.width = `${progress}%`;
            $('monitorElapsed').textContent = formatSeconds(training.elapsed_seconds);
            $('monitorUpdatedAt').textContent = formatDate(new Date().toISOString());
            $('monitorStartedAt').textContent = formatDate(training.started_at);
            $('monitorFinishedAt').textContent = formatDate(training.finished_at);
            $('monitorRowsUsed').textContent = summary?.rows_used ?? training.extra_info?.rows_after_cleaning ?? training.extra_info?.total_rows ?? '-';

            const errorText = training.error ?? payload?.detail ?? null;
            $('monitorErrorPanel').classList.toggle('hidden', !errorText);
            $('monitorErrorText').textContent = errorText ?? '';

            $('cancelTrainingButton').disabled = !(training.is_cancellable || status === 'running');
            renderSteps(training);
            renderResult(summary);

            if (!terminalStatuses.includes(status)) {
                schedulePoll(status === 'idle' ? 8000 : 3000);
            }
        }

        async function fetchStatus() {
            window.clearTimeout(pollTimer);

            try {
                const response = await fetch(statusEndpoint, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                const payload = await response.json();
                render(payload);
            } catch (error) {
                render({
                    status: 'error',
                    detail: error instanceof Error ? error.message : 'Status retrain gagal dibaca.',
                });
                schedulePoll(8000);
            }
        }

        async function cancelTraining() {
            if (!window.confirm('Batalkan retrain yang sedang berjalan? Proses akan berhenti pada checkpoint berikutnya.')) {
                return;
            }

            $('cancelTrainingButton').disabled = true;

            try {
                const response = await fetch(cancelEndpoint, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    credentials: 'same-origin',
                });
                const payload = await response.json();
                render(payload);
            } catch (error) {
                render({
                    status: 'error',
                    detail: error instanceof Error ? error.message : 'Pembatalan retrain gagal dikirim.',
                });
            }
        }

        function schedulePoll(delay) {
            window.clearTimeout(pollTimer);
            pollTimer = window.setTimeout(fetchStatus, delay);
        }

        $('refreshStatusButton').addEventListener('click', fetchStatus);
        $('cancelTrainingButton').addEventListener('click', cancelTraining);
        render(initialPayload);
    })();
</script>
@endpush
