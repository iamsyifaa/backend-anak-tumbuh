<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ActivitySubmission;
use App\Models\Enrollment;
use App\Models\Rombel;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\TeacherAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeRombelWithTeacherAndStudent(): array
    {
        $school = School::factory()->create();
        $rombel = Rombel::factory()->create(['school_id' => $school->id]);
        $wali = User::factory()->create(['role' => User::ROLE_WALI_KELAS, 'school_id' => $school->id]);

        app(TeacherAssignmentService::class)->assign($wali, $rombel);

        $studentUser = User::factory()->create(['role' => User::ROLE_SISWA, 'school_id' => $school->id]);
        $profile = StudentProfile::factory()->create(['user_id' => $studentUser->id]);
        $year = AcademicYear::factory()->create(['school_id' => $school->id]);

        Enrollment::create([
            'student_profile_id' => $profile->id,
            'academic_year_id' => $year->id,
            'rombel_id' => $rombel->id,
            'status' => 'active',
            'started_at' => now()->subDays(10),
        ]);

        return [$wali, $rombel, $profile->fresh()];
    }

    // ── Scope: hanya rombel yang di-assign ──────────────────────

    public function test_wali_kelas_can_list_own_rombel_students(): void
    {
        [$wali, $rombel, $profile] = $this->makeRombelWithTeacherAndStudent();

        $response = $this->actingAs($wali, 'sanctum')->getJson('/api/teacher/rombel/students');

        $response->assertOk();
        $this->assertTrue(collect($response->json('data.data'))->pluck('id')->contains($profile->id));
    }

    public function test_wali_kelas_without_assignment_gets_404(): void
    {
        $wali = User::factory()->create(['role' => User::ROLE_WALI_KELAS]);

        $this->actingAs($wali, 'sanctum')->getJson('/api/teacher/rombel/students')->assertStatus(404);
    }

    public function test_wali_kelas_cannot_see_student_from_other_rombel(): void
    {
        [$waliA, $rombelA, $profileA] = $this->makeRombelWithTeacherAndStudent();
        [$waliB, $rombelB, $profileB] = $this->makeRombelWithTeacherAndStudent();

        $response = $this->actingAs($waliA, 'sanctum')
            ->getJson("/api/teacher/rombel/students/{$profileB->id}");

        $response->assertStatus(404);
    }

    public function test_non_wali_kelas_gets_404_on_teacher_endpoints(): void
    {
        $siswa = User::factory()->create(['role' => User::ROLE_SISWA]);

        $this->actingAs($siswa, 'sanctum')->getJson('/api/teacher/rombel/students')->assertStatus(404);
    }

    // ── Fitur inti ───────────────────────────────────────────────

    public function test_wali_kelas_can_view_student_detail(): void
    {
        [$wali, $rombel, $profile] = $this->makeRombelWithTeacherAndStudent();

        $this->actingAs($wali, 'sanctum')
            ->getJson("/api/teacher/rombel/students/{$profile->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $profile->id);
    }

    public function test_wali_kelas_can_view_student_activity_history(): void
    {
        [$wali, $rombel, $profile] = $this->makeRombelWithTeacherAndStudent();

        ActivitySubmission::create([
            'student_profile_id' => $profile->id, 'activity_date' => now()->toDateString(), 'status' => 'draft',
        ]);

        $this->actingAs($wali, 'sanctum')
            ->getJson("/api/teacher/rombel/students/{$profile->id}/activity")
            ->assertOk();
    }

    public function test_wali_kelas_can_view_student_progress(): void
    {
        [$wali, $rombel, $profile] = $this->makeRombelWithTeacherAndStudent();

        $response = $this->actingAs($wali, 'sanctum')
            ->getJson("/api/teacher/rombel/students/{$profile->id}/progress");

        $response->assertOk()->assertJsonStructure([
            'data' => ['days_since_enrolled', 'days_filled', 'fill_rate'],
        ]);
    }

    public function test_wali_kelas_can_view_student_achievements(): void
    {
        [$wali, $rombel, $profile] = $this->makeRombelWithTeacherAndStudent();

        $this->actingAs($wali, 'sanctum')
            ->getJson("/api/teacher/rombel/students/{$profile->id}/achievements")
            ->assertOk()
            ->assertJsonStructure(['data' => ['badges', 'awards']]);
    }

    public function test_wali_kelas_can_export_rombel_students(): void
    {
        [$wali, $rombel, $profile] = $this->makeRombelWithTeacherAndStudent();

        $response = $this->actingAs($wali, 'sanctum')->get('/api/teacher/rombel/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    // ── ⚠️ NEGATIVE TEST WAJIB: tidak ada endpoint rekap manual ────

    public function test_manual_recap_input_endpoints_do_not_exist(): void
    {
        [$wali, $rombel, $profile] = $this->makeRombelWithTeacherAndStudent();

        $forbiddenPaths = [
            '/api/teacher/rombel/students/'.$profile->id.'/manual-input',
            '/api/teacher/rombel/students/'.$profile->id.'/bulk-fill',
            '/api/teacher/rombel/students/'.$profile->id.'/copy-previous-day',
            '/api/teacher/rombel/import-manual-book',
        ];

        foreach ($forbiddenPaths as $path) {
            $response = $this->actingAs($wali, 'sanctum')->postJson($path, []);
            // 404 (route tidak terdaftar) — bukan 403/422, karena
            // endpointnya memang tidak boleh ada sama sekali.
            $response->assertStatus(404);
        }
    }

    public function test_manual_students_are_still_visible_with_method_flag(): void
    {
        [$wali, $rombel, $profile] = $this->makeRombelWithTeacherAndStudent();
        $profile->update(['method' => 'manual']);

        $response = $this->actingAs($wali, 'sanctum')
            ->getJson('/api/teacher/rombel/students?method=manual');

        $response->assertOk();
        $this->assertTrue(collect($response->json('data.data'))->pluck('id')->contains($profile->id));
    }
}
