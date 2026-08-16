<?php

namespace App\Services\Business;

use App\Models\PointTransaction;
use App\Models\SchoolFeatureSetting;
use App\Models\StudentProfile;
use Illuminate\Support\Collection;

class RankingService
{
    public function isEnabledForSchool(int $schoolId): bool
    {
        $setting = SchoolFeatureSetting::where('school_id', $schoolId)->first();

        // Default AKTIF kalau belum ada pengaturan sama sekali —
        // sekolah baru tidak tiba-tiba kehilangan fitur ranking.
        return $setting?->ranking_cohort_enabled ?? true;
    }

    /**
     * Posisi ranking siswa di sekolahnya, berbasis TOTAL POIN (bukan EXP),
     * real-time (langsung SUM dari point_transactions, tidak di-cache).
     */
    public function getPositionForStudent(StudentProfile $studentProfile): ?int
    {
        $schoolId = $studentProfile->currentEnrollment()->first()?->academicYear?->school_id;

        if (! $schoolId || ! $this->isEnabledForSchool($schoolId)) {
            return null;
        }

        $rankings = $this->getRankingsForSchool($schoolId);

        $position = $rankings->search(fn ($row) => $row['user_id'] === $studentProfile->user_id);

        return $position === false ? null : $position + 1;
    }

    /**
     * @return Collection<int, array{user_id: int, total_points: int}>
     *         Terurut dari poin tertinggi ke terendah.
     */
    public function getRankingsForSchool(int $schoolId): Collection
    {
        $userIds = StudentProfile::whereHas(
            'currentEnrollment.academicYear',
            fn ($q) => $q->where('school_id', $schoolId)
        )->pluck('user_id');

        return PointTransaction::whereIn('user_id', $userIds)
            ->selectRaw('user_id, SUM(amount) as total_points')
            ->groupBy('user_id')
            ->orderByDesc('total_points')
            ->get()
            ->map(fn ($row) => ['user_id' => $row->user_id, 'total_points' => (int) $row->total_points]);
    }
}