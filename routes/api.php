<?php

use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\Auth\AccountSecurityController;
use App\Http\Controllers\Auth\AdminPasswordResetController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\StudentQrLoginController;
use App\Http\Controllers\AwardController;
use App\Http\Controllers\BadgeController;
use App\Http\Controllers\CertificateDownloadController;
use App\Http\Controllers\CertificateTemplateController;
use App\Http\Controllers\EducationLevelController;
use App\Http\Controllers\HabitConfigController;
use App\Http\Controllers\HabitController;
use App\Http\Controllers\LevelThresholdController;
use App\Http\Controllers\PointConfigController;
use App\Http\Controllers\PointController;
use App\Http\Controllers\PrincipalDashboardController;
use App\Http\Controllers\Report\HabitInitiativeReportController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\ReportGenerateController;
use App\Http\Controllers\RombelController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\SchoolFeatureSettingController;
use App\Http\Controllers\SchoolReportExportController;
use App\Http\Controllers\StudentAchievementController;
use App\Http\Controllers\StudentDashboardController;
use App\Http\Controllers\StudentImportController;
use App\Http\Controllers\StudentQrController;
use App\Http\Controllers\StudentSelfController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\TeacherController;
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

        // ── EDUCATION LEVEL (Jenjang Pendidikan) ────────────────────────
        // TODO: belum ada di dokumen resmi 01_Role_Permission_v2_0, Policy
        // masih sementara cek role langsung (bukan config('permissions')).
        // Lihat catatan di EducationLevelPolicy.
        Route::apiResource('education-levels', EducationLevelController::class)
            ->parameters(['education-levels' => 'educationLevel']);

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

        // ── SCHOOL REPORTS EXPORT ─────────────────────────────────────────
        Route::post('reports/export', [SchoolReportExportController::class, 'store']);
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
    Route::get('students/{studentProfile}', [StudentSelfController::class, 'showProfile']);

    // ── MASTER-008 (Student API — dashboard read endpoints) ───────────────
    Route::get('student/me/history', [StudentDashboardController::class, 'history']);
    Route::get('student/me/achievements', [StudentDashboardController::class, 'achievements']);
    Route::get('student/me/ranking', [StudentDashboardController::class, 'ranking']);
    Route::get('student/me/progress', [StudentDashboardController::class, 'progress']);

    // ── SEC-009 (Rombel CRUD & Actions) ──────────────────────────────────
    Route::get('rombels', [RombelController::class, 'index']);
    Route::post('rombels', [RombelController::class, 'store']);
    Route::get('rombels/{rombel}', [RombelController::class, 'show']);
    Route::put('rombels/{rombel}', [RombelController::class, 'update']);
    Route::delete('rombels/{rombel}', [RombelController::class, 'destroy']);
    Route::post('rombels/{rombel}/assign-teacher', [RombelController::class, 'assignTeacher']);

    // ── MASTER-009 (Teacher API — monitoring rombel, READ ONLY) ───────────
    // TIDAK ADA route input/rekap manual di sini maupun manapun di sistem.
    Route::prefix('teacher/rombel')->group(function () {
        Route::get('students', [TeacherController::class, 'students']);
        Route::get('students/{studentProfile}', [TeacherController::class, 'studentDetail']);
        Route::get('students/{studentProfile}/activity', [TeacherController::class, 'studentActivity']);
        Route::get('students/{studentProfile}/progress', [TeacherController::class, 'studentProgress']);
        Route::get('students/{studentProfile}/achievements', [TeacherController::class, 'studentAchievements']);
        Route::get('export', [TeacherController::class, 'export']);
    });

    // ── SEC-010 / MASTER-010 (School Analytics API) ──────────────────────
    // 'read-only' middleware = defense-in-depth: MASTER-010 (Anggota B)
    // MENAMBAH endpoint statistik di grup prefix ini, dan middleware ini
    // otomatis menolak method non-GET/HEAD apapun yang tidak sengaja
    // terdaftar di sini — tidak bergantung semata pada Policy.
    Route::prefix('schools/{school}/dashboard')->middleware('read-only')->group(function () {
        Route::get('overview', [PrincipalDashboardController::class, 'overview']);
        Route::get('trend', [PrincipalDashboardController::class, 'trend']);
        Route::get('rombels/{rombel}', [PrincipalDashboardController::class, 'rombelDetail']);
    });

    // ── MASTER-011 & SEC-011 (Report Exports) ───────────────────────────
    Route::post('report-exports', [ReportGenerateController::class, 'store']);
    Route::post('report-exports/{reportExport}/link', [ReportExportController::class, 'generateLink']);

    // ── BE-012 (Report Filter Kebiasaan & Inisiatif) ───────────────
    Route::middleware('read-only')->get('reports/habit-initiative', HabitInitiativeReportController::class);

    // ── STUDENT IMPORT ───────────────────────────────────────────────────
    // [OPS-001] Import siswa cuma boleh Super Admin & Kepala Sekolah — Wali
    // Kelas dan Siswa TIDAK BOLEH bikin akun siswa baru sembarangan lewat
    // endpoint ini. StudentImportUploadRequest::authorize() masih return
    // true (TODO lama, belum ganti ke Gate/Policy), jadi proteksi role
    // WAJIB ditegakkan di level route ini sebagai defense-in-depth.
    Route::middleware('role.not:wali_kelas,siswa')->group(function () {
        Route::post('/students/import/preview', [StudentImportController::class, 'preview']);
        Route::post('/students/import/commit', [StudentImportController::class, 'commit']);
    });

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

    // ── MASTER-007 (Certificate Template & Link) ────────────────────────
    Route::get('/certificate-templates', [CertificateTemplateController::class, 'index']);
    Route::post('/certificate-templates', [CertificateTemplateController::class, 'store']);
    Route::put('/certificate-templates/{certificateTemplate}', [CertificateTemplateController::class, 'update']);
    Route::delete('/certificate-templates/{certificateTemplate}', [CertificateTemplateController::class, 'destroy']);
    Route::post('certificates/{certificate}/link', [CertificateDownloadController::class, 'generateLink']);

    // ── Level Threshold Configuration (Super Admin only) ──────────────
    Route::middleware('role.not:kepala_sekolah,wali_kelas,siswa')->group(function () {
        Route::get('/level-thresholds', [LevelThresholdController::class, 'index']);
        Route::post('/level-thresholds', [LevelThresholdController::class, 'store']);
        Route::put('/level-thresholds/{levelThreshold}', [LevelThresholdController::class, 'update']);
        Route::delete('/level-thresholds/{levelThreshold}', [LevelThresholdController::class, 'destroy']);
    });
});

// ── Signed Download Routes ───────────────────────────────────────────────────
// Route download terpisah dari grup auth:sanctum utama — signed URL membawa otorisasinya
// sendiri via signature, tapi tetap dicek auth & signature oleh middleware Laravel.
Route::middleware(['auth:sanctum', 'signed'])
    ->get('report-exports/{reportExport}/download', [ReportExportController::class, 'download'])
    ->name('report-exports.download');

Route::middleware(['auth:sanctum', 'signed'])
    ->get('certificates/{certificate}/download', [CertificateDownloadController::class, 'download'])
    ->name('certificates.download');