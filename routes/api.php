<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

// ── AUTH-001 ────────────────────────────────────────────────────────────────
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});

// Route domain lain (school, student, activity, dll) ditambahkan oleh task
// masing-masing (ORG-001 dst.) — tidak didefinisikan di sini agar tidak
// tabrakan/merge conflict antar anggota tim.