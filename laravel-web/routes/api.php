<?php

use App\Http\Controllers\AdminApplicationController;
use App\Http\Controllers\AdminModelController;
use App\Http\Controllers\AdminParameterSchemaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SpkController;
use App\Http\Controllers\StudentApplicationController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register-student', [AuthController::class, 'registerStudent']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth.token')->group(function (): void {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::prefix('student')->middleware('role:mahasiswa')->group(function (): void {
        Route::post('/applications', [StudentApplicationController::class, 'store']);
        Route::get('/applications', [StudentApplicationController::class, 'index']);
        Route::get('/applications/{id}', [StudentApplicationController::class, 'show']);
        Route::post('/applications/{id}/document', [StudentApplicationController::class, 'uploadDocument']);
    });

    Route::prefix('admin')->middleware('role:admin')->group(function (): void {
        Route::post('/parameters/import', [AdminParameterSchemaController::class, 'import']);
        Route::get('/parameters/schema-versions', [AdminParameterSchemaController::class, 'versions']);

        Route::get('/applications', [AdminApplicationController::class, 'index']);
        Route::get('/applications/{id}', [AdminApplicationController::class, 'show']);
        Route::post('/applications/{id}/verify', [AdminApplicationController::class, 'verify']);
        Route::post('/applications/{id}/reject', [AdminApplicationController::class, 'reject']);

        Route::post('/models/retrain', [AdminModelController::class, 'retrain']);
    });
});

// Endpoint lama dipertahankan untuk kompatibilitas integrasi awal.
Route::post('/spk/run-prediction', [SpkController::class, 'runPrediction']);
Route::post('/spk/retrain-model', [SpkController::class, 'triggerRetrain']);
