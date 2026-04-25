<?php

use App\Http\Controllers\Api\Student\ApplicationController as StudentApplicationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (Mahasiswa)
|--------------------------------------------------------------------------
|
| File ini hanya untuk endpoint JSON yang dipakai oleh mahasiswa
| (mobile / external client). Semua admin actions menggunakan route web
| (lihat routes/web.php).
*/
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::prefix('student')->middleware('role.student')->group(function (): void {
        Route::post('/applications', [StudentApplicationController::class, 'store']);
        Route::get('/applications', [StudentApplicationController::class, 'index']);
        Route::get('/applications/{id}', [StudentApplicationController::class, 'show']);
    });
});
