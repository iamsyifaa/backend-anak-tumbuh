<?php

namespace Tests\Feature\Habit;

use App\Models\HabitConfig;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HabitConfigAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_draft_config_for_any_school(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/schools/{$school->id}/habit-configs", [
                'version' => 1,
                'effective_date' => now()->addWeek()->toDateString(),
            ]);

        $response->assertCreated()->assertJsonPath('data.status', 'draft');
    }

    public function test_kepala_sekolah_can_create_draft_config_for_own_school(): void
    {
        $school = School::factory()->create();
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $school->id]);

        $this->actingAs($kepsek, 'sanctum')
            ->postJson("/api/schools/{$school->id}/habit-configs", [
                'version' => 1,
                'effective_date' => now()->addWeek()->toDateString(),
            ])->assertCreated();
    }

    public function test_kepala_sekolah_cannot_create_config_for_other_school(): void
    {
        $ownSchool = School::factory()->create();
        $otherSchool = School::factory()->create();
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $ownSchool->id]);

        $this->actingAs($kepsek, 'sanctum')
            ->postJson("/api/schools/{$otherSchool->id}/habit-configs", [
                'version' => 1,
                'effective_date' => now()->addWeek()->toDateString(),
            ])->assertStatus(403);
    }

    public function test_wali_kelas_cannot_create_or_manage_config(): void
    {
        $school = School::factory()->create();
        $wali = User::factory()->create(['role' => 'wali_kelas', 'school_id' => $school->id]);

        $this->actingAs($wali, 'sanctum')
            ->postJson("/api/schools/{$school->id}/habit-configs", [
                'version' => 1,
                'effective_date' => now()->addWeek()->toDateString(),
            ])->assertStatus(403);
    }

    public function test_siswa_cannot_create_or_manage_config(): void
    {
        $school = School::factory()->create();
        $siswa = User::factory()->create(['role' => 'siswa', 'school_id' => $school->id]);

        $this->actingAs($siswa, 'sanctum')
            ->postJson("/api/schools/{$school->id}/habit-configs", [
                'version' => 1,
                'effective_date' => now()->addWeek()->toDateString(),
            ])->assertStatus(403);
    }

    public function test_all_roles_can_view_configs_within_their_scope(): void
    {
        $school = School::factory()->create();
        HabitConfig::factory()->create(['school_id' => $school->id]);

        foreach (['super_admin', 'kepala_sekolah', 'wali_kelas', 'siswa'] as $role) {
            $user = User::factory()->create(['role' => $role, 'school_id' => $school->id]);

            $this->actingAs($user, 'sanctum')
                ->getJson("/api/schools/{$school->id}/habit-configs")
                ->assertOk();
        }
    }

    public function test_published_config_cannot_be_updated(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $config = HabitConfig::factory()->published()->create(['school_id' => $school->id]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/schools/{$school->id}/habit-configs/{$config->id}", [
                'version' => $config->version,
                'effective_date' => now()->addMonth()->toDateString(),
            ])->assertStatus(403);
    }

    public function test_published_config_cannot_be_deleted(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $config = HabitConfig::factory()->published()->create(['school_id' => $school->id]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/schools/{$school->id}/habit-configs/{$config->id}")
            ->assertStatus(403);
    }

    public function test_kepala_sekolah_can_publish_own_draft_config(): void
    {
        $school = School::factory()->create();
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $school->id]);
        $config = HabitConfig::factory()->create(['school_id' => $school->id, 'status' => 'draft']);

        $response = $this->actingAs($kepsek, 'sanctum')
            ->postJson("/api/schools/{$school->id}/habit-configs/{$config->id}/publish");

        $response->assertOk()->assertJsonPath('data.status', 'published');
        $this->assertDatabaseHas('habit_configs', [
            'id' => $config->id,
            'status' => 'published',
            'published_by' => $kepsek->id,
        ]);
    }

    public function test_kepala_sekolah_cannot_publish_config_of_other_school(): void
    {
        $ownSchool = School::factory()->create();
        $otherSchool = School::factory()->create();
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $ownSchool->id]);
        $config = HabitConfig::factory()->create(['school_id' => $otherSchool->id, 'status' => 'draft']);

        $this->actingAs($kepsek, 'sanctum')
            ->postJson("/api/schools/{$otherSchool->id}/habit-configs/{$config->id}/publish")
            ->assertStatus(403);
    }

    public function test_already_published_config_cannot_be_published_again(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $config = HabitConfig::factory()->published()->create(['school_id' => $school->id]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/schools/{$school->id}/habit-configs/{$config->id}/publish")
            ->assertStatus(403);
    }

    public function test_config_from_another_school_returns_404_not_403(): void
    {
        $school = School::factory()->create();
        $otherSchool = School::factory()->create();
        $config = HabitConfig::factory()->create(['school_id' => $otherSchool->id]);
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/schools/{$school->id}/habit-configs/{$config->id}/publish")
            ->assertStatus(404);
    }
}
