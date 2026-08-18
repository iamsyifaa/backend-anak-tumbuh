<?php

namespace Tests\Feature;

use App\Models\Habit;
use App\Models\HabitIndicator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HabitMasterCrudTest extends TestCase
{
    use RefreshDatabase;

    private function habitWithIndicatorAndOption(): array
    {
        $habit = Habit::create(['code' => 'BANGUN_PAGI', 'name' => 'Bangun Pagi', 'sort_order' => 1]);
        $indicator = $habit->indicators()->create(['code' => 'JAM_BANGUN', 'label' => 'Jam Bangun', 'is_required' => true]);
        $option = $indicator->options()->create(['label' => 'Sebelum jam 6', 'value' => 'before_6', 'point_value' => 10]);

        return [$habit, $indicator, $option];
    }

    // ── Otorisasi ──────────────────────────────────────────────

    public function test_siswa_cannot_create_habit(): void
    {
        $siswa = User::factory()->create(['role' => User::ROLE_SISWA]);

        $response = $this->actingAs($siswa, 'sanctum')->postJson('/api/habits', [
            'code' => 'HACK', 'name' => 'Hack Habit',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('habits', ['code' => 'HACK']);
    }

    public function test_wali_kelas_cannot_create_habit(): void
    {
        $wali = User::factory()->create(['role' => User::ROLE_WALI_KELAS]);

        $response = $this->actingAs($wali, 'sanctum')->postJson('/api/habits', [
            'code' => 'HACK2', 'name' => 'Hack Habit 2',
        ]);

        $response->assertStatus(403);
    }

    public function test_super_admin_can_create_habit(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/habits', [
            'code' => 'GOSOK_GIGI', 'name' => 'Gosok Gigi',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('habits', ['code' => 'GOSOK_GIGI']);
    }

    public function test_all_roles_can_view_habits(): void
    {
        Habit::create(['code' => 'X', 'name' => 'X']);
        $siswa = User::factory()->create(['role' => User::ROLE_SISWA]);

        $this->actingAs($siswa, 'sanctum')->getJson('/api/habits')->assertOk();
    }

    // ── Indicator is_required benar-benar tersimpan (fix bug) ────

    public function test_indicator_is_required_field_actually_persists(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $habit = Habit::create(['code' => 'X', 'name' => 'X']);

        $this->actingAs($admin, 'sanctum')->postJson("/api/habits/{$habit->id}/indicators", [
            'code' => 'IND1', 'label' => 'Indikator 1', 'is_required' => false,
        ])->assertCreated();

        $indicator = HabitIndicator::where('code', 'IND1')->firstOrFail();
        $this->assertFalse($indicator->is_required);
    }

    // ── Conditional indicator: self-reference ─────────────────────

    public function test_condition_rejects_self_reference(): void
    {
        [$habit, $indicator, $option] = $this->habitWithIndicatorAndOption();
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/indicators/{$indicator->id}/conditions", [
                'parent_indicator_id' => $indicator->id,
                'required_option_value' => $option->value,
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('indicator_conditions', 0);
    }

    // ── Conditional indicator: parent beda habit ──────────────────

    public function test_condition_rejects_parent_from_different_habit(): void
    {
        [$habitA, $indicatorA, $optionA] = $this->habitWithIndicatorAndOption();

        $habitB = Habit::create(['code' => 'HABIT_B', 'name' => 'Habit B']);
        $indicatorB = $habitB->indicators()->create(['code' => 'INDB', 'label' => 'Indikator B', 'is_required' => true]);

        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/indicators/{$indicatorB->id}/conditions", [
                'parent_indicator_id' => $indicatorA->id,
                'required_option_value' => $optionA->value,
            ]);

        $response->assertStatus(422);
    }

    // ── Conditional indicator: value tidak valid ───────────────────

    public function test_condition_rejects_option_value_not_belonging_to_parent(): void
    {
        [$habit, $indicator, $option] = $this->habitWithIndicatorAndOption();
        $child = $habit->indicators()->create(['code' => 'CHILD', 'label' => 'Child', 'is_required' => true]);
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/indicators/{$child->id}/conditions", [
                'parent_indicator_id' => $indicator->id,
                'required_option_value' => 'value_yang_tidak_ada',
            ]);

        $response->assertStatus(422);
    }

    // ── Conditional indicator: circular dependency ─────────────────

    public function test_condition_rejects_circular_dependency(): void
    {
        $habit = Habit::create(['code' => 'X', 'name' => 'X']);

        $indicatorA = $habit->indicators()->create(['code' => 'A', 'label' => 'A', 'is_required' => true]);
        $optionA = $indicatorA->options()->create(['label' => 'Ya', 'value' => 'ya', 'point_value' => 5]);

        $indicatorB = $habit->indicators()->create(['code' => 'B', 'label' => 'B', 'is_required' => true]);
        $optionB = $indicatorB->options()->create(['label' => 'Ya', 'value' => 'ya', 'point_value' => 5]);

        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        // B depends on A -> ini valid, buat dulu
        $this->actingAs($admin, 'sanctum')->postJson("/api/indicators/{$indicatorB->id}/conditions", [
            'parent_indicator_id' => $indicatorA->id,
            'required_option_value' => $optionA->value,
        ])->assertCreated();

        // A depends on B -> ini akan bikin lingkaran, harus ditolak
        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/indicators/{$indicatorA->id}/conditions", [
            'parent_indicator_id' => $indicatorB->id,
            'required_option_value' => $optionB->value,
        ]);

        $response->assertStatus(422);
    }

    // ── Valid condition tersimpan ───────────────────────────────

    public function test_valid_condition_is_saved(): void
    {
        [$habit, $indicator, $option] = $this->habitWithIndicatorAndOption();
        $child = $habit->indicators()->create(['code' => 'CHILD', 'label' => 'Child', 'is_required' => true]);
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/indicators/{$child->id}/conditions", [
                'parent_indicator_id' => $indicator->id,
                'required_option_value' => $option->value,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('indicator_conditions', [
            'indicator_id' => $child->id,
            'parent_indicator_id' => $indicator->id,
        ]);
    }

    // ── Update & destroy ─────────────────────────────────────────

    public function test_super_admin_can_update_and_delete_indicator(): void
    {
        [$habit, $indicator] = $this->habitWithIndicatorAndOption();
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($admin, 'sanctum')->putJson("/api/indicators/{$indicator->id}", [
            'label' => 'Jam Bangun (Updated)',
            'active' => false,
        ])->assertOk();

        $this->assertDatabaseHas('habit_indicators', ['id' => $indicator->id, 'active' => false]);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/indicators/{$indicator->id}")->assertOk();
        $this->assertDatabaseMissing('habit_indicators', ['id' => $indicator->id]);
    }
}
