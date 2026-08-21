<?php

namespace Tests\Unit;

use App\Models\SchoolFeatureSetting;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\PointTransaction;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Business\RankingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudentInYear(AcademicYear $academicYear, int $points): StudentProfile
    {
        $user = User::factory()->create();
        $profile = StudentProfile::create([
            'user_id' => $user->id,
            'full_name' => 'Siswa '.uniqid(),
            'method' => StudentProfile::METHOD_DIGITAL,
            'status' => StudentProfile::STATUS_ACTIVE,
            'birth_date' => '2015-01-01',
            'nisn' => (string) rand(1000000000, 9999999999),
        ]);

        Enrollment::create([
            'student_profile_id' => $profile->id,
            'academic_year_id' => $academicYear->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'started_at' => now(),
        ]);

        if ($points > 0) {
            PointTransaction::create([
                'user_id' => $user->id,
                'amount' => $points,
                'source_type' => 'submission_answer',
                'source_id' => 1,
                'period_date' => now()->toDateString(),
            ]);
        }

        return $profile;
    }

    public function test_student_with_more_points_ranks_higher(): void
    {
        $school = School::factory()->create();

        SchoolFeatureSetting::create([
            'school_id' => $school->id,
            'ranking_class_enabled' => true,
            'ranking_cohort_enabled' => true,
        ]);

        $academicYear = AcademicYear::create([
            'school_id' => $school->id,
            'name' => 'TA 2026/2027',
            'is_active' => true,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(10)->toDateString(),
        ]);

        $top = $this->makeStudentInYear($academicYear, 100);
        $middle = $this->makeStudentInYear($academicYear, 50);
        $bottom = $this->makeStudentInYear($academicYear, 10);

        $service = app(RankingService::class);

        $this->assertSame(1, $service->getPositionForStudent($top));
        $this->assertSame(2, $service->getPositionForStudent($middle));
        $this->assertSame(3, $service->getPositionForStudent($bottom));
    }

    public function test_ranking_uses_points_not_exp(): void
    {
        $school = School::factory()->create();
        $academicYear = AcademicYear::create([
            'school_id' => $school->id,
            'name' => 'TA 2026/2027',
            'is_active' => true,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(10)->toDateString(),
        ]);

        $student = $this->makeStudentInYear($academicYear, 100);

        $rankings = app(RankingService::class)->getRankingsForSchool($school->id);

        $this->assertSame(100, $rankings->first()['total_points']);
    }
}
