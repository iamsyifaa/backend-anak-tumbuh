<?php

namespace Tests\Unit;

use App\Models\Enrollment;
use App\Models\StudentProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_profile_has_digital_method_by_default(): void
    {
        $profile = StudentProfile::factory()->create();

        $this->assertTrue($profile->isDigital());
        $this->assertFalse($profile->isManual());
    }

    public function test_manual_student_is_still_regular_student(): void
    {
        $profile = StudentProfile::factory()->manual()->create();

        // Manual bukan role terpisah — tetap StudentProfile biasa.
        $this->assertTrue($profile->isManual());
        $this->assertTrue($profile->isActive());
    }

    public function test_graduated_student_is_not_deleted(): void
    {
        $profile = StudentProfile::factory()->graduated()->create();

        $this->assertDatabaseHas('student_profiles', ['id' => $profile->id]);
        $this->assertFalse($profile->isActive());
    }

    public function test_student_can_have_multiple_enrollment_history_rows(): void
    {
        $profile = StudentProfile::factory()->create();

        $old = Enrollment::factory()->ended()->create([
            'student_profile_id' => $profile->id,
        ]);
        $current = Enrollment::factory()->create([
            'student_profile_id' => $profile->id,
        ]);

        $this->assertCount(2, $profile->enrollments);
        $this->assertDatabaseHas('enrollments', ['id' => $old->id, 'status' => 'ended']);
        $this->assertTrue($profile->currentEnrollment()->first()->is($current));
    }

    public function test_only_one_active_enrollment_expected_via_service_layer(): void
    {
        // Catatan: constraint "hanya 1 active enrollment per siswa" belum
        // di-enforce di level DB/model pada task ini (MASTER-001 murni
        // foundation). Enforcement ada di StudentEnrollmentService yang
        // akan dibuat terpisah saat logic pindah kelas diimplementasikan.
        $profile = StudentProfile::factory()->create();

        Enrollment::factory()->create(['student_profile_id' => $profile->id]);
        Enrollment::factory()->create(['student_profile_id' => $profile->id]);

        $this->assertCount(2, $profile->currentEnrollment()->get());
    }
}
