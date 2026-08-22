<?php

namespace Tests\Unit;

use App\Services\SubmissionGuardService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Gap Timezone (Requirement Bagian 8) — verifikasi SubmissionGuardService
 * bisa dikasih timezone sekolah eksplisit, dan hasil "backfill/future"
 * berubah sesuai zona itu (bukan cuma ikut server).
 */
class SubmissionGuardServiceTimezoneTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow(); // reset supaya tidak bocor ke test lain.
        parent::tearDown();
    }

    public function test_default_behavior_unchanged_when_timezone_not_provided(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-21 10:00:00', 'UTC'));

        $guard = new SubmissionGuardService();

        $this->assertFalse($guard->isBackfillAttempt('2026-08-21'));
        $this->assertFalse($guard->isFutureDate('2026-08-21'));
    }

    /**
     * Skenario inti gap timezone: jam 23:30 WIT (UTC+9) tanggal 21, tapi
     * kalau dicek pakai jam UTC polos, sudah lewat tengah malam UTC (jadi
     * "besok" versi UTC) — HARUSNYA tetap dianggap "hari ini" versi sekolah
     * WIT, bukan backfill.
     */
    public function test_late_night_submission_in_eastern_timezone_is_not_falsely_flagged_as_backfill(): void
    {
        // 21 Agustus 23:30 WIT == 21 Agustus 14:30 UTC (WIT = UTC+9)
        Carbon::setTestNow(Carbon::parse('2026-08-21 14:30:00', 'UTC'));

        $guard = new SubmissionGuardService();

        $this->assertFalse(
            $guard->isBackfillAttempt('2026-08-21', 'Asia/Jayapura'),
            'Submission jam 23:30 WIT tanggal 21 seharusnya masih dianggap hari ini di zona WIT.'
        );
    }

    public function test_backfill_correctly_detected_within_school_timezone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-22 01:00:00', 'UTC')); // 22 Agustus 10:00 WIT

        $guard = new SubmissionGuardService();

        $this->assertTrue(
            $guard->isBackfillAttempt('2026-08-21', 'Asia/Jayapura'),
            'Tanggal 21 seharusnya sudah jadi backfill kalau sekarang sudah 22 Agustus di zona WIT.'
        );
    }
}