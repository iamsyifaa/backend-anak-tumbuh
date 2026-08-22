<?php

namespace App\Services\Gamification;

use App\Models\StudentStreak;
use Carbon\Carbon;

class StreakService
{
   /**
     * Dipanggil setelah submission berhasil di-lock (minimal 1 kebiasaan
     * terisi hari itu). Satu row StudentStreak per user, KONTINU lintas
     * bulan kalender (bukan reset tiap ganti bulan). Aturan streak:
     * - Isi berturutan (gap 0 hari) -> streak +1.
     * - Kelewat N hari (gap) -> streak STUCK, lanjut dari angka terakhir
     *   saat isi lagi, missed opportunity +N.
     * - Reset ke 1 HANYA jika opportunities_used mencapai/melewati 7.
     */
    public function recordActivity(int $userId, Carbon $activityDate): StudentStreak
    {
        $streak = StudentStreak::firstOrCreate(
            ['user_id' => $userId],
            [
                'month' => $activityDate->month,
                'year' => $activityDate->year,
                'opportunities_used' => 0,
                'current_streak_days' => 0,
            ]
        );

        // Aktivitas pertama sepanjang riwayat siswa (belum pernah aktif sama sekali)
        if (! $streak->last_active_date) {
            $streak->update([
                'current_streak_days' => 1,
                'month' => $activityDate->month,
                'year' => $activityDate->year,
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
                'month' => $activityDate->month,
                'year' => $activityDate->year,
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
                'month' => $activityDate->month,
                'year' => $activityDate->year,
                'last_active_date' => $activityDate->toDateString(),
            ]);

            return $streak;
        }

        // Masih ada sisa kesempatan -> streak lanjut dari angka terakhir
        $streak->update([
            'opportunities_used' => $newOpportunitiesUsed,
            'current_streak_days' => $streak->current_streak_days + 1,
            'month' => $activityDate->month,
            'year' => $activityDate->year,
            'last_active_date' => $activityDate->toDateString(),
        ]);

        return $streak;
    }
}