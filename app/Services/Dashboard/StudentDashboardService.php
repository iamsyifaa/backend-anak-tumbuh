<?php

namespace App\Services\Dashboard;

use App\Models\PointTransaction;
use App\Models\ExpTransaction;
use App\Models\StudentBadge;
use App\Models\StudentAward;
use App\Models\StudentStreak;
use App\Models\StudentProfile;
use App\Services\Business\LevelService;
use App\Services\Business\RankingService;
use Carbon\Carbon;

class StudentDashboardService
{
    public function __construct(
        private LevelService $levelService,
        private RankingService $rankingService,
    ) {}

    public function getDashboard(StudentProfile $studentProfile): array
    {
        $userId = $studentProfile->user_id;
        $today = Carbon::now();

        $totalPoints = (int) PointTransaction::where('user_id', $userId)->sum('amount');
        $totalExp = (int) ExpTransaction::where('user_id', $userId)->sum('amount');

        $todayPoints = (int) PointTransaction::where('user_id', $userId)
            ->where('period_date', $today->toDateString())
            ->sum('amount');

        $streak = StudentStreak::where('user_id', $userId)
            ->where('month', $today->month)
            ->where('year', $today->year)
            ->first();

        return [
            'today_points' => $todayPoints,
            'total_points' => $totalPoints,
            'total_exp' => $totalExp,
            'level' => $this->levelService->calculateLevel($totalExp),
            'exp_to_next_level' => $this->levelService->expToNextLevel($totalExp),
            'streak' => [
                'current_days' => $streak?->current_streak_days ?? 0,
                'opportunities_used' => $streak?->opportunities_used ?? 0,
                'opportunities_left' => 7 - ($streak?->opportunities_used ?? 0),
            ],
            'ranking_position' => $this->rankingService->getPositionForStudent($studentProfile),
            'weekly_trend' => $this->getWeeklyTrend($userId, $today),
            'recent_achievements' => $this->getRecentAchievements($studentProfile->id),
        ];
    }

    /**
     * @return array<int, array{date: string, points: int}> 7 hari terakhir
     */
    private function getWeeklyTrend(int $userId, Carbon $today): array
    {
        $startDate = $today->copy()->subDays(6)->startOfDay();

        $rows = PointTransaction::where('user_id', $userId)
            ->where('period_date', '>=', $startDate->toDateString())
            ->selectRaw('period_date, SUM(amount) as points')
            ->groupBy('period_date')
            ->get()
            ->keyBy(fn ($row) => $row->period_date);

        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = $today->copy()->subDays($i)->toDateString();
            $trend[] = [
                'date' => $date,
                'points' => (int) ($rows[$date]->points ?? 0),
            ];
        }

        return $trend;
    }

    private function getRecentAchievements(int $studentProfileId): array
    {
        $badges = StudentBadge::with('badge')
            ->where('student_profile_id', $studentProfileId)
            ->latest('awarded_at')
            ->limit(5)
            ->get()
            ->map(fn ($sb) => ['type' => 'badge', 'name' => $sb->badge->name, 'date' => $sb->awarded_at]);

        $awards = StudentAward::with('award')
            ->where('student_profile_id', $studentProfileId)
            ->latest('given_at')
            ->limit(5)
            ->get()
            ->map(fn ($sa) => ['type' => 'award', 'name' => $sa->award->name, 'date' => $sa->given_at]);

        return $badges->concat($awards)
            ->sortByDesc('date')
            ->take(5)
            ->values()
            ->toArray();
    }
}