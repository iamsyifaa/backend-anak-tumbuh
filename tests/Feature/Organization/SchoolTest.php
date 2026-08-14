<?php

namespace Tests\Feature\Organization;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_school(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/schools', [
            'name' => 'SDN Contoh',
            'code' => 'SDN-001',
        ]);

        $response->assertCreated()->assertJsonPath('data.code', 'SDN-001');
        $this->assertDatabaseHas('schools', ['code' => 'SDN-001']);
    }

    public function test_kepala_sekolah_cannot_create_school(): void
    {
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah']);

        $response = $this->actingAs($kepsek, 'sanctum')->postJson('/api/schools', [
            'name' => 'SDN Contoh',
            'code' => 'SDN-002',
        ]);

        $response->assertStatus(403);
    }

    public function test_kepala_sekolah_can_only_see_own_school(): void
    {
        $ownSchool = School::factory()->create();
        $otherSchool = School::factory()->create();
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $ownSchool->id]);

        $response = $this->actingAs($kepsek, 'sanctum')->getJson('/api/schools');

        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains($ownSchool->id));
        $this->assertFalse($ids->contains($otherSchool->id));
    }

    public function test_kepala_sekolah_cannot_access_other_school(): void
    {
        $otherSchool = School::factory()->create();
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => School::factory()->create()->id]);

        $this->actingAs($kepsek, 'sanctum')
            ->getJson("/api/schools/{$otherSchool->id}")
            ->assertStatus(403);
    }

    public function test_kepala_sekolah_can_update_own_school_but_not_code(): void
    {
        $school = School::factory()->create(['code' => 'ORIGINAL']);
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $school->id]);

        $response = $this->actingAs($kepsek, 'sanctum')->putJson("/api/schools/{$school->id}", [
            'name' => 'Nama Baru',
            'code' => 'HACKED',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('schools', ['id' => $school->id, 'name' => 'Nama Baru', 'code' => 'ORIGINAL']);
    }

    public function test_only_super_admin_can_deactivate_school(): void
    {
        $school = School::factory()->create();
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $school->id]);

        $this->actingAs($kepsek, 'sanctum')->deleteJson("/api/schools/{$school->id}")->assertStatus(403);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin, 'sanctum')->deleteJson("/api/schools/{$school->id}")->assertOk();
        $this->assertDatabaseHas('schools', ['id' => $school->id, 'status' => 'inactive']);
    }
}