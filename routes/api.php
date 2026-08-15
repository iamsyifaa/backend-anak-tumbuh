<?php

use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\Auth\AccountSecurityController;
use App\Http\Controllers\Auth\AdminPasswordResetController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\HabitConfigController;
use App\Http\Controllers\HabitController;
use App\Http\Controllers\PointConfigController;
use App\Http\Controllers\PointController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\StudentImportController;
use App\Http\Controllers\StudentQrController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\TrophyController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\StudentQrLoginController;

// ── AUTH-001 ────────────────────────────────────────────────────────────────
Route::post('/login', [AuthController::class, 'login']);

// ── BE-004 (Student QR Login) ────────────────────────────────────────────────
Route::post('/auth/qr-login', StudentQrLoginController::class);

// ── AUTH-003 (publik, belum login) ──────────────────────────────────────────
Route::post('/forgot-password', [AccountSecurityController::class, 'forgotPassword']);
Route::post('/reset-password', [AccountSecurityController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // ── AUTH-003 ─────────────────────────────────────────────────────────
    Route::middleware('role.not:siswa')->post('/account/change-password', [AccountSecurityController::class, 'changePassword']);

    Route::post('/users/{user}/force-reset-password', [AdminPasswordResetController::class, 'forceResetPassword']);

    // ── ORG-001 / ORG-002 ───────────────────────────────────────────────
    Route::apiResource('schools', SchoolController::class);

    Route::prefix('schools/{school}')->group(function () {
        Route::apiResource('academic-years', AcademicYearController::class)
            ->parameters(['academic-years' => 'academicYear']);

        Route::post('academic-years/{academicYear}/activate', [AcademicYearController::class, 'activate']);

        // ── AUTH-004 ─────────────────────────────────────────────────────
        Route::apiResource('habit-configs', HabitConfigController::class)
            ->except(['show'])
            ->parameters(['habit-configs' => 'habitConfig']);

        Route::post('habit-configs/{habitConfig}/publish', [HabitConfigController::class, 'publish']);

        // ── SEC-005 ──────────────────────────────────────────────────────
        Route::apiResource('point-configs', PointConfigController::class)
            ->except(['show'])
            ->parameters(['point-configs' => 'pointConfig']);

        Route::post('point-configs/{pointConfig}/publish', [PointConfigController::class, 'publish']);
    });

    // ── SEC-006 ──────────────────────────────────────────────────────────
    Route::post('submissions', [SubmissionController::class, 'store']);
    Route::get('submissions/{submission}', [SubmissionController::class, 'show']);
    Route::patch('submissions/{submission}/lock', [SubmissionController::class, 'lock']);

    // ── STUDENT IMPORT ───────────────────────────────────────────────────
    Route::post('/students/import/preview', [StudentImportController::class, 'preview']);
    Route::post('/students/import/commit', [StudentImportController::class, 'commit']);

    // ── STUDENT QR (MASTER-003) ──────────────────────────────────────────────
    Route::post('/students/{studentProfile}/qr/generate', [StudentQrController::class, 'generate']);
    Route::delete('/students/{studentProfile}/qr', [StudentQrController::class, 'revoke']);
    Route::post('/students/qr/generate-bulk', [StudentQrController::class, 'generateBulk']);
    Route::post('/students/qr/export-pdf', [StudentQrController::class, 'exportPdf']);

    // ── MASTER-004 (Habit Master Configuration) ─────────────────────────────
    Route::get('/habits', [HabitController::class, 'index']);
    Route::post('/habits', [HabitController::class, 'store']);
    Route::put('/habits/{habit}', [HabitController::class, 'update']);
    Route::delete('/habits/{habit}', [HabitController::class, 'destroy']);

    Route::post('/habits/{habit}/indicators', [HabitController::class, 'storeIndicator']);
    Route::put('/indicators/{indicator}', [HabitController::class, 'updateIndicator']);
    Route::delete('/indicators/{indicator}', [HabitController::class, 'destroyIndicator']);

    Route::put('/indicator-options/{option}', [HabitController::class, 'updateOption']);
    Route::delete('/indicator-options/{option}', [HabitController::class, 'destroyOption']);

    Route::post('/indicators/{indicator}/conditions', [HabitController::class, 'storeCondition']);
    Route::delete('/indicator-conditions/{condition}', [HabitController::class, 'destroyCondition']);

    // ── MASTER-005 (Point Engine & History) ──────────────────────────────────
    Route::get('/students/{studentProfile}/points', [PointController::class, 'studentPoints']);

    // ── MASTER-006 (Trophies & Milestones) ──────────────────────────────────
    Route::get('/trophies', [TrophyController::class, 'index']);
    Route::get('/students/{studentProfile}/trophies', [TrophyController::class, 'studentTrophies']);
});

// Route domain lain (school, student, activity, dll) ditambahkan oleh task
// masing-masing (ORG-001 dst.) — tidak didefinisikan di sini agar tidak
// tabrakan/merge conflict antar anggota tim.