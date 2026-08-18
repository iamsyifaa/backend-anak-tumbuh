<?php

namespace App\Services\Analytics;

use App\Models\ActivitySubmission;
use App\Models\PointTransaction;
use App\Models\Rombel;
use App\Models\StudentProfile;
use App\Services\Business\RankingService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SchoolAnalyticsService
{
    public function __construct(private RankingService $rankingService) {}

    /**
     * Rata-rata Poin per siswa aktif di sekolah. HANYA Poin, tidak
     * pernah dicampur dengan EXP (aturan eksplisit requirement).
     */
    public function getSchoolAveragePoints(int $schoolId): float
    {
        $userIds = $this->getActiveStudentUserIds($schoolId);

        if ($userIds->isEmpty()) {
            return 0.0;
        }

        $totalPoints = PointTransaction::whereIn('user_id', $userIds)->sum('amount');

        return round($totalPoints / $userIds->count(), 2);
    }

    /**
     * Pencapaian per rombel: rata-rata Poin dan jumlah submission locked,
     * dikelompokkan per rombel dalam 1 sekolah.
     */
    public function getRombelAchievements(int $schoolId): Collection
    {
        $rombels = Rombel::where('school_id', $schoolId)->get();

        return $rombels->map(function (Rombel $rombel) {
            $userIds = StudentProfile::whereHas(
                'currentEnrollment',
                fn ($q) => $q->where('rombel_id', $rombel->id)->where('status', 'active')
            )->pluck('user_id');

            $totalPoints = PointTransaction::whereIn('user_id', $userIds)->sum('amount');
            $studentCount = $userIds->count();

            return [
                'rombel_id' => $rombel->id,
                'rombel_name' => $rombel->name,
                'student_count' => $studentCount,
                'average_points' => $studentCount > 0 ? round($totalPoints / $studentCount, 2) : 0.0,
            ];
        });
    }

    /**
     * Tren total Poin sekolah per hari, N hari terakhir (default 30).
     * 1 query, tidak N+1.
     */
    public function getSchoolTrend(int $schoolId, int $days = 30): array
    {
        $userIds = $this->getActiveStudentUserIds($schoolId);
        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();

        $rows = PointTransaction::whereIn('user_id', $userIds)
            ->where('period_date', '>=', $startDate->toDateString())
            ->selectRaw('period_date, SUM(amount) as points')
            ->groupBy('period_date')
            ->get()
            ->keyBy(fn ($row) => $row->period_date);

        $trend = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->toDateString();
            $trend[] = [
                'date' => $date,
                'points' => (int) ($rows[$date]->points ?? 0),
            ];
        }

        return $trend;
    }

    /**
     * Data ranking sekolah — DELEGASI ke RankingService (BE-009), TIDAK
     * menghitung ulang. Return null kalau ranking di-OFF untuk sekolah ini.
     */
    public function getRankingData(int $schoolId): ?Collection
    {
        if (! $this->rankingService->isEnabledForSchool($schoolId)) {
            return null;
        }

        return $this->rankingService->getRankingsForSchool($schoolId);
    }

    /**
     * Tingkat partisipasi hari ini: berapa % siswa aktif yang sudah
     * submit (locked) untuk activity_date = hari ini.
     */
    public function getTodayParticipationRate(int $schoolId): float
    {
        $userIds = $this->getActiveStudentUserIds($schoolId);

        if ($userIds->isEmpty()) {
            return 0.0;
        }

        $studentProfileIds = StudentProfile::whereIn('user_id', $userIds)->pluck('id');

        $submittedCount = ActivitySubmission::whereIn('student_profile_id', $studentProfileIds)
            ->where('status', 'locked')
            ->whereDate('activity_date', Carbon::now()->toDateString())
            ->count();

        return round(($submittedCount / $userIds->count()) * 100, 2);
    }

    /**
     * Sumber kebenaran tunggal untuk "siapa saja siswa aktif di sekolah ini"
     * — dipakai semua method di atas, supaya TIDAK ADA kebocoran data lintas
     * sekolah (acceptance criteria eksplisit).
     */
    private function getActiveStudentUserIds(int $schoolId): Collection
    {
        return StudentProfile::whereHas(
            'currentEnrollment.academicYear',
            fn ($q) => $q->where('school_id', $schoolId)
        )->pluck('user_id');
    }
}
