<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\QrCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentQrTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudentProfile(): StudentProfile
    {
        $school = School::factory()->create();
        $user = User::factory()->create(['role' => User::ROLE_SISWA, 'school_id' => $school->id]);

        return StudentProfile::factory()->create(['user_id' => $user->id]);
    }

    public function test_generate_creates_usable_sanctum_token(): void
    {
        $profile = $this->makeStudentProfile();
        $service = app(QrCredentialService::class);

        $token = $service->generateForStudent($profile);

        $this->assertNotEmpty($token);
        $this->assertTrue($service->hasActiveQr($profile));

        // FIX (ANAKTUMBUH_QR_External_Scanner_Backend_Integration.docx):
        // QR sekarang berisi FULL URL deep-link Frontend
        // (.../auth/qr?token=<raw-token>), bukan raw Sanctum token
        // mentah lagi. Raw token-nya sendiri tetap format "id|random",
        // cuma karakter '|' di-encode jadi '%7C' begitu dibungkus ke
        // query string URL — makanya dicek via query param, bukan cek
        // '|' langsung di string token secara keseluruhan.
        $this->assertStringStartsWith(config('services.frontend.qr_login_url').'?token=', $token);

        parse_str((string) parse_url($token, PHP_URL_QUERY), $params);
        $this->assertArrayHasKey('token', $params);
        $this->assertStringContainsString('|', $params['token']);
    }

    public function test_generating_new_qr_revokes_old_one(): void
    {
        $profile = $this->makeStudentProfile();
        $service = app(QrCredentialService::class);

        $firstToken = $service->generateForStudent($profile);
        $secondToken = $service->generateForStudent($profile);

        $this->assertNotSame($firstToken, $secondToken);
        // Cuma ada 1 token qr-login aktif, bukan 2
        $this->assertSame(1, $profile->user->tokens()->where('name', 'qr-login')->count());
    }

    public function test_revoke_removes_qr_token(): void
    {
        $profile = $this->makeStudentProfile();
        $service = app(QrCredentialService::class);

        $service->generateForStudent($profile);
        $this->assertTrue($service->hasActiveQr($profile));

        $service->revokeForStudent($profile);
        $this->assertFalse($service->hasActiveQr($profile));
    }

    public function test_bulk_generate_returns_token_per_student(): void
    {
        $profileA = $this->makeStudentProfile();
        $profileB = $this->makeStudentProfile();
        $service = app(QrCredentialService::class);

        $result = $service->generateBulk([$profileA, $profileB]);

        $this->assertCount(2, $result);
        $this->assertArrayHasKey($profileA->id, $result);
        $this->assertArrayHasKey($profileB->id, $result);
        $this->assertNotSame($result[$profileA->id]['token'], $result[$profileB->id]['token']);
    }

    public function test_generate_endpoint_returns_token(): void
    {
        $profile = $this->makeStudentProfile();
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/students/{$profile->id}/qr/generate");

        $response->assertOk()->assertJsonStructure(['student_profile_id', 'full_name', 'qr_token']);
    }
}