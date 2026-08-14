<?php

namespace Tests\Feature\Organization;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicYearTest extends TestCase
{
    use RefreshDatabase;

    public function test_kepala_sekolah_can_create_academic_year_for_own_school(): void
    {
        $school = School::factory()->create();
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $school->id]);

        $response = $this->actingAs($kepsek, 'sanctum')->postJson("/api/schools/{$school->id}/academic-years", [
            'name' => '2025/2026',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'inactive');
    }

    public function test_wali_kelas_cannot_create_academic_year(): void
    {
        $school = School::factory()->create();
        $wali = User::factory()->create(['role' => 'wali_kelas', 'school_id' => $school->id]);

        $this->actingAs($wali, 'sanctum')->postJson("/api/schools/{$school->id}/academic-years", [
            'name' => '2025/2026',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
        ])->assertStatus(403);
    }

    public function test_activating_academic_year_deactivates_previous_active_one(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create(['role' => 'super_admin']);

        $old = AcademicYear::factory()->create(['school_id' => $school->id, 'name' => '2024/2025', 'status' => 'active']);
        $new = AcademicYear::factory()->create(['school_id' => $school->id, 'name' => '2025/2026', 'status' => 'inactive']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/schools/{$school->id}/academic-years/{$new->id}/activate");

        $response->assertOk()->assertJsonPath('data.status', 'active');
        $this->assertDatabaseHas('academic_years', ['id' => $old->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('academic_years', ['id' => $new->id, 'status' => 'active']);
    }

    public function test_active_academic_year_cannot_be_deleted(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $year = AcademicYear::factory()->create(['school_id' => $school->id, 'status' => 'active']);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/schools/{$school->id}/academic-years/{$year->id}")
            ->assertStatus(422);
    }

    public function test_academic_year_from_another_school_returns_404(): void
    {
        $school = School::factory()->create();
        $otherSchool = School::factory()->create();
        $year = AcademicYear::factory()->create(['school_id' => $otherSchool->id]);
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/schools/{$school->id}/academic-years/{$year->id}")
            ->assertStatus(404);
    }

    public function test_duplicate_academic_year_name_in_same_school_rejected(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create(['role' => 'super_admin']);
        AcademicYear::factory()->create(['school_id' => $school->id, 'name' => '2025/2026']);

        $this->actingAs($admin, 'sanctum')->postJson("/api/schools/{$school->id}/academic-years", [
            'name' => '2025/2026',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
        ])->assertStatus(422);
    }
}