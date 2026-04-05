<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Web\Admin\ApplicationReviewController as AdminApplicationReviewController;
use App\Http\Controllers\Web\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Web\Admin\ModelRetrainController as AdminModelRetrainController;
use App\Http\Controllers\Web\Admin\TrainingDataCorrectionController as AdminTrainingDataCorrectionController;
use App\Http\Controllers\Web\DashboardRedirectController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\Student\ApplicationController as StudentApplicationWebController;
use App\Http\Controllers\Web\Student\DashboardController as StudentDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/
Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardRedirectController::class)->name('dashboard');

    Route::middleware('role.student')->prefix('student')->group(function (): void {
        Route::get('/dashboard', [StudentDashboardController::class, 'index'])->name('student.dashboard');
        Route::get('/applications/create', [StudentApplicationWebController::class, 'create'])->name('student.applications.create');
        Route::post('/applications', [StudentApplicationWebController::class, 'store'])->name('student.applications.store');
        Route::get('/applications/{application}/edit', [StudentApplicationWebController::class, 'edit'])->name('student.applications.edit');
        Route::put('/applications/{application}', [StudentApplicationWebController::class, 'update'])->name('student.applications.update');
        Route::get('/applications/{application}', [StudentApplicationWebController::class, 'show'])->name('student.applications.show');
    });

    Route::middleware('role.admin')->prefix('admin')->group(function (): void {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
        Route::post('/applications/run-predictions', [AdminApplicationReviewController::class, 'runPredictions'])->name('admin.applications.run-predictions');
        Route::get('/applications/{application}', [AdminApplicationReviewController::class, 'show'])->name('admin.applications.show');
        Route::post('/applications/{application}/refresh-prediction', [AdminApplicationReviewController::class, 'refreshPrediction'])->name('admin.applications.refresh-prediction');
        Route::post('/applications/{application}/verify', [AdminApplicationReviewController::class, 'verify'])->name('admin.applications.verify');
        Route::post('/applications/{application}/reject', [AdminApplicationReviewController::class, 'reject'])->name('admin.applications.reject');
        Route::get('/applications/{application}/training-data', [AdminTrainingDataCorrectionController::class, 'show'])->name('admin.training-data.show');
        Route::put('/applications/{application}/training-data', [AdminTrainingDataCorrectionController::class, 'update'])->name('admin.training-data.update');
        Route::get('/models/retrain', [AdminModelRetrainController::class, 'index'])->name('admin.models.retrain');
        Route::post('/models/retrain/sync-training', [AdminModelRetrainController::class, 'syncTraining'])->name('admin.models.retrain.sync-training');
        Route::post('/models/retrain/run', [AdminModelRetrainController::class, 'retrain'])->name('admin.models.retrain.run');
        Route::post('/models/retrain/{modelVersion}/activate', [AdminModelRetrainController::class, 'activate'])->name('admin.models.retrain.activate');
    });
});
