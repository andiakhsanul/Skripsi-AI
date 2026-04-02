<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('auth.login');
});

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/
Route::get('/login',  fn () => view('auth.login'))->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware('auth')->get('/dashboard', function (Request $request) {
    return view('dashboard', [
        'user' => $request->user(),
    ]);
})->name('dashboard');

Route::middleware(['auth', 'role.admin'])
    ->get('/admin/dashboard', [AdminDashboardController::class, 'index'])
    ->name('admin.dashboard');
