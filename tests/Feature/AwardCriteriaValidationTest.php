<?php

namespace Tests\Feature;

use App\Models\Award;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AwardCriteriaValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_award_criteria_rejects_key_referencing_points(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/awards', [
                'code' => 'AWARD-TEST-1',
                'name' => 'Award Test',
                'criteria' => ['min_point' => 100],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('criteria');
    }

    public function test_award_criteria_rejects_key_referencing_level(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/awards', [
                'code' => 'AWARD-TEST-2',
                'name' => 'Award Test 2',
                'criteria' => ['required_level' => 5],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('criteria');
    }

    public function test_award_criteria_accepts_habit_based_keys(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/awards', [
                'code' => 'AWARD-TEST-3',
                'name' => 'Award Test 3',
                'criteria' => ['habit_id' => 1, 'period_type' => 'monthly'],
            ])
            ->assertStatus(201);
    }

    public function test_update_award_also_rejects_point_based_criteria(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $award = Award::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/awards/{$award->id}", [
                'criteria' => ['total_exp' => 200],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('criteria');
    }
}