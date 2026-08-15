<?php

namespace Tests\Feature\Report;

use App\Models\ReportExport;
use App\Models\Rombel;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\TeacherAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ReportExportSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function createSiswaWithProfile(?School $school = null): User
    {
        $user = User::factory()->create(['role' => 'siswa', 'status' => 'active']);
        StudentProfile::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        return $user->fresh();
    }

    // ── School scope ─────────────────────────────────────────────────────

    public function test_kepala_sekolah_can_get_download_link_for_own_school_report(): void
    {
        $school = School::factory()->create();
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $school->id]);
        $export = ReportExport::factory()->forSchool($school->id)->create();

        Storage::disk('local')->put($export->file_path, 'dummy content');

        $this->actingAs($kepsek, 'sanctum')
            ->postJson("/api/report-exports/{$export->id}/link")
            ->assertOk()
            ->assertJsonStructure(['data' => ['url']]);
    }

    public function test_kepala_sekolah_cannot_get_link_for_other_schools_report(): void
    {
        $ownSchool = School::factory()->create();
        $otherSchool = School::factory()->create();
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $ownSchool->id]);
        $export = ReportExport::factory()->forSchool($otherSchool->id)->create();

        $this->actingAs($kepsek, 'sanctum')
            ->postJson("/api/report-exports/{$export->id}/link")
            ->assertStatus(403);
    }

    public function test_wali_kelas_cannot_access_school_level_report(): void
    {
        $school = School::factory()->create();
        $wali = User::factory()->create(['role' => 'wali_kelas', 'school_id' => $school->id]);
        $export = ReportExport::factory()->forSchool($school->id)->create();

        $this->actingAs($wali, 'sanctum')
            ->postJson("/api/report-exports/{$export->id}/link")
            ->assertStatus(403);
    }

    // ── Rombel scope ─────────────────────────────────────────────────────

    public function test_wali_kelas_can_access_own_rombel_report(): void
    {
        $school = School::factory()->create();
        $wali = User::factory()->create(['role' => 'wali_kelas', 'school_id' => $school->id]);
        $rombel = Rombel::factory()->create(['school_id' => $school->id]);
        app(TeacherAssignmentService::class)->assign($wali, $rombel);

        $export = ReportExport::factory()->forRombel($rombel->id)->create();

        $this->actingAs($wali, 'sanctum')
            ->postJson("/api/report-exports/{$export->id}/link")
            ->assertOk();
    }

    public function test_wali_kelas_cannot_access_other_rombel_report(): void
    {
        $school = School::factory()->create();
        $wali = User::factory()->create(['role' => 'wali_kelas', 'school_id' => $school->id]);
        $ownRombel = Rombel::factory()->create(['school_id' => $school->id]);
        $otherRombel = Rombel::factory()->create(['school_id' => $school->id]);
        app(TeacherAssignmentService::class)->assign($wali, $ownRombel);

        $export = ReportExport::factory()->forRombel($otherRombel->id)->create();

        $this->actingAs($wali, 'sanctum')
            ->postJson("/api/report-exports/{$export->id}/link")
            ->assertStatus(403);
    }

    // ── Signed URL + actual download ────────────────────────────────────

    public function test_valid_signed_link_allows_download_within_scope(): void
    {
        $school = School::factory()->create();
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $school->id]);
        $export = ReportExport::factory()->forSchool($school->id)->create();

        Storage::disk('local')->put($export->file_path, 'dummy excel content');

        $linkResponse = $this->actingAs($kepsek, 'sanctum')
            ->postJson("/api/report-exports/{$export->id}/link");

        $url = $linkResponse->json('data.url');

        $downloadResponse = $this->actingAs($kepsek, 'sanctum')->get($url);

        $downloadResponse->assertOk();
    }

    public function test_tampered_signed_url_is_rejected(): void
    {
        $school = School::factory()->create();
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $school->id]);
        $export = ReportExport::factory()->forSchool($school->id)->create();

        Storage::disk('local')->put($export->file_path, 'dummy content');

        $linkResponse = $this->actingAs($kepsek, 'sanctum')
            ->postJson("/api/report-exports/{$export->id}/link");

        $url = $linkResponse->json('data.url');
        $tamperedUrl = $url.'&extra_param=hacked';

        $this->actingAs($kepsek, 'sanctum')
            ->get($tamperedUrl)
            ->assertStatus(403); // signature invalid.
    }

    public function test_expired_export_cannot_be_downloaded_even_with_valid_signature(): void
    {
        $school = School::factory()->create();
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $school->id]);
        $export = ReportExport::factory()->forSchool($school->id)->expired()->create();

        Storage::disk('local')->put($export->file_path, 'dummy content');

        // Karena Policy cek isExpired() dulu, generateLink pun sudah ditolak.
        $this->actingAs($kepsek, 'sanctum')
            ->postJson("/api/report-exports/{$export->id}/link")
            ->assertStatus(403);
    }

    /**
     * Skenario kunci "files protected": signature valid TAPI user yang
     * mengaksesnya BUKAN pemilik scope (mis. link diteruskan ke orang lain,
     * atau user login beda di device yang sama) — tetap harus ditolak.
     */
    public function test_signed_url_generated_by_one_user_rejected_for_out_of_scope_user(): void
    {
        $ownSchool = School::factory()->create();
        $otherSchool = School::factory()->create();
        $kepsekOwner = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $ownSchool->id]);
        $kepsekOther = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $otherSchool->id]);

        $export = ReportExport::factory()->forSchool($ownSchool->id)->create();
        Storage::disk('local')->put($export->file_path, 'dummy content');

        $linkResponse = $this->actingAs($kepsekOwner, 'sanctum')
            ->postJson("/api/report-exports/{$export->id}/link");

        $url = $linkResponse->json('data.url');

        // Signature-nya VALID (link asli, tidak diutak-atik), tapi yang
        // mengakses adalah kepsek sekolah LAIN.
        $this->actingAs($kepsekOther, 'sanctum')
            ->get($url)
            ->assertStatus(403);
    }

    public function test_super_admin_can_access_any_scope_report(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $export = ReportExport::factory()->forSchool($school->id)->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/report-exports/{$export->id}/link")
            ->assertOk();
    }
}