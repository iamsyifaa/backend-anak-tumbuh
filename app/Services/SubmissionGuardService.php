<?php

namespace App\Services;

use App\Models\ActivitySubmission;
use Carbon\Carbon;

/**
 * ⚠️ Kemungkinan besar overlap dengan DailyPeriodService (BE-002, Anggota C
 * — sudah ada test-nya duluan: DailyPeriodServiceTest dengan skenario yang
 * sama persis: "today is open", "yesterday is backfill attempt", dst).
 * Saya tidak punya akses ke source class itu saat menulis SEC-006, jadi
 * saya buat versi sendiri yang MINIMAL supaya acceptance criteria SEC-006
 * (duplicate & backfill ditolak) bisa dibuktikan lewat test.
 *
 * ACTION ITEM untuk tim: satukan logic ini dengan DailyPeriodService milik
 * BE-002 supaya cuma ada 1 sumber kebenaran soal "periode hari ini" —
 * jangan biarkan 2 service beda logic diam-diam berjalan paralel.
 */
class SubmissionGuardService
{
    public function isBackfillAttempt(Carbon|string $activityDate): bool
    {
        return Carbon::parse($activityDate)->startOfDay()->lt(Carbon::today());
    }

    public function isFutureDate(Carbon|string $activityDate): bool
    {
        return Carbon::parse($activityDate)->startOfDay()->gt(Carbon::today());
    }

    public function hasExistingSubmission(int $studentProfileId, Carbon|string $activityDate): bool
    {
        return ActivitySubmission::query()
            ->where('student_profile_id', $studentProfileId)
            ->whereDate('activity_date', Carbon::parse($activityDate)->toDateString())
            ->exists();
    }
}
