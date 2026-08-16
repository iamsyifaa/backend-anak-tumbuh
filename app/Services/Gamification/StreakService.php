<?php

namespace App\Services\Gamification;

use App\Models\StudentStreak;
use Carbon\Carbon;

class StreakService
{
    /**
     * Dipanggil setelah submission berhasil di-lock (minimal 1 kebiasaan
     * terisi hari itu = 1 kesempatan terpakai). Kalau kesempatan bulan ini
     * sudah habis (7), streak TIDAK bertambah lagi bulan ini — tapi tidak
     * dianggap "gagal", cuma berhenti nambah sampai bulan depan.
     */
    public function recordActivity(int $userId, Carbon $activityDate): StudentStreak
    {
        $streak = StudentStreak::firstOrCreate(
            ['user_id' => $userId, 'month' => $activityDate->month, 'year' => $activityDate->year],
            ['opportunities_used' => 0, 'current_streak_days' => 0]
        );

        if (! $streak->hasOpportunityLeft()) {
            return $streak; // kesempatan bulan ini habis, tidak diproses lagi
        }

        $isConsecutive = $streak->last_active_date
            && $streak->last_active_date->isSameDay($activityDate->copy()->subDay());

        $streak->update([
            'opportunities_used' => $streak->opportunities_used + 1,
            'current_streak_days' => $isConsecutive ? $streak->current_streak_days + 1 : 1,
            'last_active_date' => $activityDate->toDateString(),
        ]);

        return $streak;
    }
}