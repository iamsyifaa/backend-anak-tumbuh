<?php

namespace Tests\Unit;

use App\Services\DailyPeriod\DailyPeriodService;
use Carbon\Carbon;
use Tests\TestCase;

class DailyPeriodServiceTest extends TestCase
{
    public function test_current_period_is_today(): void
    {
        $service = app(DailyPeriodService::class);

        $this->assertTrue(
            $service->getCurrentPeriod()->isSameDay(Carbon::now())
        );
    }

    public function test_today_is_open_for_submission(): void
    {
        $service = app(DailyPeriodService::class);

        $this->assertTrue(
            $service->isPeriodOpenForSubmission(Carbon::now())
        );
    }

    public function test_yesterday_is_not_open_for_submission(): void
    {
        $service = app(DailyPeriodService::class);

        $this->assertFalse(
            $service->isPeriodOpenForSubmission(Carbon::yesterday())
        );
    }

    public function test_yesterday_is_detected_as_backfill_attempt(): void
    {
        $service = app(DailyPeriodService::class);

        $this->assertTrue(
            $service->isBackfillAttempt(Carbon::yesterday())
        );
    }

    public function test_today_is_not_a_backfill_attempt(): void
    {
        $service = app(DailyPeriodService::class);

        $this->assertFalse(
            $service->isBackfillAttempt(Carbon::now())
        );
    }
}