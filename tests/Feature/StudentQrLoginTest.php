<?php

namespace Tests\Feature;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentQrLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_digital_student_qr_can_login(): void
    {
        $user = User::factory()->create();
        StudentProfile::factory()->create([
            'user_id' => $user->id,
            'method' => StudentProfile::METHOD_DIGITAL,
        ]);

        $token = $user->createToken('qr-login', ['qr-login'])->plainTextToken;

        $response = $this->postJson('/api/auth/qr-login', ['qr_token' => $token]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['user', 'student_profile', 'token']);
    }

    public function test_revoked_qr_token_is_rejected(): void
    {
        $user = User::factory()->create();
        StudentProfile::factory()->create([
            'user_id' => $user->id,
            'method' => StudentProfile::METHOD_DIGITAL,
        ]);

        $newToken = $user->createToken('qr-login', ['qr-login']);
        $plainToken = $newToken->plainTextToken;

        // Simulasikan revoke: hapus baris token-nya
        $newToken->accessToken->delete();

        $response = $this->postJson('/api/auth/qr-login', ['qr_token' => $plainToken]);

        $response->assertStatus(401);
    }

    public function test_wrong_qr_token_is_rejected(): void
    {
        $response = $this->postJson('/api/auth/qr-login', ['qr_token' => 'salah-total|abcdef']);

        $response->assertStatus(401);
    }

    public function test_missing_qr_token_is_rejected(): void
    {
        $response = $this->postJson('/api/auth/qr-login', ['qr_token' => '']);

        $response->assertStatus(422);
    }

    public function test_manual_student_qr_is_rejected(): void
    {
        $user = User::factory()->create();
        StudentProfile::factory()->create([
            'user_id' => $user->id,
            'method' => StudentProfile::METHOD_MANUAL,
        ]);

        $token = $user->createToken('qr-login', ['qr-login'])->plainTextToken;

        $response = $this->postJson('/api/auth/qr-login', ['qr_token' => $token]);

        $response->assertStatus(401);
    }

    public function test_token_with_wrong_ability_is_rejected(): void
    {
        $user = User::factory()->create();
        StudentProfile::factory()->create([
            'user_id' => $user->id,
            'method' => StudentProfile::METHOD_DIGITAL,
        ]);

        // Token asli tapi bukan untuk QR login (misal token API biasa)
        $token = $user->createToken('other-purpose', ['other-ability'])->plainTextToken;

        $response = $this->postJson('/api/auth/qr-login', ['qr_token' => $token]);

        $response->assertStatus(401);
    }
}
