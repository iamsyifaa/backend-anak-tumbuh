<?php

namespace Tests\Unit;

use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_wali_kelas_has_teacher_profile(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_WALI_KELAS]);
        $profile = TeacherProfile::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->isWaliKelas());
        $this->assertTrue($user->teacherProfile->is($profile));
        $this->assertTrue($user->profile->is($profile));
    }

    public function test_siswa_has_student_profile_with_method(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_SISWA]);
        $profile = StudentProfile::factory()->create([
            'user_id' => $user->id,
            'method' => 'manual',
        ]);

        $this->assertTrue($user->isSiswa());
        $this->assertTrue($user->profile->is($profile));
        $this->assertTrue($profile->isManual());
        $this->assertFalse($profile->isDigital());
    }

    public function test_super_admin_has_no_profile(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->assertNull($user->profile);
    }

    public function test_inactive_user_flag_is_boolean(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_INACTIVE]);

        $this->assertFalse($user->isActive());
    }

    public function test_no_duplicate_profile_allowed_per_user(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_SISWA]);
        StudentProfile::factory()->create(['user_id' => $user->id]);

        $this->expectException(QueryException::class);
        StudentProfile::factory()->create(['user_id' => $user->id]);
    }
}
