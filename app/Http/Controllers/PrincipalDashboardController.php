<?php

namespace App\Http\Controllers;

use App\Models\ExpTransaction;
use App\Models\PointTransaction;
use App\Models\Rombel;
use App\Models\School;
use App\Models\StudentProfile;
use App\Services\Business\LevelService;
use App\Services\Business\RankingService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PrincipalDashboardController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly RankingService $rankingService,
        private readonly LevelService $levelService,
    ) {}

    /**
     * GET /api/schools/{school}/dashboard/overview
     */
    public function overview(Request $request, School $school)
    {
        $this->authorize('principal.dashboard.view', $school);

        $studentUserIds = $this->schoolStudentUserIds($school);

        $avgPoints = $this->averageAmount(PointTransaction::class, $studentUserIds);
        $avgExp = $this->averageAmount(ExpTransaction::class, $studentUserIds);

        $classAchievement = Rombel::where('school_id', $school->id)
            ->get()
            ->map(function (Rombel $rombel) {
                $userIds = $this->rombelStudentUserIds($rombel);

                return [
                    'rombel_id' => $rombel->id,
                    'rombel_name' => $rombel->name,
                    'student_count' => $userIds->count(),
                    'avg_points' => $this->averageAmount(PointTransaction::class, $userIds),
                ];
            });

       $rankingEnabled = $this->rankingService->isCohortRankingEnabledForSchool($school->id);
        $topRanking = null;

        if ($rankingEnabled && $request->filled('education_level_id')) {
            $topRanking = $this->rankingService
                // [Gap Timezone, Requirement Bagian 8] Batas bulan ranking
                // angkatan mengikuti timezone sekolah, bukan server.
                ->getRankingsForGrade((int) $request->integer('education_level_id'), now($school->timezone))
                ->take(10)
                ->values();
        }

        return $this->success([
            'school_id' => $school->id,
            'school_name' => $school->name,
            'averages' => [
                'avg_points' => $avgPoints,
                'avg_exp' => $avgExp,
            ],
            'class_achievement' => $classAchievement,
            'ranking' => [
                'enabled' => $rankingEnabled,
                'top' => $topRanking,
            ],
        ]);
    }

    /**
     * GET /api/schools/{school}/dashboard/trend
     */
    public function trend(Request $request, School $school)
    {
        $this->authorize('principal.dashboard.view', $school);

        $from = $request->filled('from') ? Carbon::parse($request->string('from')) : now()->subDays(29);
        $to = $request->filled('to') ? Carbon::parse($request->string('to')) : now();

        $studentUserIds = $this->schoolStudentUserIds($school);

        $trend = PointTransaction::whereIn('user_id', $studentUserIds)
            ->whereBetween('period_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('period_date, SUM(amount) as total_points')
            ->groupBy('period_date')
            ->orderBy('period_date')
            ->get();

        return $this->success([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'trend' => $trend,
        ]);
    }

    /**
     * GET /api/schools/{school}/dashboard/rombels/{rombel}
     */
    public function rombelDetail(Request $request, School $school, Rombel $rombel)
    {
        $this->authorize('principal.dashboard.view', $school);

        abort_if($rombel->school_id !== $school->id, 404);

        $students = StudentProfile::whereHas('enrollments', function ($q) use ($rombel) {
            $q->where('rombel_id', $rombel->id)->where('status', 'active');
        })->get();

        $detail = $students->map(function (StudentProfile $student) {
            $points = (int) PointTransaction::where('user_id', $student->user_id)->sum('amount');
            $exp = (int) ExpTransaction::where('user_id', $student->user_id)->sum('amount');

            return [
                'student_profile_id' => $student->id,
                'full_name' => $student->full_name,
                'total_points' => $points,
                'total_exp' => $exp,
                'level' => $this->levelService->calculateLevel($exp),
            ];
        });

        return $this->success([
            'rombel_id' => $rombel->id,
            'rombel_name' => $rombel->name,
            'students' => $detail,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function schoolStudentUserIds(School $school)
    {
        return StudentProfile::whereHas('enrollments', function ($q) use ($school) {
            $q->where('status', 'active')
                ->whereHas('academicYear', fn ($q2) => $q2->where('school_id', $school->id));
        })->pluck('user_id');
    }

    private function rombelStudentUserIds(Rombel $rombel)
    {
        return StudentProfile::whereHas('enrollments', function ($q) use ($rombel) {
            $q->where('rombel_id', $rombel->id)->where('status', 'active');
        })->pluck('user_id');
    }

    private function averageAmount(string $modelClass, $userIds): float
    {
        if ($userIds->isEmpty()) {
            return 0.0;
        }

        $total = $modelClass::whereIn('user_id', $userIds)->sum('amount');

        return (float) round($total / $userIds->count(), 2);
    }
}
