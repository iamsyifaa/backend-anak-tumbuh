<?php

namespace App\Services\Gamification;

use App\Models\StudentStreak;
use Carbon\Carbon;

class StreakService
{
    /**
     * Dipanggil setelah submission berhasil di-lock (minimal 1 kebiasaan
     * terisi hari itu). Aturan streak:
     * - Isi berturutan (gap 0 hari) -> streak +1.
     * - Kelewat 1 hari (gap) -> streak STUCK di angka terakhir (tidak
     *   reset), missed opportunity +1 per hari yang terlewat.
     * - Kalau isi lagi sebelum 7 kesempatan (missed) habis -> streak
     *   lanjut dari angka terakhir, +1.
     * - Kalau ke-7 kesempatan sudah habis semua -> streak reset ke 0
     *   (mulai lagi dari 1 di aktivitas ini).
     */
    public function recordActivity(int $userId, Carbon $activityDate): StudentStreak
    {
        $streak = StudentStreak::firstOrCreate(
            ['user_id' => $userId, 'month' => $activityDate->month, 'year' => $activityDate->year],
            ['opportunities_used' => 0, 'current_streak_days' => 0]
        );

        // Aktivitas pertama di bulan ini / belum pernah aktif sebelumnya
        if (! $streak->last_active_date) {
            $streak->update([
                'current_streak_days' => 1,
                'last_active_date' => $activityDate->toDateString(),
            ]);

            return $streak;
        }

        // Idempotency: submit ulang di hari yang sama, jangan diproses lagi
        if ($streak->last_active_date->isSameDay($activityDate)) {
            return $streak;
        }

        $gapDays = $streak->last_active_date->diffInDays($activityDate) - 1;

        if ($gapDays <= 0) {
            // Berturutan, tidak ada hari terlewat
            $streak->update([
                'current_streak_days' => $streak->current_streak_days + 1,
                'last_active_date' => $activityDate->toDateString(),
            ]);

            return $streak;
        }

        $newOpportunitiesUsed = $streak->opportunities_used + $gapDays;

        if ($newOpportunitiesUsed >= 7) {
            // 7 kesempatan habis -> reset total, mulai lagi dari 1
            $streak->update([
                'opportunities_used' => 0,
                'current_streak_days' => 1,
                'last_active_date' => $activityDate->toDateString(),
            ]);

            return $streak;
        }

        // Masih ada sisa kesempatan -> streak lanjut dari angka terakhir
        $streak->update([
            'opportunities_used' => $newOpportunitiesUsed,
            'current_streak_days' => $streak->current_streak_days + 1,
            'last_active_date' => $activityDate->toDateString(),
        ]);

        return $streak;
    }
}