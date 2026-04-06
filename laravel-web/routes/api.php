<?php

use App\Http\Controllers\Api\Admin\ApplicationController as AdminApplicationController;
use App\Http\Controllers\Api\Admin\ModelController as AdminModelController;
use App\Http\Controllers\Api\Admin\ParameterSchemaController as AdminParameterSchemaController;
use App\Http\Controllers\Api\Admin\StatsController as AdminStatsController;
use App\Http\Controllers\Api\Legacy\SpkController;
use App\Http\Controllers\Api\Student\ApplicationController as StudentApplicationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SaaS API Routes
|--------------------------------------------------------------------------
|
| File ini hanya untuk endpoint JSON proses bisnis SaaS.
| Auth user/admin memakai session Laravel dari route web.
|
*/
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::prefix('student')->middleware('role.student')->group(function (): void {
        Route::post('/applications', [StudentApplicationController::class, 'store']);
        Route::get('/applications', [StudentApplicationController::class, 'index']);
        Route::get('/applications/{id}', [StudentApplicationController::class, 'show']);
    });

    Route::prefix('admin')->middleware('role.admin')->group(function (): void {
        Route::get('/stats', [AdminStatsController::class, 'index']);
        Route::post('/parameters/import', [AdminParameterSchemaController::class, 'import']);
        Route::get('/parameters/schema-versions', [AdminParameterSchemaController::class, 'versions']);

        Route::post('/applications/run-predictions', [AdminApplicationController::class, 'runPredictions']);
        Route::get('/applications', [AdminApplicationController::class, 'index']);
        Route::get('/applications/{id}', [AdminApplicationController::class, 'show']);
        Route::post('/applications/{id}/verify', [AdminApplicationController::class, 'verify']);
        Route::post('/applications/{id}/reject', [AdminApplicationController::class, 'reject']);
        Route::get('/applications/{id}/training-data', [AdminApplicationController::class, 'showTrainingData']);
        Route::put('/applications/{id}/training-data', [AdminApplicationController::class, 'updateTrainingData']);
        Route::post('/training/sync-finalized', [AdminApplicationController::class, 'syncFinalizedTraining']);
        Route::post('/models/retrain', [AdminModelController::class, 'retrain']);
        Route::post('/models/{modelVersion}/activate', [AdminModelController::class, 'activate']);
    });

    Route::prefix('spk')->middleware('role.admin')->group(function (): void {
        Route::post('/run-prediction', [SpkController::class, 'runPrediction']);
        Route::post('/retrain-model', [SpkController::class, 'triggerRetrain']);
    });
});
