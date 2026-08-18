<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\PointTransaction;
use App\Models\ReportExport;
use App\Models\Rombel;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\TeacherAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportExportGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function makeStudentInRombel(): array
    {
        $school = School::factory()->create();
        $year = AcademicYear::factory()->create(['school_id' => $school->id]);
        $rombel = Rombel::factory()->create(['school_id' => $school->id, 'academic_year_id' => $year->id]);

        $studentUser = User::factory()->create(['role' => User::ROLE_SISWA, 'school_id' => $school->id]);
        $profile = StudentProfile::factory()->create(['user_id' => $studentUser->id]);

        Enrollment::create([
            'student_profile_id' => $profile->id,
            'academic_year_id' => $year->id,
            'rombel_id' => $rombel->id,
            'status' => 'active',
            'started_at' => now()->subDays(10),
        ]);

        return [$school, $rombel, $profile->fresh()];
    }

    // ── Otorisasi scope (reuse Policy) ──────────────────────────

    public function test_siswa_can_export_own_report(): void
    {
        [$school, $rombel, $profile] = $this->makeStudentInRombel();

        $response = $this->actingAs($profile->user, 'sanctum')->postJson('/api/report-exports', [
            'scope_type' => 'student',
            'scope_id' => $profile->id,
            'format' => 'excel',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('report_exports', ['scope_type' => 'student', 'scope_id' => $profile->id]);
    }

    public function test_siswa_cannot_export_other_students_report(): void
    {
        [, , $profileA] = $this->makeStudentInRombel();
        [, , $profileB] = $this->makeStudentInRombel();

        $response = $this->actingAs($profileA->user, 'sanctum')->postJson('/api/report-exports', [
            'scope_type' => 'student',
            'scope_id' => $profileB->id,
            'format' => 'excel',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('report_exports', 0);
    }

    public function test_wali_kelas_can_export_own_rombel_report(): void
    {
        [$school, $rombel, $profile] = $this->makeStudentInRombel();
        $wali = User::factory()->create(['role' => User::ROLE_WALI_KELAS, 'school_id' => $school->id]);
        app(TeacherAssignmentService::class)->assign($wali, $rombel);

        $response = $this->actingAs($wali, 'sanctum')->postJson('/api/report-exports', [
            'scope_type' => 'rombel',
            'scope_id' => $rombel->id,
            'format' => 'pdf',
        ]);

        $response->assertCreated();
    }

    public function test_wali_kelas_cannot_export_other_rombel_report(): void
    {
        [$schoolA, $rombelA] = $this->makeStudentInRombel();
        [$schoolB, $rombelB] = $this->makeStudentInRombel();

        $waliA = User::factory()->create(['role' => User::ROLE_WALI_KELAS, 'school_id' => $schoolA->id]);
        app(TeacherAssignmentService::class)->assign($waliA, $rombelA);

        $response = $this->actingAs($waliA, 'sanctum')->postJson('/api/report-exports', [
            'scope_type' => 'rombel',
            'scope_id' => $rombelB->id,
            'format' => 'pdf',
        ]);

        $response->assertStatus(403);
    }

    public function test_kepala_sekolah_can_export_own_school_report(): void
    {
        [$school] = $this->makeStudentInRombel();
        $kepsek = User::factory()->create(['role' => User::ROLE_KEPALA_SEKOLAH, 'school_id' => $school->id]);

        $response = $this->actingAs($kepsek, 'sanctum')->postJson('/api/report-exports', [
            'scope_type' => 'school',
            'scope_id' => $school->id,
            'format' => 'excel',
        ]);

        $response->assertCreated();
    }

    public function test_kepala_sekolah_cannot_export_other_school_report(): void
    {
        [$schoolA] = $this->makeStudentInRombel();
        $schoolB = School::factory()->create();
        $kepsek = User::factory()->create(['role' => User::ROLE_KEPALA_SEKOLAH, 'school_id' => $schoolA->id]);

        $response = $this->actingAs($kepsek, 'sanctum')->postJson('/api/report-exports', [
            'scope_type' => 'school',
            'scope_id' => $schoolB->id,
            'format' => 'excel',
        ]);

        $response->assertStatus(403);
    }

    // ── File beneran ke-generate & tersimpan private ────────────

    public function test_excel_file_is_actually_created_on_local_disk(): void
    {
        [$school, $rombel, $profile] = $this->makeStudentInRombel();

        $response = $this->actingAs($profile->user, 'sanctum')->postJson('/api/report-exports', [
            'scope_type' => 'student',
            'scope_id' => $profile->id,
            'format' => 'excel',
        ]);

        $response->assertCreated();

        $export = ReportExport::first();
        Storage::disk('local')->assertExists($export->file_path);
        $this->assertStringEndsWith('.xlsx', $export->file_path);
    }

    public function test_pdf_file_is_actually_created_on_local_disk(): void
    {
        [$school, $rombel, $profile] = $this->makeStudentInRombel();

        $response = $this->actingAs($profile->user, 'sanctum')->postJson('/api/report-exports', [
            'scope_type' => 'student',
            'scope_id' => $profile->id,
            'format' => 'pdf',
        ]);

        $response->assertCreated();

        $export = ReportExport::first();
        Storage::disk('local')->assertExists($export->file_path);
        $this->assertStringEndsWith('.pdf', $export->file_path);
    }

    // ── Integrasi penuh dengan alur download SEC-011 yang sudah ada ──

    public function test_full_flow_generate_then_link_then_download(): void
    {
        [$school, $rombel, $profile] = $this->makeStudentInRombel();

        $generateResponse = $this->actingAs($profile->user, 'sanctum')->postJson('/api/report-exports', [
            'scope_type' => 'student',
            'scope_id' => $profile->id,
            'format' => 'excel',
        ]);
        $exportId = $generateResponse->json('data.report_export_id');

        $linkResponse = $this->actingAs($profile->user, 'sanctum')
            ->postJson("/api/report-exports/{$exportId}/link");
        $linkResponse->assertOk();

        $url = $linkResponse->json('data.url');
        $downloadResponse = $this->actingAs($profile->user, 'sanctum')->get($url);

        $downloadResponse->assertOk();
    }

    public function test_invalid_scope_type_is_rejected(): void
    {
        [$school, $rombel, $profile] = $this->makeStudentInRombel();

        $response = $this->actingAs($profile->user, 'sanctum')->postJson('/api/report-exports', [
            'scope_type' => 'invalid-scope',
            'scope_id' => $profile->id,
            'format' => 'excel',
        ]);

        $response->assertStatus(422);
    }
}
