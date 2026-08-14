<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\PointTransaction;
use App\Models\ExpTransaction;
use App\Services\StudentStatistics\StudentStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentStatisticsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_aggregates_total_points_correctly(): void
    {
        $user = User::factory()->create();

        PointTransaction::create([
            'user_id' => $user->id,
            'amount' => 10,
            'source_type' => 'submission_answer',
            'source_id' => 1,
            'period_date' => now()->toDateString(),
        ]);

        PointTransaction::create([
            'user_id' => $user->id,
            'amount' => 15,
            'source_type' => 'submission_answer',
            'source_id' => 2,
            'period_date' => now()->toDateString(),
        ]);

        $service = app(StudentStatisticsService::class);
        $result = $service->getStatistics($user->id);

        $this->assertSame(25, $result['total_points']);
    }

    public function test_it_aggregates_total_exp_correctly(): void
    {
        $user = User::factory()->create();

        ExpTransaction::create([
            'user_id' => $user->id,
            'amount' => 5,
            'source_type' => 'submission_answer',
            'source_id' => 1,
            'period_date' => now()->toDateString(),
        ]);

        ExpTransaction::create([
            'user_id' => $user->id,
            'amount' => 8,
            'source_type' => 'submission_answer',
            'source_id' => 2,
            'period_date' => now()->toDateString(),
        ]);

        $service = app(StudentStatisticsService::class);
        $result = $service->getStatistics($user->id);

        $this->assertSame(13, $result['total_exp']);
    }

    public function test_response_contract_has_stable_keys(): void
    {
        $user = User::factory()->create();

        $service = app(StudentStatisticsService::class);
        $result = $service->getStatistics($user->id);

        $this->assertArrayHasKey('user_id', $result);
        $this->assertArrayHasKey('total_points', $result);
        $this->assertArrayHasKey('total_exp', $result);
        $this->assertArrayHasKey('level', $result);
        $this->assertArrayHasKey('streak', $result);
        $this->assertArrayHasKey('activity_history', $result);
    }

    public function test_points_and_exp_are_never_mixed_up(): void
    {
        $user = User::factory()->create();

        PointTransaction::create([
            'user_id' => $user->id,
            'amount' => 100,
            'source_type' => 'submission_answer',
            'source_id' => 1,
            'period_date' => now()->toDateString(),
        ]);

        ExpTransaction::create([
            'user_id' => $user->id,
            'amount' => 50,
            'source_type' => 'submission_answer',
            'source_id' => 1,
            'period_date' => now()->toDateString(),
        ]);

        $service = app(StudentStatisticsService::class);
        $result = $service->getStatistics($user->id);

        $this->assertSame(100, $result['total_points']);
        $this->assertSame(50, $result['total_exp']);
    }
}