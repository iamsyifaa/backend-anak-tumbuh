<?php

use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\Auth\AccountSecurityController;
use App\Http\Controllers\Auth\AdminPasswordResetController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\StudentQrLoginController;
use App\Http\Controllers\AwardController;
use App\Http\Controllers\BadgeController;
use App\Http\Controllers\CertificateTemplateController;
use App\Http\Controllers\HabitConfigController;
use App\Http\Controllers\HabitController;
use App\Http\Controllers\PointConfigController;
use App\Http\Controllers\PointController;
use App\Http\Controllers\PrincipalDashboardController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\RombelController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\SchoolFeatureSettingController;
use App\Http\Controllers\StudentAchievementController;
use App\Http\Controllers\StudentImportController;
use App\Http\Controllers\StudentQrController;
use App\Http\Controllers\StudentSelfController;
use App\Http\Controllers\SubmissionController;
use Illuminate\Support\Facades\Route;

// ── AUTH-001 ────────────────────────────────────────────────────────────────
// [SEC-012] throttle:5,1 — maksimal 5 percobaan/menit per kombinasi IP+session
// bawaan Laravel — mitigasi brute-force credential (username/password guessing).
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

// ── BE-004 (Student QR Login) ────────────────────────────────────────────────
// [SEC-012] throttle:5,1 — mitigasi brute-force token QR login
Route::post('/auth/qr-login', StudentQrLoginController::class)->middleware('throttle:5,1');

// ── AUTH-003 (publik, belum login) ──────────────────────────────────────────
// [SEC-012] throttle lebih ketat (3/menit) — endpoint ini bisa dipakai untuk
// enumerasi akun/spam token reset kalau tidak dibatasi, walau pesan errornya
// sendiri sudah generik (AUTH-001/003 anti-enumeration).
Route::post('/forgot-password', [AccountSecurityController::class, 'forgotPassword'])->middleware('throttle:3,1');
Route::post('/reset-password', [AccountSecurityController::class, 'resetPassword'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // ── AUTH-003 ─────────────────────────────────────────────────────────
    // Sengaja TIDAK didaftarkan untuk role 'siswa' — dicek eksplisit di
    // middleware inline di bawah, bukan cuma diasumsikan lewat frontend.
    // [SEC-012] throttle:5,1 juga di sini — user yang sudah login pun bisa
    // jadi vektor brute-force "current_password" kalau tokennya dicuri.
    Route::middleware(['role.not:siswa', 'throttle:5,1'])->post('/account/change-password', [AccountSecurityController::class, 'changePassword']);

    Route::post('/users/{user}/force-reset-password', [AdminPasswordResetController::class, 'forceResetPassword'])->middleware('throttle:10,1');

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

        // ── SEC-007 ──────────────────────────────────────────────────────
        Route::get('feature-settings', [SchoolFeatureSettingController::class, 'show']);
        Route::put('feature-settings', [SchoolFeatureSettingController::class, 'update']);
    });

    // ── SEC-006 ──────────────────────────────────────────────────────────
    Route::post('submissions', [SubmissionController::class, 'store']);
    Route::get('submissions/{submission}', [SubmissionController::class, 'show']);
    Route::patch('submissions/{submission}/lock', [SubmissionController::class, 'lock']);

    // ── SEC-007 ──────────────────────────────────────────────────────────
    Route::get('student-badges/{studentBadge}', [StudentAchievementController::class, 'showBadge']);
    Route::get('student-awards/{studentAward}', [StudentAchievementController::class, 'showAward']);

    // ── SEC-008 ──────────────────────────────────────────────────────────
    Route::get('student/me', [StudentSelfController::class, 'me']);
    Route::get('certificates/{certificate}', [StudentSelfController::class, 'showCertificate']);
    Route::get('students/{studentProfile}', [StudentSelfController::class, 'showProfile']);

    // ── SEC-009 ──────────────────────────────────────────────────────────
    Route::get('rombels/{rombel}', [RombelController::class, 'show']);
    Route::post('rombels/{rombel}/assign-teacher', [RombelController::class, 'assignTeacher']);

    // ── SEC-010 ──────────────────────────────────────────────────────────
    // 'read-only' middleware = defense-in-depth: MASTER-010 (Anggota B)
    // MENAMBAH endpoint statistik di grup prefix ini, dan middleware ini
    // otomatis menolak method non-GET/HEAD apapun yang tidak sengaja
    // terdaftar di sini — tidak bergantung semata pada Policy.
    Route::prefix('schools/{school}/dashboard')->middleware('read-only')->group(function () {
        Route::get('overview', [PrincipalDashboardController::class, 'overview']);
    });

    // ── SEC-011 ──────────────────────────────────────────────────────────
    Route::post('report-exports/{reportExport}/link', [ReportExportController::class, 'generateLink']);

    // ── STUDENT IMPORT ───────────────────────────────────────────────────
    Route::post('/students/import/preview', [StudentImportController::class, 'preview']);
    Route::post('/students/import/commit', [StudentImportController::class, 'commit']);

    // ── STUDENT QR (MASTER-003) ──────────────────────────────────────────
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

    // ── MASTER-006 (Badge & Award) ───────────────────────────────────────────
    Route::get('/badges', [BadgeController::class, 'index']);
    Route::post('/badges', [BadgeController::class, 'store']);
    Route::put('/badges/{badge}', [BadgeController::class, 'update']);
    Route::delete('/badges/{badge}', [BadgeController::class, 'destroy']);
    Route::get('/students/{studentProfile}/badges', [BadgeController::class, 'studentBadges']);

    // ── MASTER-006 (Award Master & Give) ─────────────────────────────────────
    Route::get('/awards', [AwardController::class, 'index']);
    Route::post('/awards', [AwardController::class, 'store']);
    Route::put('/awards/{award}', [AwardController::class, 'update']);
    Route::delete('/awards/{award}', [AwardController::class, 'destroy']);
    Route::post('/students/{studentProfile}/awards', [AwardController::class, 'give']);
    Route::get('/students/{studentProfile}/awards', [AwardController::class, 'studentAwards']);

    // ── MASTER-007 (Certificate Template) ────────────────────────────────
    Route::get('/certificate-templates', [CertificateTemplateController::class, 'index']);
    Route::post('/certificate-templates', [CertificateTemplateController::class, 'store']);
    Route::put('/certificate-templates/{certificateTemplate}', [CertificateTemplateController::class, 'update']);
    Route::delete('/certificate-templates/{certificateTemplate}', [CertificateTemplateController::class, 'destroy']);
});

// Route download TERPISAH dari grup auth:sanctum di atas — signed URL
// membawa otorisasinya sendiri lewat signature, TAPI tetap butuh user login
// (Policy cek $request->user()), jadi tetap di-guard auth:sanctum, hanya
// modelnya beda: middleware 'signed' Laravel bawaan yang validasi signature.
Route::middleware(['auth:sanctum', 'signed'])
    ->get('report-exports/{reportExport}/download', [ReportExportController::class, 'download'])
    ->name('report-exports.download');