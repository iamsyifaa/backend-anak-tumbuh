<?php

namespace Tests\Feature\Achievement;

use App\Models\Award;
use App\Models\Badge;
use App\Models\School;
use App\Models\StudentAward;
use App\Models\StudentBadge;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AchievementAccessTest extends TestCase
{
    use RefreshDatabase;

    private function createSiswaWithProfile(): User
    {
        $user = User::factory()->create(['role' => 'siswa', 'status' => 'active']);
        StudentProfile::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        return $user->fresh();
    }

    // ── Badge (global master) ───────────────────────────────────────────

    public function test_only_super_admin_can_create_badge(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah']);

        $this->assertTrue(Gate::forUser($admin)->allows('create', Badge::class));
        $this->assertFalse(Gate::forUser($kepsek)->allows('create', Badge::class));
    }

    public function test_all_roles_can_view_badge_list(): void
    {
        foreach (['super_admin', 'kepala_sekolah', 'wali_kelas', 'siswa'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->assertTrue(Gate::forUser($user)->allows('viewAny', Badge::class));
        }
    }

    // ── Award (global master) ───────────────────────────────────────────

    public function test_only_super_admin_can_manage_master_award(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah']);
        $award = Award::factory()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('update', $award));
        $this->assertFalse(Gate::forUser($kepsek)->allows('update', $award));
    }

    public function test_kepala_sekolah_and_wali_kelas_can_give_award(): void
    {
        $school = School::factory()->create();
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $school->id]);
        $wali = User::factory()->create(['role' => 'wali_kelas', 'school_id' => $school->id]);
        $siswa = User::factory()->create(['role' => 'siswa', 'school_id' => $school->id]);

        $this->assertTrue(Gate::forUser($kepsek)->allows('give', Award::class));
        $this->assertTrue(Gate::forUser($wali)->allows('give', Award::class));
        $this->assertFalse(Gate::forUser($siswa)->allows('give', Award::class));
    }

    public function test_wali_kelas_cannot_manage_master_award(): void
    {
        $school = School::factory()->create();
        $wali = User::factory()->create(['role' => 'wali_kelas', 'school_id' => $school->id]);
        $award = Award::factory()->create();

        $this->assertFalse(Gate::forUser($wali)->allows('update', $award));
    }

    // ── Student achievement ownership ───────────────────────────────────

    public function test_siswa_can_view_own_badge(): void
    {
        $siswa = $this->createSiswaWithProfile();
        $studentBadge = StudentBadge::factory()->create([
            'student_profile_id' => $siswa->studentProfile->id,
        ]);

        $this->actingAs($siswa, 'sanctum')
            ->getJson("/api/student-badges/{$studentBadge->id}")
            ->assertOk();
    }

    public function test_siswa_cannot_view_another_students_badge(): void
    {
        $siswaA = $this->createSiswaWithProfile();
        $siswaB = $this->createSiswaWithProfile();

        $studentBadge = StudentBadge::factory()->create([
            'student_profile_id' => $siswaB->studentProfile->id,
        ]);

        $this->actingAs($siswaA, 'sanctum')
            ->getJson("/api/student-badges/{$studentBadge->id}")
            ->assertStatus(403);
    }

    public function test_siswa_cannot_view_another_students_award(): void
    {
        $siswaA = $this->createSiswaWithProfile();
        $siswaB = $this->createSiswaWithProfile();

        $studentAward = StudentAward::factory()->create([
            'student_profile_id' => $siswaB->studentProfile->id,
        ]);

        $this->actingAs($siswaA, 'sanctum')
            ->getJson("/api/student-awards/{$studentAward->id}")
            ->assertStatus(403);
    }

    public function test_super_admin_can_view_any_students_achievement(): void
    {
        $siswa = $this->createSiswaWithProfile();
        $admin = User::factory()->create(['role' => 'super_admin']);

        $studentBadge = StudentBadge::factory()->create([
            'student_profile_id' => $siswa->studentProfile->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/student-badges/{$studentBadge->id}")
            ->assertOk();
    }

    // ── Ranking feature toggle ──────────────────────────────────────────

    public function test_kepala_sekolah_can_toggle_ranking_for_own_school(): void
    {
        $school = School::factory()->create();
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $school->id]);

        $response = $this->actingAs($kepsek, 'sanctum')
            ->putJson("/api/schools/{$school->id}/feature-settings", [
                'ranking_class_enabled' => true,
            ]);

        $response->assertOk()->assertJsonPath('data.ranking_class_enabled', true);
    }

    public function test_wali_kelas_cannot_toggle_ranking(): void
    {
        $school = School::factory()->create();
        $wali = User::factory()->create(['role' => 'wali_kelas', 'school_id' => $school->id]);

        $this->actingAs($wali, 'sanctum')
            ->putJson("/api/schools/{$school->id}/feature-settings", [
                'ranking_class_enabled' => true,
            ])->assertStatus(403);
    }

    public function test_kepala_sekolah_cannot_toggle_ranking_of_other_school(): void
    {
        $ownSchool = School::factory()->create();
        $otherSchool = School::factory()->create();
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $ownSchool->id]);

        $this->actingAs($kepsek, 'sanctum')
            ->putJson("/api/schools/{$otherSchool->id}/feature-settings", [
                'ranking_class_enabled' => true,
            ])->assertStatus(403);
    }

    public function test_all_roles_can_view_ranking_setting_within_scope(): void
    {
        $school = School::factory()->create();

        foreach (['super_admin', 'kepala_sekolah', 'wali_kelas', 'siswa'] as $role) {
            $user = User::factory()->create(['role' => $role, 'school_id' => $school->id]);

            $this->actingAs($user, 'sanctum')
                ->getJson("/api/schools/{$school->id}/feature-settings")
                ->assertOk();
        }
    }
}