<?php

namespace Tests\Feature\Security;

use App\Models\AcademicYear;
use App\Models\ReportExport;
use App\Models\Rombel;
use App\Models\School;
use App\Models\User;
use App\Services\TeacherAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TEST-001 — Regression suite konsolidasi: "Super Admin, Kepala Sekolah,
 * Wali Kelas satu rombel, Siswa diri sendiri. Cross-school, cross-rombel,
 * cross-student, direct API access." File ini TIDAK menggantikan test
 * granular di file lain (SchoolTest, TeacherRombelScopeTest, dst — yang
 * tetap jadi dokumentasi detail per-fitur), tapi jadi SATU TEMPAT untuk
 * verifikasi cepat "apakah 4 role x resource kritis semuanya masih benar"
 * dalam satu run, cocok dijalankan sebelum setiap deploy.
 */
class CrossScopeRegressionMatrixTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;

    private School $schoolB;

    private User $superAdmin;

    private User $kepsekA;

    private User $kepsekB;

    private User $waliA;

    private User $waliB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::factory()->create();
        $this->schoolB = School::factory()->create();

        $this->superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->kepsekA = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $this->schoolA->id]);
        $this->kepsekB = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $this->schoolB->id]);
        $this->waliA = User::factory()->create(['role' => 'wali_kelas', 'school_id' => $this->schoolA->id]);
        $this->waliB = User::factory()->create(['role' => 'wali_kelas', 'school_id' => $this->schoolB->id]);
    }

    public function test_school_detail_access_matrix(): void
    {
        $url = "/api/schools/{$this->schoolA->id}";

        $this->actingAs($this->superAdmin, 'sanctum')->getJson($url)->assertOk();
        $this->actingAs($this->kepsekA, 'sanctum')->getJson($url)->assertOk();
        $this->actingAs($this->kepsekB, 'sanctum')->getJson($url)->assertStatus(403);
        $this->actingAs($this->waliA, 'sanctum')->getJson($url)->assertStatus(403);
    }

    public function test_academic_year_detail_access_matrix(): void
    {
        $year = AcademicYear::factory()->create(['school_id' => $this->schoolA->id]);
        $url = "/api/schools/{$this->schoolA->id}/academic-years/{$year->id}";

        $this->actingAs($this->superAdmin, 'sanctum')->getJson($url)->assertOk();
        $this->actingAs($this->kepsekA, 'sanctum')->getJson($url)->assertOk();
        $this->actingAs($this->kepsekB, 'sanctum')->getJson($url)->assertStatus(403);
        $this->actingAs($this->waliA, 'sanctum')->getJson($url)->assertStatus(403);
    }

    public function test_rombel_scoped_access_matrix(): void
    {
        $rombel = Rombel::factory()->create(['school_id' => $this->schoolA->id]);
        app(TeacherAssignmentService::class)->assign($this->waliA, $rombel);

        $url = "/api/rombels/{$rombel->id}";

        $this->actingAs($this->superAdmin, 'sanctum')->getJson($url)->assertOk();
        $this->actingAs($this->kepsekA, 'sanctum')->getJson($url)->assertOk(); // Kepsek: semua rombel di sekolahnya.
        $this->actingAs($this->kepsekB, 'sanctum')->getJson($url)->assertStatus(403);
        $this->actingAs($this->waliA, 'sanctum')->getJson($url)->assertOk(); // pemilik assignment.
        $this->actingAs($this->waliB, 'sanctum')->getJson($url)->assertStatus(403); // guru lain, tanpa assignment.
    }

    public function test_dashboard_read_only_access_matrix(): void
    {
        $url = "/api/schools/{$this->schoolA->id}/dashboard/overview";

        $this->actingAs($this->superAdmin, 'sanctum')->getJson($url)->assertOk();
        $this->actingAs($this->kepsekA, 'sanctum')->getJson($url)->assertOk();
        $this->actingAs($this->kepsekB, 'sanctum')->getJson($url)->assertStatus(403);
        $this->actingAs($this->waliA, 'sanctum')->getJson($url)->assertStatus(403);
    }

    public function test_report_export_scope_matrix_across_school_and_rombel(): void
    {
        $rombel = Rombel::factory()->create(['school_id' => $this->schoolA->id]);
        app(TeacherAssignmentService::class)->assign($this->waliA, $rombel);

        $schoolExport = ReportExport::factory()->forSchool($this->schoolA->id)->create();
        $rombelExport = ReportExport::factory()->forRombel($rombel->id)->create();

        $this->actingAs($this->kepsekA, 'sanctum')
            ->postJson("/api/report-exports/{$schoolExport->id}/link")->assertOk();
        $this->actingAs($this->kepsekB, 'sanctum')
            ->postJson("/api/report-exports/{$schoolExport->id}/link")->assertStatus(403);
        $this->actingAs($this->waliA, 'sanctum')
            ->postJson("/api/report-exports/{$schoolExport->id}/link")->assertStatus(403);

        $this->actingAs($this->waliA, 'sanctum')
            ->postJson("/api/report-exports/{$rombelExport->id}/link")->assertOk();
        $this->actingAs($this->waliB, 'sanctum')
            ->postJson("/api/report-exports/{$rombelExport->id}/link")->assertStatus(403);
    }

    public function test_habit_config_mutation_matrix(): void
    {
        $url = "/api/schools/{$this->schoolA->id}/habit-configs";
        $payload = ['version' => 1, 'effective_date' => now()->addWeek()->toDateString()];

        $this->actingAs($this->superAdmin, 'sanctum')->postJson($url, $payload)->assertCreated();
        $this->actingAs($this->kepsekA, 'sanctum')->postJson($url, ['version' => 2] + $payload)->assertCreated();
        $this->actingAs($this->kepsekB, 'sanctum')->postJson($url, ['version' => 3] + $payload)->assertStatus(403);
        $this->actingAs($this->waliA, 'sanctum')->postJson($url, ['version' => 4] + $payload)->assertStatus(403);
    }

    public function test_point_config_mutation_matrix(): void
    {
        $url = "/api/schools/{$this->schoolA->id}/point-configs";
        $payload = ['version' => 1, 'effective_date' => now()->addWeek()->toDateString()];

        $this->actingAs($this->superAdmin, 'sanctum')->postJson($url, $payload)->assertCreated();
        $this->actingAs($this->kepsekA, 'sanctum')->postJson($url, ['version' => 2] + $payload)->assertCreated();
        $this->actingAs($this->kepsekB, 'sanctum')->postJson($url, ['version' => 3] + $payload)->assertStatus(403);
        $this->actingAs($this->waliA, 'sanctum')->postJson($url, ['version' => 4] + $payload)->assertStatus(403);
    }

    /**
     * "No critical authorization bypass": mengubah role di request body
     * TIDAK boleh mengubah hasil otorisasi — role HARUS murni dari data
     * user di database (via token), bukan dari input klien.
     */
    public function test_role_cannot_be_spoofed_via_request_payload(): void
    {
        $response = $this->actingAs($this->waliA, 'sanctum')->postJson(
            "/api/schools/{$this->schoolA->id}/academic-years",
            [
                'name' => '2099/2100',
                'start_date' => '2099-01-01',
                'end_date' => '2100-01-01',
                'role' => 'super_admin', // percobaan spoofing lewat body.
            ]
        );

        $response->assertStatus(403);
    }

    /**
     * Kepsek A mencoba membuat resource untuk sekolah B lewat body, padahal
     * URL tetap /schools/{schoolA} — school_id di body harus diabaikan total,
     * scope selalu dari route param + user->school_id, bukan dari payload.
     */
    public function test_school_id_cannot_be_spoofed_via_request_payload(): void
    {
        $response = $this->actingAs($this->kepsekA, 'sanctum')->postJson(
            "/api/schools/{$this->schoolA->id}/academic-years",
            [
                'name' => '2030/2031',
                'start_date' => '2030-01-01',
                'end_date' => '2031-01-01',
                'school_id' => $this->schoolB->id,
            ]
        );

        $response->assertCreated();
        $this->assertDatabaseHas('academic_years', [
            'name' => '2030/2031',
            'school_id' => $this->schoolA->id, // BUKAN schoolB, walau di-body coba disisipkan.
        ]);
    }
}
