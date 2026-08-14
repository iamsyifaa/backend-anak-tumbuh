<?php

namespace App\Services;

use App\Models\PointTransaction;
use App\Models\StudentProfile;
use App\Models\StudentTrophy;
use App\Models\Trophy;

class TrophyService
{
    /**
     * Cek dan anugerahkan piala baru untuk siswa berdasarkan pencapaian poin.
     */
    public function checkAndAwardTrophies(StudentProfile $studentProfile): array
    {
        $totalPoints = PointTransaction::where('student_profile_id', $studentProfile->id)
            ->sum('amount');

        // Cari piala yang syarat poinnya tercapai tapi belum didapatkan siswa
        $earnedTrophyIds = StudentTrophy::where('student_profile_id', $studentProfile->id)
            ->pluck('trophy_id')
            ->toArray();

        $eligibleTrophies = Trophy::whereNotIn('id', $earnedTrophyIds)
            ->where('required_points', '<=', $totalPoints)
            ->get();

        $newlyAwarded = [];

        foreach ($eligibleTrophies as $trophy) {
            StudentTrophy::create([
                'student_profile_id' => $studentProfile->id,
                'trophy_id' => $trophy->id,
                'awarded_at' => now(),
            ]);

            $newlyAwarded[] = $trophy;
        }

        return $newlyAwarded;
    }
}