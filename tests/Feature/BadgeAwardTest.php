<?php

namespace Tests\Feature;

use App\Models\Award;
use App\Models\Badge;
use App\Models\PointTransaction;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\BadgeEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BadgeAwardTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(): StudentProfile
    {
        $school = School::factory()->create();
        $user = User::factory()->create(['role' => User::ROLE_SISWA, 'school_id' => $school->id]);

        return StudentProfile::factory()->create(['user_id' => $user->id]);
    }

    // ── Otorisasi Badge (master, global) ────────────────────────

    public function test_siswa_cannot_create_badge(): void
    {
        $siswa = User::factory()->create(['role' => User::ROLE_SISWA]);

        $this->actingAs($siswa, 'sanctum')->postJson('/api/badges', [
            'code' => 'HACK', 'name' => 'Hack', 'target_type' => 'total_points', 'target_value' => 10,
        ])->assertStatus(403);
    }

    public function test_super_admin_can_create_badge(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/badges', [
            'code' => 'RAJIN_100', 'name' => 'Rajin 100 Poin', 'target_type' => 'total_points', 'target_value' => 100,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('badges', ['code' => 'RAJIN_100']);
    }

    // ── Evaluasi otomatis berdasarkan target, bukan streak ──────

    public function test_badge_awarded_when_target_points_reached(): void
    {
        $profile = $this->makeStudent();
        Badge::create([
            'code' => 'POIN_50', 'name' => 'Kumpulkan 50 Poin',
            'target_type' => Badge::TARGET_TOTAL_POINTS, 'target_value' => 50,
        ]);

        PointTransaction::create([
            'user_id' => $profile->user_id, 'amount' => 60,
            'source_type' => 'submission_answer', 'source_id' => 1, 'period_date' => now()->toDateString(),
        ]);

        $service = app(BadgeEvaluationService::class);
        $awarded = $service->checkAndAwardBadges($profile);

        $this->assertCount(1, $awarded);
        $this->assertDatabaseHas('student_badges', [
            'student_profile_id' => $profile->id,
        ]);
    }

    public function test_badge_not_awarded_when_target_not_reached(): void
    {
        $profile = $this->makeStudent();
        Badge::create([
            'code' => 'POIN_100', 'name' => 'Kumpulkan 100 Poin',
            'target_type' => Badge::TARGET_TOTAL_POINTS, 'target_value' => 100,
        ]);

        PointTransaction::create([
            'user_id' => $profile->user_id, 'amount' => 30,
            'source_type' => 'submission_answer', 'source_id' => 1, 'period_date' => now()->toDateString(),
        ]);

        $service = app(BadgeEvaluationService::class);
        $awarded = $service->checkAndAwardBadges($profile);

        $this->assertCount(0, $awarded);
    }

    public function test_badge_not_awarded_twice(): void
    {
        $profile = $this->makeStudent();
        Badge::create([
            'code' => 'POIN_10', 'name' => 'Kumpulkan 10 Poin',
            'target_type' => Badge::TARGET_TOTAL_POINTS, 'target_value' => 10,
        ]);

        PointTransaction::create([
            'user_id' => $profile->user_id, 'amount' => 20,
            'source_type' => 'submission_answer', 'source_id' => 1, 'period_date' => now()->toDateString(),
        ]);

        $service = app(BadgeEvaluationService::class);
        $service->checkAndAwardBadges($profile);
        $secondRun = $service->checkAndAwardBadges($profile);

        $this->assertCount(0, $secondRun);
        $this->assertDatabaseCount('student_badges', 1);
    }

    // ── Award: pemberian manual ─────────────────────────────────

    public function test_siswa_cannot_give_award(): void
    {
        $profile = $this->makeStudent();
        $award = Award::create(['code' => 'TELADAN', 'name' => 'Siswa Teladan']);

        $this->actingAs($profile->user, 'sanctum')
            ->postJson("/api/students/{$profile->id}/awards", ['award_id' => $award->id])
            ->assertStatus(403);
    }

    public function test_wali_kelas_can_give_award(): void
    {
        $profile = $this->makeStudent();
        $award = Award::create(['code' => 'TELADAN', 'name' => 'Siswa Teladan']);
        $wali = User::factory()->create(['role' => User::ROLE_WALI_KELAS]);

        $response = $this->actingAs($wali, 'sanctum')
            ->postJson("/api/students/{$profile->id}/awards", [
                'award_id' => $award->id,
                'note' => 'Rajin dan disiplin sepanjang semester',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('student_awards', [
            'student_profile_id' => $profile->id,
            'award_id' => $award->id,
            'given_by' => $wali->id,
        ]);
    }

    public function test_student_awards_listing_shows_who_gave_it(): void
    {
        $profile = $this->makeStudent();
        $award = Award::create(['code' => 'TELADAN', 'name' => 'Siswa Teladan']);
        $wali = User::factory()->create(['role' => User::ROLE_WALI_KELAS]);

        $this->actingAs($wali, 'sanctum')->postJson("/api/students/{$profile->id}/awards", [
            'award_id' => $award->id,
        ])->assertCreated();

        $response = $this->actingAs($profile->user, 'sanctum')
            ->getJson("/api/students/{$profile->id}/awards");

        $response->assertOk()->assertJsonPath('data.total_awards', 1);
    }
}
