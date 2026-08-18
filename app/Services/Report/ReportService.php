<?php

namespace App\Services\Report;

use App\Models\ActivitySubmission;
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
     * PERFORMANCE FIX: dulu memanggil getStudentReport() per siswa di dalam
     * loop — untuk N siswa jadi 3×N query (Poin, EXP, submitted_days masing-
     * masing per siswa). Sekarang 3 query TOTAL untuk seluruh rombel
     * (masing-masing 1 query ber-GROUP BY), digabung di memori.
     */
    public function getRombelReport(int $rombelId, Carbon $startDate, Carbon $endDate): array
    {
        $students = StudentProfile::whereHas(
            'currentEnrollment',
            fn ($q) => $q->where('rombel_id', $rombelId)->where('status', 'active')
        )->get();

        if ($students->isEmpty()) {
            return [
                'rombel_id' => $rombelId,
                'period' => ['start' => $startDate->toDateString(), 'end' => $endDate->toDateString()],
                'student_count' => 0,
                'students' => [],
            ];
        }

        $userIds = $students->pluck('user_id');
        $studentProfileIds = $students->pluck('id');
        $startStr = $startDate->toDateString();
        $endStr = $endDate->toDateString();

        $pointsByUser = PointTransaction::whereIn('user_id', $userIds)
            ->whereBetween('period_date', [$startStr, $endStr])
            ->selectRaw('user_id, SUM(amount) as total')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $expByUser = ExpTransaction::whereIn('user_id', $userIds)
            ->whereBetween('period_date', [$startStr, $endStr])
            ->selectRaw('user_id, SUM(amount) as total')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $submittedByProfile = ActivitySubmission::whereIn('student_profile_id', $studentProfileIds)
            ->where('status', 'locked')
            ->whereBetween('activity_date', [$startStr, $endStr])
            ->selectRaw('student_profile_id, COUNT(*) as total')
            ->groupBy('student_profile_id')
            ->get()
            ->keyBy('student_profile_id');

        $rows = $students->map(function (StudentProfile $sp) use ($pointsByUser, $expByUser, $submittedByProfile, $startDate, $endDate) {
            $totalPoints = (int) ($pointsByUser->get($sp->user_id)->total ?? 0);
            $totalExp = (int) ($expByUser->get($sp->user_id)->total ?? 0);

            return [
                'student_profile_id' => $sp->id,
                'full_name' => $sp->full_name,
                'period' => ['start' => $startDate->toDateString(), 'end' => $endDate->toDateString()],
                'total_points' => $totalPoints,
                'total_exp' => $totalExp,
                'level' => $this->levelService->calculateLevel($totalExp),
                'submitted_days' => (int) ($submittedByProfile->get($sp->id)->total ?? 0),
            ];
        });

        return [
            'rombel_id' => $rombelId,
            'period' => ['start' => $startDate->toDateString(), 'end' => $endDate->toDateString()],
            'student_count' => $rows->count(),
            'students' => $rows->values()->toArray(),
        ];
    }

    public function getSchoolReport(int $schoolId, int $trendDays = 30): array
    {
        return [
            'school_id' => $schoolId,
            'average_points' => $this->analyticsService->getSchoolAveragePoints($schoolId),
            'rombel_achievements' => $this->analyticsService->getRombelAchievements($schoolId)->toArray(),
            'trend' => $this->analyticsService->getSchoolTrend($schoolId, $trendDays),
        ];
    }

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
