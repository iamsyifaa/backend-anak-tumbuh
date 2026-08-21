<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\Rombel;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\TeacherAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Meniru pola ReportExportSecurityTest (SEC-011) untuk CertificateDownloadController.
 * Otorisasi: hanya wali_kelas dari rombel siswa pemilik certificate, dan
 * super_admin. Siswa TIDAK BOLEH akses sama sekali (keputusan tim).
 */
class CertificateDownloadSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function createSiswaWithProfile(): User
    {
        $user = User::factory()->create(['role' => 'siswa', 'status' => 'active']);
        StudentProfile::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        return $user->fresh();
    }

    private function enrollInRombel(StudentProfile $profile, Rombel $rombel): void
    {
        $academicYear = AcademicYear::create([
            'school_id' => $rombel->school_id,
            'name' => 'TA 2026/2027',
            'is_active' => true,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(10)->toDateString(),
        ]);

        Enrollment::create([
            'student_profile_id' => $profile->id,
            'academic_year_id' => $academicYear->id,
            'rombel_id' => $rombel->id,
            'status' => Enrollment::STATUS_ACTIVE,
            'started_at' => now(),
        ]);
    }

    public function test_wali_kelas_of_own_rombel_can_download_certificate(): void
    {
        $rombel = Rombel::factory()->create();
        $wali = User::factory()->create(['role' => 'wali_kelas']);
        app(TeacherAssignmentService::class)->assign($wali, $rombel);

        $siswa = $this->createSiswaWithProfile();
        $this->enrollInRombel($siswa->studentProfile, $rombel);

        $certificate = Certificate::factory()->create([
            'student_profile_id' => $siswa->studentProfile->id,
        ]);
        Storage::disk('local')->put($certificate->file_path, 'dummy pdf content');

        $linkResponse = $this->actingAs($wali, 'sanctum')
            ->postJson("/api/certificates/{$certificate->id}/link");

        $linkResponse->assertOk()->assertJsonStructure(['data' => ['url']]);

        $url = $linkResponse->json('data.url');

        $this->actingAs($wali, 'sanctum')->get($url)->assertOk();
    }

    public function test_tampered_signed_url_is_rejected(): void
    {
        $rombel = Rombel::factory()->create();
        $wali = User::factory()->create(['role' => 'wali_kelas']);
        app(TeacherAssignmentService::class)->assign($wali, $rombel);

        $siswa = $this->createSiswaWithProfile();
        $this->enrollInRombel($siswa->studentProfile, $rombel);

        $certificate = Certificate::factory()->create([
            'student_profile_id' => $siswa->studentProfile->id,
        ]);
        Storage::disk('local')->put($certificate->file_path, 'dummy pdf content');

        $linkResponse = $this->actingAs($wali, 'sanctum')
            ->postJson("/api/certificates/{$certificate->id}/link");

        $tamperedUrl = $linkResponse->json('data.url').'&extra_param=hacked';

        $this->actingAs($wali, 'sanctum')
            ->get($tamperedUrl)
            ->assertStatus(403);
    }

    public function test_signed_url_generated_by_one_teacher_rejected_for_other_teacher(): void
    {
        $ownRombel = Rombel::factory()->create();
        $otherRombel = Rombel::factory()->create();

        $waliOwner = User::factory()->create(['role' => 'wali_kelas']);
        app(TeacherAssignmentService::class)->assign($waliOwner, $ownRombel);

        $waliOther = User::factory()->create(['role' => 'wali_kelas']);
        app(TeacherAssignmentService::class)->assign($waliOther, $otherRombel);

        $siswa = $this->createSiswaWithProfile();
        $this->enrollInRombel($siswa->studentProfile, $ownRombel);

        $certificate = Certificate::factory()->create([
            'student_profile_id' => $siswa->studentProfile->id,
        ]);
        Storage::disk('local')->put($certificate->file_path, 'dummy pdf content');

        $linkResponse = $this->actingAs($waliOwner, 'sanctum')
            ->postJson("/api/certificates/{$certificate->id}/link");

        $url = $linkResponse->json('data.url');

        // Signature VALID (link asli), tapi yang akses wali kelas rombel LAIN.
        $this->actingAs($waliOther, 'sanctum')
            ->get($url)
            ->assertStatus(403);
    }

    public function test_student_cannot_download_own_certificate(): void
    {
        $siswa = $this->createSiswaWithProfile();

        $certificate = Certificate::factory()->create([
            'student_profile_id' => $siswa->studentProfile->id,
        ]);
        Storage::disk('local')->put($certificate->file_path, 'dummy pdf content');

        $this->actingAs($siswa, 'sanctum')
            ->postJson("/api/certificates/{$certificate->id}/link")
            ->assertStatus(403);
    }

    public function test_super_admin_can_download_any_certificate(): void
    {
        $siswa = $this->createSiswaWithProfile();

        $certificate = Certificate::factory()->create([
            'student_profile_id' => $siswa->studentProfile->id,
        ]);
        Storage::disk('local')->put($certificate->file_path, 'dummy pdf content');

        $admin = User::factory()->create(['role' => 'super_admin']);

        $linkResponse = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/certificates/{$certificate->id}/link");

        $linkResponse->assertOk();

        $url = $linkResponse->json('data.url');

        $this->actingAs($admin, 'sanctum')->get($url)->assertOk();
    }

    public function test_download_returns_404_when_file_missing_from_disk(): void
    {
        $rombel = Rombel::factory()->create();
        $wali = User::factory()->create(['role' => 'wali_kelas']);
        app(TeacherAssignmentService::class)->assign($wali, $rombel);

        $siswa = $this->createSiswaWithProfile();
        $this->enrollInRombel($siswa->studentProfile, $rombel);

        $certificate = Certificate::factory()->create([
            'student_profile_id' => $siswa->studentProfile->id,
        ]);
        // Sengaja TIDAK Storage::put — file tidak pernah ada di disk.

        $linkResponse = $this->actingAs($wali, 'sanctum')
            ->postJson("/api/certificates/{$certificate->id}/link");

        $url = $linkResponse->json('data.url');

        $this->actingAs($wali, 'sanctum')->get($url)->assertStatus(404);
    }
}