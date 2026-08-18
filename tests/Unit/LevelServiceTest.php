<?php

namespace Tests\Unit;

use App\Services\Business\LevelService;
use Tests\TestCase;

class LevelServiceTest extends TestCase
{
    public function test_zero_exp_is_level_one(): void
    {
        $this->assertSame(1, app(LevelService::class)->calculateLevel(0));
    }

    public function test_exp_exactly_at_threshold_reaches_that_level(): void
    {
        $this->assertSame(3, app(LevelService::class)->calculateLevel(250));
    }

    public function test_exp_just_below_threshold_stays_at_lower_level(): void
    {
        $this->assertSame(2, app(LevelService::class)->calculateLevel(249));
    }

    public function test_exp_to_next_level_calculates_remaining_gap(): void
    {
        $this->assertSame(50, app(LevelService::class)->expToNextLevel(200));
    }

    public function test_max_level_returns_null_exp_to_next(): void
    {
        $this->assertNull(app(LevelService::class)->expToNextLevel(5000));
    }
}
