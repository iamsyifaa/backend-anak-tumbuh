<?php

use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\Auth\AccountSecurityController;
use App\Http\Controllers\Auth\AdminPasswordResetController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\StudentImportController;
use Illuminate\Support\Facades\Route;

// ── AUTH-001 ────────────────────────────────────────────────────────────────
Route::post('/login', [AuthController::class, 'login']);

// ── AUTH-003 (Publik, belum login) ──────────────────────────────────────────
Route::post('/forgot-password', [AccountSecurityController::class, 'forgotPassword']);
Route::post('/reset-password', [AccountSecurityController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // ── AUTH-003 (Proteksi Akun) ─────────────────────────────────────────────
    // Sengaja TIDAK didaftarkan untuk role 'siswa' — dicek eksplisit di middleware
    Route::middleware('role.not:siswa')->post('/account/change-password', [AccountSecurityController::class, 'changePassword']);

    Route::post('/users/{user}/force-reset-password', [AdminPasswordResetController::class, 'forceResetPassword']);

    // ── ORG-001 / ORG-002 (Sekolah & Tahun Ajaran) ──────────────────────────
    Route::apiResource('schools', SchoolController::class);

    Route::prefix('schools/{school}')->group(function () {
        Route::apiResource('academic-years', AcademicYearController::class)
            ->parameters(['academic-years' => 'academicYear']);

        Route::post('academic-years/{academicYear}/activate', [AcademicYearController::class, 'activate']);
    });

    // ── STUDENT IMPORT ───────────────────────────────────────────────────────
    Route::post('/students/import/preview', [StudentImportController::class, 'preview']);
    Route::post('/students/import/commit', [StudentImportController::class, 'commit']);
});

// Route domain lain (school, student, activity, dll) ditambahkan oleh task
// masing-masing (ORG-001 dst.) — tidak didefinisikan di sini agar tidak
// tabrakan/merge conflict antar anggota tim.