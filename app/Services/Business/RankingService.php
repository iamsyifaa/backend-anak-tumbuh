<?php

namespace App\Services\Business;

use App\Models\PointTransaction;
use App\Models\SchoolFeatureSetting;
use App\Models\StudentProfile;
use Illuminate\Support\Collection;

class RankingService
{
    /**
     * Ranking kelas ON/OFF untuk sekolah tertentu.
     * Default NONAKTIF kalau belum ada pengaturan sama sekali,
     * sesuai default kolom di migration school_feature_settings.
     */
    public function isClassRankingEnabledForSchool(int $schoolId): bool
    {
        $setting = SchoolFeatureSetting::where('school_id', $schoolId)->first();

        return (bool) ($setting->ranking_class_enabled ?? false);
    }

    /**
     * Ranking angkatan (cohort) ON/OFF untuk sekolah tertentu.
     * Default NONAKTIF kalau belum ada pengaturan sama sekali,
     * sesuai default kolom di migration school_feature_settings.
     */
    public function isCohortRankingEnabledForSchool(int $schoolId): bool
    {
        $setting = SchoolFeatureSetting::where('school_id', $schoolId)->first();

        return (bool) ($setting->ranking_cohort_enabled ?? false);
    }

    /**
     * @deprecated Gunakan isCohortRankingEnabledForSchool(). Dipertahankan
     * sementara supaya pemanggil lama (PrincipalDashboardController) tidak
     * langsung patah selama masa transisi.
     */
    public function isEnabledForSchool(int $schoolId): bool
    {
        return $this->isCohortRankingEnabledForSchool($schoolId);
    }

    /**
     * Posisi ranking siswa di ANGKATANNYA (satu sekolah, satu tahun ajaran),
     * berbasis TOTAL POIN, real-time.
     */
    public function getPositionForStudent(StudentProfile $studentProfile): ?int
    {
        $schoolId = $studentProfile->currentEnrollment()->first()?->academicYear?->school_id;

        if (! $schoolId || ! $this->isCohortRankingEnabledForSchool($schoolId)) {
            return null;
        }

        $rankings = $this->getRankingsForSchool($schoolId);

        $position = $rankings->search(fn ($row) => $row['user_id'] === $studentProfile->user_id);

        return $position === false ? null : $position + 1;
    }

    /**
     * Posisi ranking siswa di KELASNYA (satu rombel), berbasis TOTAL POIN,
     * real-time.
     */
    public function getPositionForStudentInRombel(StudentProfile $studentProfile): ?int
    {
        $enrollment = $studentProfile->currentEnrollment()->first();
        $rombelId = $enrollment?->rombel_id;
        $schoolId = $enrollment?->academicYear?->school_id;

        if (! $rombelId || ! $schoolId || ! $this->isClassRankingEnabledForSchool($schoolId)) {
            return null;
        }

        $rankings = $this->getRankingsForRombel($rombelId);

        $position = $rankings->search(fn ($row) => $row['user_id'] === $studentProfile->user_id);

        return $position === false ? null : $position + 1;
    }

    /**
     * Ranking se-sekolah (angkatan). Beri $month untuk membatasi transaksi
     * poin ke bulan tertentu (belum dipakai di langkah ini — masih
     * kumulatif kalau $month null, sesuai perilaku lama, supaya test lama
     * tidak patah). Filter bulan akan diaktifkan di langkah berikutnya
     * begitu kolom grade_level dari Anggota B siap.
     *
     * @return Collection<int, array{user_id: int, total_points: int}>
     *                                                                 Terurut dari poin tertinggi ke terendah.
     */
    public function getRankingsForSchool(int $schoolId): Collection
    {
        $userIds = StudentProfile::whereHas(
            'currentEnrollment.academicYear',
            fn ($q) => $q->where('school_id', $schoolId)
        )->pluck('user_id');

        return $this->rankByPoints($userIds);
    }

    /**
     * Ranking satu rombel (kelas). Real-time dari point_transactions,
     * konsisten dengan prinsip "satu sumber kebenaran".
     *
     * @return Collection<int, array{user_id: int, total_points: int}>
     *                                                                 Terurut dari poin tertinggi ke terendah.
     */
    public function getRankingsForRombel(int $rombelId): Collection
    {
        $userIds = StudentProfile::whereHas(
            'currentEnrollment',
            fn ($q) => $q->where('rombel_id', $rombelId)
        )->pluck('user_id');

        return $this->rankByPoints($userIds);
    }

    /**
     * @param  Collection<int, int>  $userIds
     * @return Collection<int, array{user_id: int, total_points: int}>
     */
    private function rankByPoints(Collection $userIds): Collection
    {
        return PointTransaction::whereIn('user_id', $userIds)
            ->selectRaw('user_id, SUM(amount) as total_points')
            ->groupBy('user_id')
            ->orderByDesc('total_points')
            ->get()
            ->map(fn ($row) => ['user_id' => $row->user_id, 'total_points' => (int) $row->total_points]);
    }
}