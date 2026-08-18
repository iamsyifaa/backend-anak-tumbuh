<?php

namespace Tests\Feature\Teacher;

use App\Models\Rombel;
use App\Models\School;
use App\Models\TeacherRombelAssignment;
use App\Models\User;
use App\Services\TeacherAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherRombelScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_wali_kelas_can_view_own_assigned_rombel(): void
    {
        $school = School::factory()->create();
        $wali = User::factory()->create(['role' => 'wali_kelas', 'school_id' => $school->id]);
        $rombel = Rombel::factory()->create(['school_id' => $school->id]);

        app(TeacherAssignmentService::class)->assign($wali, $rombel);

        $this->actingAs($wali, 'sanctum')
            ->getJson("/api/rombels/{$rombel->id}")
            ->assertOk();
    }

    public function test_wali_kelas_cannot_view_other_rombel_they_are_not_assigned_to(): void
    {
        $school = School::factory()->create();
        $wali = User::factory()->create(['role' => 'wali_kelas', 'school_id' => $school->id]);
        $ownRombel = Rombel::factory()->create(['school_id' => $school->id]);
        $otherRombel = Rombel::factory()->create(['school_id' => $school->id]);

        app(TeacherAssignmentService::class)->assign($wali, $ownRombel);

        $this->actingAs($wali, 'sanctum')
            ->getJson("/api/rombels/{$otherRombel->id}")
            ->assertStatus(403);
    }

    public function test_wali_kelas_with_no_assignment_cannot_view_any_rombel(): void
    {
        $wali = User::factory()->create(['role' => 'wali_kelas']);
        $rombel = Rombel::factory()->create();

        $this->actingAs($wali, 'sanctum')
            ->getJson("/api/rombels/{$rombel->id}")
            ->assertStatus(403);
    }

    /**
     * Inti SEC-009: assign ke rombel BARU harus otomatis melepas rombel LAMA,
     * bukan menambah — guru tidak boleh aktif di 2 rombel sekaligus.
     */
    public function test_assigning_teacher_to_new_rombel_ends_previous_assignment(): void
    {
        $school = School::factory()->create();
        $wali = User::factory()->create(['role' => 'wali_kelas', 'school_id' => $school->id]);
        $rombelLama = Rombel::factory()->create(['school_id' => $school->id]);
        $rombelBaru = Rombel::factory()->create(['school_id' => $school->id]);

        $service = app(TeacherAssignmentService::class);
        $service->assign($wali, $rombelLama);
        $service->assign($wali, $rombelBaru);

        $this->assertDatabaseHas('teacher_rombel_assignments', [
            'teacher_id' => $wali->id,
            'rombel_id' => $rombelLama->id,
            'status' => 'ended',
        ]);
        $this->assertDatabaseHas('teacher_rombel_assignments', [
            'teacher_id' => $wali->id,
            'rombel_id' => $rombelBaru->id,
            'status' => 'active',
        ]);

        // Sekarang dia HANYA bisa akses rombel baru, tidak lagi rombel lama.
        $this->actingAs($wali, 'sanctum')->getJson("/api/rombels/{$rombelBaru->id}")->assertOk();
        $this->actingAs($wali, 'sanctum')->getJson("/api/rombels/{$rombelLama->id}")->assertStatus(403);
    }

    /**
     * Sisi lain: satu rombel juga tidak boleh punya 2 wali kelas aktif —
     * assign guru baru ke rombel yang sama harus melepas guru lama.
     */
    public function test_assigning_new_teacher_to_rombel_ends_previous_teachers_assignment(): void
    {
        $school = School::factory()->create();
        $waliLama = User::factory()->create(['role' => 'wali_kelas', 'school_id' => $school->id]);
        $waliBaru = User::factory()->create(['role' => 'wali_kelas', 'school_id' => $school->id]);
        $rombel = Rombel::factory()->create(['school_id' => $school->id]);

        $service = app(TeacherAssignmentService::class);
        $service->assign($waliLama, $rombel);
        $service->assign($waliBaru, $rombel);

        $this->actingAs($waliBaru, 'sanctum')->getJson("/api/rombels/{$rombel->id}")->assertOk();
        $this->actingAs($waliLama, 'sanctum')->getJson("/api/rombels/{$rombel->id}")->assertStatus(403);

        $this->assertDatabaseHas('rombels', ['id' => $rombel->id, 'homeroom_teacher_id' => $waliBaru->id]);
    }

    public function test_teacher_never_has_more_than_one_active_assignment(): void
    {
        $school = School::factory()->create();
        $wali = User::factory()->create(['role' => 'wali_kelas', 'school_id' => $school->id]);
        $rombelA = Rombel::factory()->create(['school_id' => $school->id]);
        $rombelB = Rombel::factory()->create(['school_id' => $school->id]);
        $rombelC = Rombel::factory()->create(['school_id' => $school->id]);

        $service = app(TeacherAssignmentService::class);
        $service->assign($wali, $rombelA);
        $service->assign($wali, $rombelB);
        $service->assign($wali, $rombelC);

        $activeCount = TeacherRombelAssignment::where('teacher_id', $wali->id)
            ->where('status', 'active')
            ->count();

        $this->assertSame(1, $activeCount);
    }

    public function test_kepala_sekolah_can_view_any_rombel_in_own_school(): void
    {
        $school = School::factory()->create();
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $school->id]);
        $rombel = Rombel::factory()->create(['school_id' => $school->id]);

        $this->actingAs($kepsek, 'sanctum')
            ->getJson("/api/rombels/{$rombel->id}")
            ->assertOk();
    }

    public function test_kepala_sekolah_cannot_view_rombel_of_other_school(): void
    {
        $ownSchool = School::factory()->create();
        $otherSchool = School::factory()->create();
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $ownSchool->id]);
        $rombel = Rombel::factory()->create(['school_id' => $otherSchool->id]);

        $this->actingAs($kepsek, 'sanctum')
            ->getJson("/api/rombels/{$rombel->id}")
            ->assertStatus(403);
    }

    public function test_only_super_admin_or_kepala_sekolah_can_assign_teacher(): void
    {
        $school = School::factory()->create();
        $wali = User::factory()->create(['role' => 'wali_kelas', 'school_id' => $school->id]);
        $rombel = Rombel::factory()->create(['school_id' => $school->id]);

        $this->actingAs($wali, 'sanctum')
            ->postJson("/api/rombels/{$rombel->id}/assign-teacher", ['teacher_id' => $wali->id])
            ->assertStatus(403);
    }

    public function test_kepala_sekolah_cannot_assign_teacher_to_other_schools_rombel(): void
    {
        $ownSchool = School::factory()->create();
        $otherSchool = School::factory()->create();
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $ownSchool->id]);
        $rombel = Rombel::factory()->create(['school_id' => $otherSchool->id]);
        $wali = User::factory()->create(['role' => 'wali_kelas', 'school_id' => $otherSchool->id]);

        $this->actingAs($kepsek, 'sanctum')
            ->postJson("/api/rombels/{$rombel->id}/assign-teacher", ['teacher_id' => $wali->id])
            ->assertStatus(403);
    }
}
