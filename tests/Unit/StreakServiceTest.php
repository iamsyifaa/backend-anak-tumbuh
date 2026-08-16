<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Gamification\StreakService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StreakServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_activity_starts_streak_at_one(): void
    {
        $user = User::factory()->create();
        $streak = app(StreakService::class)->recordActivity($user->id, Carbon::parse('2026-08-01'));

        $this->assertSame(1, $streak->current_streak_days);
        $this->assertSame(1, $streak->opportunities_used);
    }

    public function test_consecutive_day_increments_streak(): void
    {
        $user = User::factory()->create();
        $service = app(StreakService::class);

        $service->recordActivity($user->id, Carbon::parse('2026-08-01'));
        $streak = $service->recordActivity($user->id, Carbon::parse('2026-08-02'));

        $this->assertSame(2, $streak->current_streak_days);
    }

    public function test_gap_day_resets_streak_but_keeps_opportunity_count(): void
    {
        $user = User::factory()->create();
        $service = app(StreakService::class);

        $service->recordActivity($user->id, Carbon::parse('2026-08-01'));
        $streak = $service->recordActivity($user->id, Carbon::parse('2026-08-05')); // ada jeda

        $this->assertSame(1, $streak->current_streak_days); // reset
        $this->assertSame(2, $streak->opportunities_used); // tetap nambah
    }

    public function test_seventh_opportunity_is_last_allowed_in_month(): void
    {
        $user = User::factory()->create();
        $service = app(StreakService::class);

        foreach (range(1, 7) as $day) {
            $service->recordActivity($user->id, Carbon::parse("2026-08-0{$day}"));
        }

        $streak = $service->recordActivity($user->id, Carbon::parse('2026-08-08')); // ke-8

        $this->assertSame(7, $streak->opportunities_used); // tidak nambah lagi
    }

    public function test_new_month_gets_fresh_opportunity_count(): void
    {
        $user = User::factory()->create();
        $service = app(StreakService::class);

        foreach (range(1, 7) as $day) {
            $service->recordActivity($user->id, Carbon::parse("2026-08-0{$day}"));
        }

        $septemberStreak = $service->recordActivity($user->id, Carbon::parse('2026-09-01'));

        $this->assertSame(1, $septemberStreak->opportunities_used); // reset per bulan
    }
}