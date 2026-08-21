<?php

namespace Tests\Feature;

use App\Models\EducationLevel;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\ExpTransaction;
use App\Models\PointTransaction;
use App\Models\Rombel;
use App\Models\School;
use App\Models\SchoolFeatureSetting;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudentInRombel(School $school, Rombel $rombel, AcademicYear $year): StudentProfile
    {
        $user = User::factory()->create(['role' => User::ROLE_SISWA, 'school_id' => $school->id]);
        $profile = StudentProfile::factory()->create(['user_id' => $user->id]);

        Enrollment::create([
            'student_profile_id' => $profile->id,
            'academic_year_id' => $year->id,
            'rombel_id' => $rombel->id,
            'status' => 'active',
            'started_at' => now()->subDays(10),
        ]);

        return $profile->fresh();
    }

    // ── Otorisasi & scope ────────────────────────────────────────

    public function test_kepala_sekolah_can_view_own_school_overview(): void
    {
        $school = School::factory()->create();
        $kepsek = User::factory()->create(['role' => User::ROLE_KEPALA_SEKOLAH, 'school_id' => $school->id]);

        $this->actingAs($kepsek, 'sanctum')
            ->getJson("/api/schools/{$school->id}/dashboard/overview")
            ->assertOk();
    }

    public function test_kepala_sekolah_cannot_view_other_school_overview(): void
    {
        $ownSchool = School::factory()->create();
        $otherSchool = School::factory()->create();
        $kepsek = User::factory()->create(['role' => User::ROLE_KEPALA_SEKOLAH, 'school_id' => $ownSchool->id]);

        $this->actingAs($kepsek, 'sanctum')
            ->getJson("/api/schools/{$otherSchool->id}/dashboard/overview")
            ->assertStatus(403);
    }

    public function test_wali_kelas_cannot_view_school_analytics(): void
    {
        $school = School::factory()->create();
        $wali = User::factory()->create(['role' => User::ROLE_WALI_KELAS, 'school_id' => $school->id]);

        $this->actingAs($wali, 'sanctum')
            ->getJson("/api/schools/{$school->id}/dashboard/overview")
            ->assertStatus(403);
    }

    // ── Averages & class achievement ────────────────────────────

    public function test_overview_computes_school_average_points(): void
    {
        $school = School::factory()->create();
        $year = AcademicYear::factory()->create(['school_id' => $school->id]);
        $rombel = Rombel::factory()->create(['school_id' => $school->id, 'academic_year_id' => $year->id]);

        $studentA = $this->makeStudentInRombel($school, $rombel, $year);
        $studentB = $this->makeStudentInRombel($school, $rombel, $year);

        PointTransaction::create([
            'user_id' => $studentA->user_id, 'amount' => 100,
            'source_type' => 'submission_answer', 'source_id' => 1, 'period_date' => now()->toDateString(),
        ]);
        PointTransaction::create([
            'user_id' => $studentB->user_id, 'amount' => 50,
            'source_type' => 'submission_answer', 'source_id' => 2, 'period_date' => now()->toDateString(),
        ]);

        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $response = $this->actingAs($admin, 'sanctum')->getJson("/api/schools/{$school->id}/dashboard/overview");

        $response->assertOk();
        $this->assertEquals(75.0, $response->json('data.averages.avg_points'));
    }

    public function test_overview_includes_class_achievement_per_rombel(): void
    {
        $school = School::factory()->create();
        $year = AcademicYear::factory()->create(['school_id' => $school->id]);
        $rombel = Rombel::factory()->create(['school_id' => $school->id, 'academic_year_id' => $year->id]);
        $this->makeStudentInRombel($school, $rombel, $year);

        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $response = $this->actingAs($admin, 'sanctum')->getJson("/api/schools/{$school->id}/dashboard/overview");

        $response->assertOk();
        $this->assertCount(1, $response->json('data.class_achievement'));
        $this->assertSame($rombel->id, $response->json('data.class_achievement.0.rombel_id'));
    }

    // ── Ranking berbasis poin, hormati feature flag ─────────────

    public function test_ranking_hidden_when_disabled(): void
    {
        $school = School::factory()->create();
        SchoolFeatureSetting::create([
            'school_id' => $school->id, 'ranking_class_enabled' => false, 'ranking_cohort_enabled' => false,
        ]);

        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $response = $this->actingAs($admin, 'sanctum')->getJson("/api/schools/{$school->id}/dashboard/overview");

        $response->assertOk()->assertJsonPath('data.ranking.enabled', false)
            ->assertJsonPath('data.ranking.top', null);
    }

    public function test_ranking_shown_based_on_points_not_exp(): void
    {
        $school = School::factory()->create();

        SchoolFeatureSetting::create([
            'school_id' => $school->id,
            'ranking_class_enabled' => true,
            'ranking_cohort_enabled' => true,
        ]);

        $year = AcademicYear::factory()->create(['school_id' => $school->id]);
        $educationLevel = EducationLevel::create([
            'school_id' => $school->id,
            'name' => 'Kelas 5',
            'order' => 5,
            'status' => 'active',
        ]);
        $rombel = Rombel::factory()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'education_level_id' => $educationLevel->id,
        ]);

        $studentLowPointHighExp = $this->makeStudentInRombel($school, $rombel, $year);
        $studentHighPointLowExp = $this->makeStudentInRombel($school, $rombel, $year);

        PointTransaction::create([
            'user_id' => $studentLowPointHighExp->user_id, 'amount' => 10,
            'source_type' => 'x', 'source_id' => 1, 'period_date' => now()->toDateString(),
        ]);
        ExpTransaction::create([
            'user_id' => $studentLowPointHighExp->user_id, 'amount' => 500,
            'source_type' => 'x', 'source_id' => 1, 'period_date' => now()->toDateString(),
        ]);

        PointTransaction::create([
            'user_id' => $studentHighPointLowExp->user_id, 'amount' => 100,
            'source_type' => 'x', 'source_id' => 2, 'period_date' => now()->toDateString(),
        ]);

        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $response = $this->actingAs($admin, 'sanctum')->getJson("/api/schools/{$school->id}/dashboard/overview?education_level_id={$educationLevel->id}");

        // Ranking teratas harus siswa dengan poin tertinggi (100), BUKAN
        // yang EXP-nya tertinggi (500) — buktikan tidak tercampur.

        // Ranking teratas harus siswa dengan poin tertinggi (100), BUKAN
        // yang EXP-nya tertinggi (500) — buktikan tidak tercampur.
        $topUserId = $response->json('data.ranking.top.0.user_id');
        $this->assertSame($studentHighPointLowExp->user_id, $topUserId);
    }

    // ── Trend ────────────────────────────────────────────────────

    public function test_trend_returns_daily_totals_within_range(): void
    {
        $school = School::factory()->create();
        $year = AcademicYear::factory()->create(['school_id' => $school->id]);
        $rombel = Rombel::factory()->create(['school_id' => $school->id, 'academic_year_id' => $year->id]);
        $student = $this->makeStudentInRombel($school, $rombel, $year);

        PointTransaction::create([
            'user_id' => $student->user_id, 'amount' => 20,
            'source_type' => 'x', 'source_id' => 1, 'period_date' => now()->toDateString(),
        ]);

        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $response = $this->actingAs($admin, 'sanctum')->getJson("/api/schools/{$school->id}/dashboard/trend");

        $response->assertOk();
        $this->assertCount(1, $response->json('data.trend'));
    }

    // ── Rombel detail, anti-IDOR ─────────────────────────────────

    public function test_rombel_detail_returns_404_for_rombel_of_other_school(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();
        $rombelB = Rombel::factory()->create(['school_id' => $schoolB->id]);

        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/schools/{$schoolA->id}/dashboard/rombels/{$rombelB->id}");

        $response->assertStatus(404);
    }

    public function test_rombel_detail_returns_student_stats(): void
    {
        $school = School::factory()->create();
        $year = AcademicYear::factory()->create(['school_id' => $school->id]);
        $rombel = Rombel::factory()->create(['school_id' => $school->id, 'academic_year_id' => $year->id]);
        $student = $this->makeStudentInRombel($school, $rombel, $year);

        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/schools/{$school->id}/dashboard/rombels/{$rombel->id}");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['rombel_id', 'rombel_name', 'students']]);
    }

    // ── Read-only enforcement (defense-in-depth sudah ada, cek tetap jalan) ──

    public function test_no_mutation_endpoint_exists_on_dashboard_group(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/schools/{$school->id}/dashboard/overview", [])
            ->assertStatus(405);
    }
}
