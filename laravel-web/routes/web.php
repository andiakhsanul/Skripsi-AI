<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Web\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Web\Admin\ModelRetrainController as AdminModelRetrainController;
use App\Http\Controllers\Web\DashboardRedirectController;
use App\Http\Controllers\Web\HomeController;
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
    });

    Route::middleware('role.admin')->prefix('admin')->group(function (): void {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
        Route::get('/models/retrain', [AdminModelRetrainController::class, 'index'])->name('admin.models.retrain');
        Route::post('/models/retrain/sync-training', [AdminModelRetrainController::class, 'syncTraining'])->name('admin.models.retrain.sync-training');
        Route::post('/models/retrain/run', [AdminModelRetrainController::class, 'retrain'])->name('admin.models.retrain.run');
    });
});
