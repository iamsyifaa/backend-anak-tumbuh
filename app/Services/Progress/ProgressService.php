<?php

namespace App\Services\Progress;

use App\Models\ActivitySubmission;
use Carbon\Carbon;

class ProgressService
{
    /**
     * PLACEHOLDER metrik — requirement tidak mengunci formula progress
     * secara eksplisit. Dihitung sebagai: jumlah submission locked bulan
     * ini / jumlah hari yang sudah berjalan bulan ini. Direview tim kalau
     * definisi "progress" yang dimaksud berbeda.
     */
    public function getMonthlyProgress(int $studentProfileId, ?Carbon $month = null): array
    {
        $month ??= Carbon::now();

        $submittedDays = ActivitySubmission::where('student_profile_id', $studentProfileId)
            ->where('status', 'locked')
            ->whereYear('activity_date', $month->year)
            ->whereMonth('activity_date', $month->month)
            ->count();

        $daysElapsed = $month->isSameMonth(Carbon::now())
            ? Carbon::now()->day
            : $month->daysInMonth;

        return [
            'submitted_days' => $submittedDays,
            'days_elapsed' => $daysElapsed,
            'completion_rate' => $daysElapsed > 0
                ? round($submittedDays / $daysElapsed, 2)
                : 0,
        ];
    }
}
