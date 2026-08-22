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

    public function test_first_activity_starts_streak_at_one_with_no_opportunity_used(): void
    {
        $user = User::factory()->create();
        $streak = app(StreakService::class)->recordActivity($user->id, Carbon::parse('2026-08-01'));

        $this->assertSame(1, $streak->current_streak_days);
        $this->assertSame(0, $streak->opportunities_used);
    }

    public function test_consecutive_day_increments_streak_without_using_opportunity(): void
    {
        $user = User::factory()->create();
        $service = app(StreakService::class);

        $service->recordActivity($user->id, Carbon::parse('2026-08-01'));
        $streak = $service->recordActivity($user->id, Carbon::parse('2026-08-02'));

        $this->assertSame(2, $streak->current_streak_days);
        $this->assertSame(0, $streak->opportunities_used);
    }

    public function test_gap_day_keeps_streak_stuck_and_uses_opportunity(): void
    {
        $user = User::factory()->create();
        $service = app(StreakService::class);

        $service->recordActivity($user->id, Carbon::parse('2026-08-01')); // streak 1
        $streak = $service->recordActivity($user->id, Carbon::parse('2026-08-03')); // gap 1 hari (tgl 2 kosong)

        // stuck lanjut dari angka terakhir (+1), BUKAN reset ke 1
        $this->assertSame(2, $streak->current_streak_days);
        $this->assertSame(1, $streak->opportunities_used);
    }

    public function test_streak_continues_across_multiple_gaps_until_opportunities_run_out(): void
    {
        $user = User::factory()->create();
        $service = app(StreakService::class);

        $service->recordActivity($user->id, Carbon::parse('2026-08-01')); // streak 1, opp 0

        // 6 kali isi dengan gap 1 hari tiap kali (opp naik ke 1..6), streak tetap lanjut naik
        $dates = ['2026-08-03', '2026-08-05', '2026-08-07', '2026-08-09', '2026-08-11', '2026-08-13'];
        $streak = null;
        foreach ($dates as $date) {
            $streak = $service->recordActivity($user->id, Carbon::parse($date));
        }

        $this->assertSame(7, $streak->current_streak_days); // 1 awal + 6 kali lanjut
        $this->assertSame(6, $streak->opportunities_used);
    }

    public function test_seventh_opportunity_used_resets_streak(): void
    {
        $user = User::factory()->create();
        $service = app(StreakService::class);

        $service->recordActivity($user->id, Carbon::parse('2026-08-01')); // streak 1, opp 0

        // 7 gap berturutan (masing-masing 1 hari kosong) -> opportunities_used akan mencapai 7 di titik ini
        $dates = ['2026-08-03', '2026-08-05', '2026-08-07', '2026-08-09', '2026-08-11', '2026-08-13', '2026-08-15'];
        $streak = null;
        foreach ($dates as $date) {
            $streak = $service->recordActivity($user->id, Carbon::parse($date));
        }

        // di aktivitas ke-7 (gap ke-7), kesempatan habis -> reset total
        $this->assertSame(1, $streak->current_streak_days);
        $this->assertSame(0, $streak->opportunities_used);
    }

    public function test_resubmitting_same_day_is_idempotent(): void
    {
        $user = User::factory()->create();
        $service = app(StreakService::class);

        $service->recordActivity($user->id, Carbon::parse('2026-08-01'));
        $streak = $service->recordActivity($user->id, Carbon::parse('2026-08-01')); // submit ulang hari sama

        $this->assertSame(1, $streak->current_streak_days);
        $this->assertSame(0, $streak->opportunities_used);
    }

    public function test_streak_continues_across_calendar_month_boundary(): void
    {
        $user = User::factory()->create();
        $service = app(StreakService::class);

        $service->recordActivity($user->id, Carbon::parse('2026-08-30')); // streak 1
        $service->recordActivity($user->id, Carbon::parse('2026-08-31')); // streak 2, berturutan
        $streak = $service->recordActivity($user->id, Carbon::parse('2026-09-01')); // streak 3, tetap berturutan lintas bulan

        $this->assertSame(3, $streak->current_streak_days);
        $this->assertSame(0, $streak->opportunities_used);
    }

    public function test_gap_across_calendar_month_boundary_keeps_streak_stuck(): void
    {
        $user = User::factory()->create();
        $service = app(StreakService::class);

        $service->recordActivity($user->id, Carbon::parse('2026-08-30')); // streak 1
        $streak = $service->recordActivity($user->id, Carbon::parse('2026-09-01')); // gap 1 hari (31 Agustus kosong), lintas bulan

        $this->assertSame(2, $streak->current_streak_days);
        $this->assertSame(1, $streak->opportunities_used);
    }
}