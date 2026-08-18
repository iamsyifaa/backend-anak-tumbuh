<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ActivitySubmission;
use App\Models\Enrollment;
use App\Models\PointTransaction;
use App\Models\Rombel;
use App\Models\School;
use App\Models\SchoolFeatureSetting;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Analytics\SchoolAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeSchoolWithYear(): array
    {
        $school = School::factory()->create();
        $academicYear = AcademicYear::create([
            'school_id' => $school->id, 'name' => 'TA 2026/2027', 'is_active' => true,
            'start_date' => now()->toDateString(), 'end_date' => now()->addMonths(10)->toDateString(),
        ]);

        return [$school, $academicYear];
    }

    private function makeStudentWithPoints(AcademicYear $academicYear, int $points, ?Rombel $rombel = null): StudentProfile
    {
        $user = User::factory()->create();
        $profile = StudentProfile::create([
            'user_id' => $user->id, 'full_name' => 'Siswa '.uniqid(), 'method' => StudentProfile::METHOD_DIGITAL,
            'status' => StudentProfile::STATUS_ACTIVE, 'birth_date' => '2015-01-01',
            'nisn' => (string) rand(1000000000, 9999999999),
        ]);

        Enrollment::create([
            'student_profile_id' => $profile->id, 'academic_year_id' => $academicYear->id,
            'rombel_id' => $rombel?->id, 'status' => Enrollment::STATUS_ACTIVE, 'started_at' => now(),
        ]);

        if ($points > 0) {
            PointTransaction::create([
                'user_id' => $user->id, 'amount' => $points, 'source_type' => 'submission_answer',
                'source_id' => 1, 'period_date' => now()->toDateString(),
            ]);
        }

        return $profile;
    }

    public function test_school_average_is_calculated_from_points_only(): void
    {
        [$school, $academicYear] = $this->makeSchoolWithYear();

        $this->makeStudentWithPoints($academicYear, 100);
        $this->makeStudentWithPoints($academicYear, 50);

        $average = app(SchoolAnalyticsService::class)->getSchoolAveragePoints($school->id);

        $this->assertSame(75.0, $average);
    }

    public function test_no_data_leakage_across_schools(): void
    {
        [$schoolA, $yearA] = $this->makeSchoolWithYear();
        [$schoolB, $yearB] = $this->makeSchoolWithYear();

        $this->makeStudentWithPoints($yearA, 100);
        $this->makeStudentWithPoints($yearB, 999); // sekolah lain, jangan ikut kehitung

        $average = app(SchoolAnalyticsService::class)->getSchoolAveragePoints($schoolA->id);

        $this->assertSame(100.0, $average);
    }

    public function test_rombel_achievements_group_correctly(): void
    {
        [$school, $academicYear] = $this->makeSchoolWithYear();
        $rombelA = Rombel::create(['school_id' => $school->id, 'academic_year_id' => $academicYear->id, 'name' => 'Kelas 1A']);
        $rombelB = Rombel::create(['school_id' => $school->id, 'academic_year_id' => $academicYear->id, 'name' => 'Kelas 1B']);

        $this->makeStudentWithPoints($academicYear, 100, $rombelA);
        $this->makeStudentWithPoints($academicYear, 50, $rombelB);

        $achievements = app(SchoolAnalyticsService::class)->getRombelAchievements($school->id);

        $rombelAData = $achievements->firstWhere('rombel_id', $rombelA->id);
        $rombelBData = $achievements->firstWhere('rombel_id', $rombelB->id);

        $this->assertSame(100.0, $rombelAData['average_points']);
        $this->assertSame(50.0, $rombelBData['average_points']);
    }

    public function test_trend_returns_requested_number_of_days(): void
    {
        [$school] = $this->makeSchoolWithYear();

        $trend = app(SchoolAnalyticsService::class)->getSchoolTrend($school->id, 7);

        $this->assertCount(7, $trend);
    }

    public function test_ranking_data_is_null_when_disabled(): void
    {
        [$school] = $this->makeSchoolWithYear();
        SchoolFeatureSetting::create(['school_id' => $school->id, 'ranking_cohort_enabled' => false]);

        $rankingData = app(SchoolAnalyticsService::class)->getRankingData($school->id);

        $this->assertNull($rankingData);
    }

    public function test_ranking_data_reflects_points_when_enabled(): void
    {
        [$school, $academicYear] = $this->makeSchoolWithYear();
        SchoolFeatureSetting::create(['school_id' => $school->id, 'ranking_cohort_enabled' => true]);

        $this->makeStudentWithPoints($academicYear, 100);

        $rankingData = app(SchoolAnalyticsService::class)->getRankingData($school->id);

        $this->assertNotNull($rankingData);
        $this->assertSame(100, $rankingData->first()['total_points']);
    }

    public function test_participation_rate_counts_only_locked_submissions_today(): void
    {
        [$school, $academicYear] = $this->makeSchoolWithYear();

        $submitted = $this->makeStudentWithPoints($academicYear, 0);
        $notSubmitted = $this->makeStudentWithPoints($academicYear, 0);

        ActivitySubmission::create([
            'student_profile_id' => $submitted->id, 'activity_date' => now()->toDateString(), 'status' => 'locked',
        ]);

        $rate = app(SchoolAnalyticsService::class)->getTodayParticipationRate($school->id);

        $this->assertSame(50.0, $rate);
    }
}
