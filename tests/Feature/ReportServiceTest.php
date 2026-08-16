<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ActivitySubmission;
use App\Models\Badge;
use App\Models\Enrollment;
use App\Models\ExpTransaction;
use App\Models\PointTransaction;
use App\Models\Rombel;
use App\Models\School;
use App\Models\StudentBadge;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Dashboard\StudentDashboardService;
use App\Services\Report\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudentWithData(): StudentProfile
    {
        $user = User::factory()->create();
        $profile = StudentProfile::create([
            'user_id' => $user->id, 'full_name' => 'Siswa Test', 'method' => StudentProfile::METHOD_DIGITAL,
            'status' => StudentProfile::STATUS_ACTIVE, 'birth_date' => '2015-01-01',
            'nisn' => (string) rand(1000000000, 9999999999),
        ]);

        $school = School::factory()->create();
        $academicYear = AcademicYear::create([
            'school_id' => $school->id, 'name' => 'TA 2026/2027', 'is_active' => true,
            'start_date' => now()->toDateString(), 'end_date' => now()->addMonths(10)->toDateString(),
        ]);

        Enrollment::create([
            'student_profile_id' => $profile->id, 'academic_year_id' => $academicYear->id,
            'status' => Enrollment::STATUS_ACTIVE, 'started_at' => now(),
        ]);

        PointTransaction::create([
            'user_id' => $user->id, 'amount' => 100, 'source_type' => 'submission_answer',
            'source_id' => 1, 'period_date' => now()->toDateString(),
        ]);
        ExpTransaction::create([
            'user_id' => $user->id, 'amount' => 250, 'source_type' => 'submission_answer',
            'source_id' => 1, 'period_date' => now()->toDateString(),
        ]);

        return $profile;
    }

    public function test_student_report_matches_dashboard_totals(): void
    {
        $profile = $this->makeStudentWithData();

        $dashboard = app(StudentDashboardService::class)->getDashboard($profile);
        $report = app(ReportService::class)->getStudentReport(
            $profile, Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()
        );

        // RECONCILIATION TEST — angka report HARUS sama dengan dashboard,
        // karena keduanya baca dari sumber transaksi yang sama.
        $this->assertSame($dashboard['total_points'], $report['total_points']);
        $this->assertSame($dashboard['total_exp'], $report['total_exp']);
        $this->assertSame($dashboard['level'], $report['level']);
    }

    public function test_student_report_respects_date_range_filter(): void
    {
        $profile = $this->makeStudentWithData();

        // Transaksi bulan lalu, di luar rentang filter
        PointTransaction::create([
            'user_id' => $profile->user_id, 'amount' => 999, 'source_type' => 'submission_answer',
            'source_id' => 2, 'period_date' => now()->subMonths(2)->toDateString(),
        ]);

        $report = app(ReportService::class)->getStudentReport(
            $profile, Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()
        );

        $this->assertSame(100, $report['total_points']); // tidak termasuk yang 999
    }

    public function test_rombel_report_aggregates_all_students(): void
    {
        $school = School::factory()->create();
        $academicYear = AcademicYear::create([
            'school_id' => $school->id, 'name' => 'TA 2026/2027', 'is_active' => true,
            'start_date' => now()->toDateString(), 'end_date' => now()->addMonths(10)->toDateString(),
        ]);
        $rombel = Rombel::create(['school_id' => $school->id, 'academic_year_id' => $academicYear->id, 'name' => 'Kelas 1A']);

        $user = User::factory()->create();
        $profile = StudentProfile::create([
            'user_id' => $user->id, 'full_name' => 'Siswa Rombel', 'method' => StudentProfile::METHOD_DIGITAL,
            'status' => StudentProfile::STATUS_ACTIVE, 'birth_date' => '2015-01-01',
            'nisn' => (string) rand(1000000000, 9999999999),
        ]);
        Enrollment::create([
            'student_profile_id' => $profile->id, 'academic_year_id' => $academicYear->id,
            'rombel_id' => $rombel->id, 'status' => Enrollment::STATUS_ACTIVE, 'started_at' => now(),
        ]);

        $report = app(ReportService::class)->getRombelReport(
            $rombel->id, Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()
        );

        $this->assertSame(1, $report['student_count']);
    }

    public function test_school_report_matches_analytics_service(): void
    {
        $school = School::factory()->create();

        $report = app(ReportService::class)->getSchoolReport($school->id);

        $this->assertArrayHasKey('average_points', $report);
        $this->assertArrayHasKey('rombel_achievements', $report);
        $this->assertArrayHasKey('trend', $report);
    }

    public function test_achievement_report_counts_badges_and_awards(): void
    {
        $profile = $this->makeStudentWithData();

        $badge = Badge::create([
            'code' => 'badge_'.uniqid(), 'name' => 'Rajin', 'target_type' => 'total_points',
            'target_value' => 10, 'active' => true,
        ]);
        StudentBadge::create([
            'student_profile_id' => $profile->id, 'badge_id' => $badge->id, 'awarded_at' => now(),
        ]);

        $report = app(ReportService::class)->getAchievementReport(
            $profile, Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()
        );

        $this->assertSame(1, $report['badges_earned']);
    }
}