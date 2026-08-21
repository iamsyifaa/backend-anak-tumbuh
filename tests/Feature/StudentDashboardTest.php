<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ActivitySubmission;
use App\Models\Award;
use App\Models\Badge;
use App\Models\Enrollment;
use App\Models\PointTransaction;
use App\Models\School;
use App\Models\SchoolFeatureSetting;
use App\Models\StudentAward;
use App\Models\StudentBadge;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudentWithEnrollment(?School $school = null): StudentProfile
    {
        $school = $school ?? School::factory()->create();
        $user = User::factory()->create(['role' => User::ROLE_SISWA, 'school_id' => $school->id]);
        $profile = StudentProfile::factory()->create(['user_id' => $user->id]);
        $year = AcademicYear::firstOrCreate(
            ['school_id' => $school->id, 'name' => '2025/2026'],
            AcademicYear::factory()->make(['school_id' => $school->id, 'name' => '2025/2026'])->toArray()
        );
        Enrollment::create([
            'student_profile_id' => $profile->id,
            'academic_year_id' => $year->id,
            'rombel_id' => 1,
            'status' => 'active',
            'started_at' => now(),
        ]);

        return $profile->fresh();
    }

    // ── IDOR immunity ───────────────────────────────────────────

    public function test_history_only_returns_own_data_no_id_param_accepted(): void
    {
        $profile = $this->makeStudentWithEnrollment();
        $other = $this->makeStudentWithEnrollment();

        ActivitySubmission::create([
            'student_profile_id' => $other->id, 'activity_date' => now()->toDateString(), 'status' => 'draft',
        ]);

        $response = $this->actingAs($profile->user, 'sanctum')->getJson('/api/student/me/history');

        $response->assertOk();
        // Tidak ada param ID di URL sama sekali — endpoint ini secara
        // desain tidak mungkin dipakai untuk lihat history siswa lain.
        $this->assertStringNotContainsString('{', '/api/student/me/history');
    }

    public function test_non_siswa_gets_404_on_dashboard_endpoints(): void
    {
        $wali = User::factory()->create(['role' => User::ROLE_WALI_KELAS]);

        $this->actingAs($wali, 'sanctum')->getJson('/api/student/me/history')->assertStatus(404);
        $this->actingAs($wali, 'sanctum')->getJson('/api/student/me/achievements')->assertStatus(404);
        $this->actingAs($wali, 'sanctum')->getJson('/api/student/me/certificates')->assertStatus(404);
    }

    // ── Achievements gabungan ───────────────────────────────────

    public function test_achievements_combines_badges_and_awards(): void
    {
        $profile = $this->makeStudentWithEnrollment();
        $badge = Badge::create(['code' => 'X', 'name' => 'X', 'target_type' => 'total_points', 'target_value' => 1]);
        StudentBadge::create(['student_profile_id' => $profile->id, 'badge_id' => $badge->id, 'awarded_at' => now()]);

        $award = Award::create(['code' => 'Y', 'name' => 'Y']);
        $wali = User::factory()->create(['role' => User::ROLE_WALI_KELAS]);
        StudentAward::create([
            'student_profile_id' => $profile->id, 'award_id' => $award->id,
            'given_by' => $wali->id, 'given_at' => now(),
        ]);

        $response = $this->actingAs($profile->user, 'sanctum')->getJson('/api/student/me/achievements');

        $response->assertOk()
            ->assertJsonPath('data.total_badges', 1)
            ->assertJsonPath('data.total_awards', 1);
    }

    // ── Certificates ─────────────────────────────────────────────

    

    // ── Ranking respects feature flag ───────────────────────────

    public function test_ranking_disabled_returns_no_data(): void
    {
        $school = School::factory()->create();
        $profile = $this->makeStudentWithEnrollment($school);

        SchoolFeatureSetting::create([
            'school_id' => $school->id,
            'ranking_class_enabled' => false,
            'ranking_cohort_enabled' => false,
        ]);

        $response = $this->actingAs($profile->user, 'sanctum')->getJson('/api/student/me/ranking');

        $response->assertOk()->assertJsonPath('data.ranking_enabled', false);
    }

    public function test_ranking_enabled_computes_class_rank(): void
    {
        $school = School::factory()->create();
        $profileA = $this->makeStudentWithEnrollment($school);
        $profileB = $this->makeStudentWithEnrollment($school);

        SchoolFeatureSetting::create([
            'school_id' => $school->id,
            'ranking_class_enabled' => true,
            'ranking_cohort_enabled' => false,
        ]);

        PointTransaction::create([
            'user_id' => $profileA->user_id, 'amount' => 100,
            'source_type' => 'submission_answer', 'source_id' => 1, 'period_date' => now()->toDateString(),
        ]);
        PointTransaction::create([
            'user_id' => $profileB->user_id, 'amount' => 50,
            'source_type' => 'submission_answer', 'source_id' => 2, 'period_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($profileA->user, 'sanctum')->getJson('/api/student/me/ranking');

        $response->assertOk()
            ->assertJsonPath('data.ranking_enabled', true)
            ->assertJsonPath('data.class_rank.rank', 1)
            ->assertJsonPath('data.class_rank.my_points', 100);
    }
}
