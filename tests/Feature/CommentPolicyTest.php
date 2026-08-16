<?php

namespace Tests\Feature;

use App\Models\ActivitySubmission;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Rombel;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\TeacherRombelAssignment;
use App\Models\User;
use App\Services\Comment\CommentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function makeSubmissionInRombel(Rombel $rombel, AcademicYear $academicYear): ActivitySubmission
    {
        $user = User::factory()->create(['role' => 'siswa']);
        $profile = StudentProfile::create([
            'user_id' => $user->id, 'full_name' => 'Siswa Test', 'method' => StudentProfile::METHOD_DIGITAL,
            'status' => StudentProfile::STATUS_ACTIVE, 'birth_date' => '2015-01-01',
            'nisn' => (string) rand(1000000000, 9999999999),
        ]);

        Enrollment::create([
            'student_profile_id' => $profile->id, 'academic_year_id' => $academicYear->id,
            'rombel_id' => $rombel->id, 'status' => Enrollment::STATUS_ACTIVE, 'started_at' => now(),
        ]);

        return ActivitySubmission::create([
            'student_profile_id' => $profile->id, 'activity_date' => now()->toDateString(), 'status' => 'draft',
        ]);
    }

    public function test_wali_kelas_can_comment_on_own_rombel_student(): void
    {
        $school = School::factory()->create();
        $academicYear = AcademicYear::create([
            'school_id' => $school->id, 'name' => 'TA 2026/2027', 'is_active' => true,
            'start_date' => now()->toDateString(), 'end_date' => now()->addMonths(10)->toDateString(),
        ]);
        $rombel = Rombel::create([
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'name' => 'Kelas 1A',
        ]);

        $teacher = User::factory()->create(['role' => 'wali_kelas', 'school_id' => $school->id]);
        TeacherRombelAssignment::create([
            'teacher_id' => $teacher->id, 'rombel_id' => $rombel->id,
            'status' => 'active', 'assigned_at' => now(),
        ]);

        $submission = $this->makeSubmissionInRombel($rombel, $academicYear);

        $this->assertTrue($teacher->can('create', [\App\Models\ActivityComment::class, $submission]));
    }

    public function test_wali_kelas_cannot_comment_on_other_rombel_student(): void
    {
        $school = School::factory()->create();
        $academicYear = AcademicYear::create([
            'school_id' => $school->id, 'name' => 'TA 2026/2027', 'is_active' => true,
            'start_date' => now()->toDateString(), 'end_date' => now()->addMonths(10)->toDateString(),
        ]);
        $ownRombel = Rombel::create([
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'name' => 'Kelas 1A',
        ]);
        $otherRombel = Rombel::create([
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'name' => 'Kelas 1B',
        ]);

        $teacher = User::factory()->create(['role' => 'wali_kelas', 'school_id' => $school->id]);
        TeacherRombelAssignment::create([
            'teacher_id' => $teacher->id, 'rombel_id' => $ownRombel->id,
            'status' => 'active', 'assigned_at' => now(),
        ]);

        $submission = $this->makeSubmissionInRombel($otherRombel, $academicYear);

        $this->assertFalse($teacher->can('create', [\App\Models\ActivityComment::class, $submission]));
    }

    public function test_student_can_comment_on_own_submission(): void
    {
        $school = School::factory()->create();
        $academicYear = AcademicYear::create([
            'school_id' => $school->id, 'name' => 'TA 2026/2027', 'is_active' => true,
            'start_date' => now()->toDateString(), 'end_date' => now()->addMonths(10)->toDateString(),
        ]);
        $rombel = Rombel::create([
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'name' => 'Kelas 1A',
        ]);
        $submission = $this->makeSubmissionInRombel($rombel, $academicYear);

        $owner = $submission->studentProfile->user;

        $this->assertTrue($owner->can('create', [\App\Models\ActivityComment::class, $submission]));
    }

    public function test_student_cannot_comment_on_another_students_submission(): void
    {
        $school = School::factory()->create();
        $academicYear = AcademicYear::create([
            'school_id' => $school->id, 'name' => 'TA 2026/2027', 'is_active' => true,
            'start_date' => now()->toDateString(), 'end_date' => now()->addMonths(10)->toDateString(),
        ]);
        $rombel = Rombel::create([
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'name' => 'Kelas 1A',
        ]);
        $submission = $this->makeSubmissionInRombel($rombel, $academicYear);

        $otherStudentUser = User::factory()->create(['role' => 'siswa']);

        $this->assertFalse($otherStudentUser->can('create', [\App\Models\ActivityComment::class, $submission]));
    }
}