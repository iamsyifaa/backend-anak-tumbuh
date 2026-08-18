<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Badge;
use App\Models\Enrollment;
use App\Models\ExpTransaction;
use App\Models\PointTransaction;
use App\Models\School;
use App\Models\StudentBadge;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Dashboard\StudentDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(): StudentProfile
    {
        $user = User::factory()->create();
        $profile = StudentProfile::create([
            'user_id' => $user->id,
            'full_name' => 'Siswa Test',
            'method' => StudentProfile::METHOD_DIGITAL,
            'status' => StudentProfile::STATUS_ACTIVE,
            'birth_date' => '2015-01-01',
            'nisn' => (string) rand(1000000000, 9999999999),
        ]);

        $school = School::factory()->create();
        $academicYear = AcademicYear::create([
            'school_id' => $school->id,
            'name' => 'TA 2026/2027',
            'is_active' => true,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(10)->toDateString(),
        ]);

        Enrollment::create([
            'student_profile_id' => $profile->id,
            'academic_year_id' => $academicYear->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'started_at' => now(),
        ]);

        return $profile;
    }

    public function test_dashboard_returns_correct_totals_and_level(): void
    {
        $profile = $this->makeStudent();

        PointTransaction::create([
            'user_id' => $profile->user_id, 'amount' => 50,
            'source_type' => 'submission_answer', 'source_id' => 1,
            'period_date' => now()->toDateString(),
        ]);
        ExpTransaction::create([
            'user_id' => $profile->user_id, 'amount' => 250,
            'source_type' => 'submission_answer', 'source_id' => 1,
            'period_date' => now()->toDateString(),
        ]);

        $dashboard = app(StudentDashboardService::class)->getDashboard($profile);

        $this->assertSame(50, $dashboard['total_points']);
        $this->assertSame(250, $dashboard['total_exp']);
        $this->assertSame(3, $dashboard['level']); // 250 exp = level 3
    }

    public function test_dashboard_response_has_stable_contract(): void
    {
        $profile = $this->makeStudent();

        $dashboard = app(StudentDashboardService::class)->getDashboard($profile);

        foreach (['today_points', 'total_points', 'total_exp', 'level', 'exp_to_next_level', 'streak', 'ranking_position', 'weekly_trend', 'recent_achievements'] as $key) {
            $this->assertArrayHasKey($key, $dashboard);
        }
    }

    public function test_weekly_trend_has_seven_days(): void
    {
        $profile = $this->makeStudent();

        $dashboard = app(StudentDashboardService::class)->getDashboard($profile);

        $this->assertCount(7, $dashboard['weekly_trend']);
    }

    public function test_recent_achievements_includes_badges(): void
    {
        $profile = $this->makeStudent();

        $badge = Badge::create([
            'code' => 'badge_'.uniqid(), 'name' => 'Rajin', 'target_type' => 'total_points',
            'target_value' => 10, 'active' => true,
        ]);
        StudentBadge::create([
            'student_profile_id' => $profile->id, 'badge_id' => $badge->id, 'awarded_at' => now(),
        ]);

        $dashboard = app(StudentDashboardService::class)->getDashboard($profile);

        $this->assertCount(1, $dashboard['recent_achievements']);
        $this->assertSame('badge', $dashboard['recent_achievements'][0]['type']);
    }
}
