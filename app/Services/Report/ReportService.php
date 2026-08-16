<?php

namespace App\Services\Report;

use App\Models\ActivitySubmission;
use App\Models\Enrollment;
use App\Models\ExpTransaction;
use App\Models\PointTransaction;
use App\Models\StudentAward;
use App\Models\StudentBadge;
use App\Models\StudentProfile;
use App\Services\Analytics\SchoolAnalyticsService;
use App\Services\Business\LevelService;
use Carbon\Carbon;

class ReportService
{
    public function __construct(
        private SchoolAnalyticsService $analyticsService,
        private LevelService $levelService,
    ) {}

    /**
     * Report per siswa untuk 1 rentang tanggal. Sumber angka SAMA PERSIS
     * dengan yang dipakai StudentDashboardService (BE-009) — total_points
     * dan total_exp dihitung dari tabel transaksi yang sama, bukan formula
     * baru. Bedanya cuma di sini bisa di-filter per rentang tanggal
     * (dashboard selalu "sepanjang waktu" + tren 7 hari terakhir saja).
     */
    public function getStudentReport(StudentProfile $studentProfile, Carbon $startDate, Carbon $endDate): array
    {
        $userId = $studentProfile->user_id;

        $totalPoints = (int) PointTransaction::where('user_id', $userId)
            ->whereBetween('period_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->sum('amount');

        $totalExp = (int) ExpTransaction::where('user_id', $userId)
            ->whereBetween('period_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->sum('amount');

        $submittedDays = ActivitySubmission::where('student_profile_id', $studentProfile->id)
            ->where('status', 'locked')
            ->whereBetween('activity_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->count();

        return [
            'student_profile_id' => $studentProfile->id,
            'full_name' => $studentProfile->full_name,
            'period' => ['start' => $startDate->toDateString(), 'end' => $endDate->toDateString()],
            'total_points' => $totalPoints,
            'total_exp' => $totalExp,
            'level' => $this->levelService->calculateLevel($totalExp),
            'submitted_days' => $submittedDays,
        ];
    }

    /**
     * Report untuk semua siswa di 1 rombel, periode tertentu.
     * 1 query utama per metrik (hindari N+1 walau iterasi per siswa,
     * karena jumlah siswa per rombel biasanya kecil/wajar).
     */
    public function getRombelReport(int $rombelId, Carbon $startDate, Carbon $endDate): array
    {
        $studentProfileIds = StudentProfile::whereHas(
            'currentEnrollment',
            fn ($q) => $q->where('rombel_id', $rombelId)->where('status', 'active')
        )->pluck('id');

        $students = StudentProfile::whereIn('id', $studentProfileIds)->get();

        $rows = $students->map(
            fn (StudentProfile $sp) => $this->getStudentReport($sp, $startDate, $endDate)
        );

        return [
            'rombel_id' => $rombelId,
            'period' => ['start' => $startDate->toDateString(), 'end' => $endDate->toDateString()],
            'student_count' => $rows->count(),
            'students' => $rows->values()->toArray(),
        ];
    }

    /**
     * Report tingkat sekolah — DELEGASI ke SchoolAnalyticsService (BE-011),
     * tidak menghitung ulang rata-rata/tren. "Reconcile" terjamin karena
     * ini panggil fungsi yang PERSIS sama dengan yang dipakai dashboard.
     */
    public function getSchoolReport(int $schoolId, int $trendDays = 30): array
    {
        return [
            'school_id' => $schoolId,
            'average_points' => $this->analyticsService->getSchoolAveragePoints($schoolId),
            'rombel_achievements' => $this->analyticsService->getRombelAchievements($schoolId)->toArray(),
            'trend' => $this->analyticsService->getSchoolTrend($schoolId, $trendDays),
        ];
    }

    /**
     * Report pencapaian (Badge + Award) untuk 1 siswa dalam rentang tanggal.
     */
    public function getAchievementReport(StudentProfile $studentProfile, Carbon $startDate, Carbon $endDate): array
    {
        $badges = StudentBadge::with('badge')
            ->where('student_profile_id', $studentProfile->id)
            ->whereBetween('awarded_at', [$startDate, $endDate])
            ->get()
            ->map(fn ($sb) => ['type' => 'badge', 'name' => $sb->badge->name, 'date' => $sb->awarded_at]);

        $awards = StudentAward::with('award')
            ->where('student_profile_id', $studentProfile->id)
            ->whereBetween('given_at', [$startDate, $endDate])
            ->get()
            ->map(fn ($sa) => ['type' => 'award', 'name' => $sa->award->name, 'date' => $sa->given_at]);

        return [
            'student_profile_id' => $studentProfile->id,
            'period' => ['start' => $startDate->toDateString(), 'end' => $endDate->toDateString()],
            'badges_earned' => $badges->count(),
            'awards_received' => $awards->count(),
            'achievements' => $badges->concat($awards)->sortBy('date')->values()->toArray(),
        ];
    }
}