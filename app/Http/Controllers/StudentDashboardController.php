<?php

namespace App\Http\Controllers;

use Illuminate\Support\Collection;
use App\Services\Business\RankingService;
use App\Models\ActivitySubmission;
use App\Models\PointTransaction;
use App\Models\SchoolFeatureSetting;
use App\Models\StudentAward;
use App\Models\StudentBadge;
use App\Models\StudentProfile;
use App\Support\ApiResponse;
use App\Services\Progress\ProgressService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * MASTER-008 — mengikuti pola persis StudentSelfController::me() (SEC-008,
 * Anggota A): identitas SELALU dari $request->user()->studentProfile,
 * TIDAK PERNAH dari route param/query/body. Ini bikin seluruh controller
 * ini IDOR-immune by design — siswa tidak mungkin bisa lihat data siswa
 * lain lewat endpoint-endpoint ini, apapun yang mereka kirim di request.
 *
 * Response contract dijaga stabil (field & tipe konsisten) karena dipakai
 * FE-009 (Anggota D, Student Dashboard) — perubahan struktur di sini
 * berdampak langsung ke frontend, jadi kalau perlu ubah shape, koordinasi
 * dulu, jangan diam-diam.
 */
class StudentDashboardController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly RankingService $rankingService,
        private readonly ProgressService $progressService,
    ) {}

    private function currentStudentProfile(Request $request): StudentProfile
    {
        $profile = $request->user()->studentProfile;

        abort_if($profile === null, 404, 'Profil siswa tidak ditemukan untuk akun ini.');

        return $profile;
    }

    /**
     * GET /api/student/me/history
     * Riwayat pengisian harian, terbaru dulu, paginated.
     */
    public function history(Request $request)
    {
        $profile = $this->currentStudentProfile($request);

        $history = ActivitySubmission::where('student_profile_id', $profile->id)
            ->with('answers.indicator', 'answers.option')
            ->orderByDesc('activity_date')
            ->paginate(15);

        return $this->success($history);
    }

    /**
     * GET /api/student/me/achievements
     * Gabungan badge + award yang sudah didapat siswa, dengan total
     * masing-masing supaya frontend tidak perlu hitung sendiri.
     */
    public function achievements(Request $request)
    {
        $profile = $this->currentStudentProfile($request);

        $badges = StudentBadge::with('badge')
            ->where('student_profile_id', $profile->id)
            ->orderByDesc('awarded_at')
            ->get();

        $awards = StudentAward::with(['award', 'givenBy'])
            ->where('student_profile_id', $profile->id)
            ->orderByDesc('given_at')
            ->get();

        return $this->success([
            'total_badges' => $badges->count(),
            'total_awards' => $awards->count(),
            'badges' => $badges,
            'awards' => $awards,
        ]);
    }

    /**
     * GET /api/student/me/certificates
     */

    /**
     * GET /api/student/me/progress
     *
     * Menutup gap requirement Bagian 25 — siswa berhak melihat
     * "perkembangan" miliknya sendiri, sebelumnya cuma bisa diakses guru
     * lewat TeacherController::studentProgress() (formula berbeda,
     * days_filled/days_since_enrolled). Endpoint ini pakai ProgressService
     * yang sudah ada tapi belum pernah disambungkan ke controller manapun.
     *
     * Query param opsional `?month=YYYY-MM` untuk lihat progress bulan
     * lain; default bulan berjalan.
     *
     * ⚠️ completion_rate formula masih PLACEHOLDER (lihat docblock
     * ProgressService::getMonthlyProgress) — belum tentu sama dengan
     * formula TeacherController::studentProgress(). Ini disengaja untuk
     * gap ini, refactor konsistensi formula didiskusikan terpisah.
     */
    public function progress(Request $request)
    {
        $profile = $this->currentStudentProfile($request);

        $month = $request->query('month')
            ? Carbon::parse($request->query('month'))
            : null;

        $progress = $this->progressService->getMonthlyProgress($profile->id, $month);

        return $this->success($progress);
    }

    /**
     * GET /api/student/me/ranking
     *
     * Hormati SchoolFeatureSetting — kalau ranking dimatikan sekolah,
     * TIDAK menampilkan data sama sekali (bukan cuma disembunyikan di
     * frontend), sesuai requirement "ranking hanya jika feature aktif".
     *
     * ⚠️ Field `class_streak_rank`/dsb TIDAK disediakan — itu depend ke
     * BE-008 (Gamification Engine, Anggota C) yang per 16 Agustus 2026
     * belum dikerjakan. Ranking di bawah murni berbasis akumulasi Poin
     * (yang sudah pasti ada datanya), bukan streak. Kalau nanti BE-008
     * selesai dan streak based ranking dibutuhkan, endpoint ini perlu
     * di-extend, BUKAN dibuat endpoint terpisah (supaya frontend tidak
     * perlu ubah 2 tempat).
     */
    public function ranking(Request $request)
    {
        $profile = $this->currentStudentProfile($request);
        $enrollment = $profile->currentEnrollment()->first();

        abort_if($enrollment === null, 404, 'Siswa belum terdaftar di rombel manapun pada periode aktif.');

        $schoolId = $profile->user->school_id;
        $classEnabled = $this->rankingService->isClassRankingEnabledForSchool($schoolId);
        $cohortEnabled = $this->rankingService->isCohortRankingEnabledForSchool($schoolId);

        if (! $classEnabled && ! $cohortEnabled) {
            return $this->success([
                'ranking_enabled' => false,
                'message' => 'Fitur ranking belum diaktifkan oleh sekolah.',
            ]);
        }

        $result = [
            'ranking_enabled' => true,
            'class_rank' => null,
            'cohort_rank' => null,
        ];

        if ($classEnabled) {
            $result['class_rank'] = $this->buildRankResult(
                $profile,
                $this->rankingService->getRankingsForRombel($enrollment->rombel_id)
            );
        }

            if ($cohortEnabled) {
                $educationLevelId = $enrollment->rombel?->education_level_id;

                $result['cohort_rank'] = $educationLevelId
                    ? $this->buildRankResult(
                        $profile,
                        $this->rankingService->getRankingsForGrade($educationLevelId, now())
                    )
                    : null;
            }

        return $this->success($result);
    }

    private function buildRankResult(StudentProfile $profile, Collection $rankings): array
    {
        $position = $rankings->search(fn ($row) => $row['user_id'] === $profile->user_id);
        $myPoints = $rankings->firstWhere('user_id', $profile->user_id)['total_points'] ?? 0;

        return [
            'rank' => $position === false ? null : $position + 1,
            'total_students' => $rankings->count(),
            'my_points' => $myPoints,
        ];
    }
}
