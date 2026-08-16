<?php

namespace Tests\Feature;

use App\Models\Award;
use App\Models\CertificateTemplate;
use App\Models\Certificate;
use App\Models\StudentAward;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudentProfile(): StudentProfile
    {
        $user = User::factory()->create();

        return StudentProfile::create([
            'user_id' => $user->id,
            'full_name' => 'Siswa Test',
            'method' => StudentProfile::METHOD_DIGITAL,
            'status' => StudentProfile::STATUS_ACTIVE,
            'birth_date' => '2015-01-01',
            'nisn' => (string) rand(1000000000, 9999999999),
        ]);
    }

    public function test_certificate_is_generated_when_award_requires_it(): void
    {
        Storage::fake('local');

        $template = CertificateTemplate::create([
            'code' => 'default',
            'name' => 'Template Default',
            'active' => true,
        ]);

        $award = Award::create([
            'code' => 'award_'.uniqid(),
            'name' => 'Penghargaan Rajin',
            'generates_certificate' => true,
            'active' => true,
        ]);

        $studentProfile = $this->makeStudentProfile();

        $studentAward = StudentAward::create([
            'student_profile_id' => $studentProfile->id,
            'award_id' => $award->id,
            'given_by' => User::factory()->create()->id,
            'given_at' => now(),
        ]);

        $this->assertSame(1, Certificate::where('student_profile_id', $studentProfile->id)->count());
    }

    public function test_certificate_is_not_generated_when_award_does_not_require_it(): void
    {
        Storage::fake('local');

        CertificateTemplate::create(['code' => 'default', 'name' => 'Template Default', 'active' => true]);

        $award = Award::create([
            'code' => 'award_'.uniqid(),
            'name' => 'Penghargaan Biasa',
            'generates_certificate' => false,
            'active' => true,
        ]);

        $studentProfile = $this->makeStudentProfile();

        StudentAward::create([
            'student_profile_id' => $studentProfile->id,
            'award_id' => $award->id,
            'given_by' => User::factory()->create()->id,
            'given_at' => now(),
        ]);

        $this->assertSame(0, Certificate::count());
    }
}