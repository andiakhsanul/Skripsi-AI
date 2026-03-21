<?php

use App\Http\Controllers\AdminApplicationController;
use App\Http\Controllers\AdminModelController;
use App\Http\Controllers\AdminParameterSchemaController;
use App\Http\Controllers\AdminStatsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SpkController;
use App\Http\Controllers\StudentApplicationController;
use Illuminate\Support\Facades\Route;

// ─── Auth (publik) ────────────────────────────────────────────────────────────
Route::post('/auth/register-student', [AuthController::class, 'registerStudent']);
Route::post('/auth/login', [AuthController::class, 'login']);

// ─── Semua route terautentikasi ────────────────────────────────────────────────
Route::middleware('auth.token')->group(function (): void {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // ── Mahasiswa ──────────────────────────────────────────────────────────────
    Route::prefix('student')->middleware('role:mahasiswa')->group(function (): void {
        // Submit pengajuan baru (raw input + PDF wajib)
        Route::post('/applications', [StudentApplicationController::class, 'store']);
        // Daftar pengajuan milik mahasiswa (status saja)
        Route::get('/applications', [StudentApplicationController::class, 'index']);
        // Detail satu pengajuan (status + log perubahan)
        Route::get('/applications/{id}', [StudentApplicationController::class, 'show']);
    });

    // ── Admin ──────────────────────────────────────────────────────────────────
    Route::prefix('admin')->middleware('role:admin')->group(function (): void {
        // Dashboard stats
        Route::get('/stats', [AdminStatsController::class, 'index']);

        // Parameter schema
        Route::post('/parameters/import', [AdminParameterSchemaController::class, 'import']);
        Route::get('/parameters/schema-versions', [AdminParameterSchemaController::class, 'versions']);

        // Manajemen pengajuan
        Route::get('/applications', [AdminApplicationController::class, 'index']);
        Route::get('/applications/{id}', [AdminApplicationController::class, 'show']);
        Route::post('/applications/{id}/verify', [AdminApplicationController::class, 'verify']);
        Route::post('/applications/{id}/reject', [AdminApplicationController::class, 'reject']);

        // Data training (encoded) — lihat & koreksi manual
        Route::get('/applications/{id}/training-data', [AdminApplicationController::class, 'showTrainingData']);
        Route::put('/applications/{id}/training-data', [AdminApplicationController::class, 'updateTrainingData']);

        // Model
        Route::post('/models/retrain', [AdminModelController::class, 'retrain']);
    });
});

// ─── Legacy endpoints (dipertahankan untuk kompatibilitas) ────────────────────
Route::post('/spk/run-prediction', [SpkController::class, 'runPrediction']);
Route::post('/spk/retrain-model', [SpkController::class, 'triggerRetrain']);
