<?php

namespace Tests\Feature\Auth;

use App\Events\PasswordResetTokenIssued;
use App\Models\School;
use App\Models\User;
use App\Services\PasswordResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_change_own_password_with_correct_current_password(): void
    {
        $user = User::factory()->create(['role' => 'wali_kelas', 'password' => Hash::make('lama12345')]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/account/change-password', [
            'current_password' => 'lama12345',
            'new_password' => 'Baru12345',
            'new_password_confirmation' => 'Baru12345',
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('Baru12345', $user->fresh()->password));
    }

    public function test_change_password_rejected_with_wrong_current_password(): void
    {
        $user = User::factory()->create(['role' => 'wali_kelas', 'password' => Hash::make('lama12345')]);

        $this->actingAs($user, 'sanctum')->postJson('/api/account/change-password', [
            'current_password' => 'salah',
            'new_password' => 'Baru12345',
            'new_password_confirmation' => 'Baru12345',
        ])->assertStatus(422);
    }

    public function test_siswa_cannot_access_change_password_endpoint(): void
    {
        $siswa = User::factory()->create(['role' => 'siswa', 'password' => Hash::make('lama12345')]);

        $this->actingAs($siswa, 'sanctum')->postJson('/api/account/change-password', [
            'current_password' => 'lama12345',
            'new_password' => 'Baru12345',
            'new_password_confirmation' => 'Baru12345',
        ])->assertStatus(403);
    }

    public function test_forgot_password_issues_token_event_for_existing_active_user(): void
    {
        Event::fake([PasswordResetTokenIssued::class]);

        $user = User::factory()->create(['username' => 'wali01', 'status' => 'active']);

        $this->postJson('/api/forgot-password', ['login' => 'wali01'])->assertOk();

        Event::assertDispatched(PasswordResetTokenIssued::class, fn ($e) => $e->user->id === $user->id);
    }

    public function test_forgot_password_returns_generic_success_for_nonexistent_user(): void
    {
        // Anti user-enumeration: tidak boleh beda respons antara akun ada vs tidak ada.
        $response = $this->postJson('/api/forgot-password', ['login' => 'tidak_ada_usernya']);

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_reset_password_with_valid_token_succeeds_and_revokes_sessions(): void
    {
        $user = User::factory()->create(['username' => 'wali01', 'password' => Hash::make('lama12345')]);
        $oldToken = $user->createToken('device-1')->plainTextToken;

        $service = app(PasswordResetService::class);
        $plainResetToken = $service->issueToken($user);

        $response = $this->postJson('/api/reset-password', [
            'login' => 'wali01',
            'token' => $plainResetToken,
            'new_password' => 'Baru12345',
            'new_password_confirmation' => 'Baru12345',
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('Baru12345', $user->fresh()->password));

        // Sesi lama harus sudah tercabut.
        $this->withHeader('Authorization', "Bearer {$oldToken}")
            ->getJson('/api/me')
            ->assertStatus(401);
    }

    public function test_reset_password_with_invalid_token_fails(): void
    {
        User::factory()->create(['username' => 'wali01']);

        $this->postJson('/api/reset-password', [
            'login' => 'wali01',
            'token' => 'token-ngasal',
            'new_password' => 'Baru12345',
            'new_password_confirmation' => 'Baru12345',
        ])->assertStatus(422);
    }

    public function test_reset_token_is_single_use(): void
    {
        $user = User::factory()->create(['username' => 'wali01']);
        $service = app(PasswordResetService::class);
        $plainResetToken = $service->issueToken($user);

        $this->postJson('/api/reset-password', [
            'login' => 'wali01', 'token' => $plainResetToken,
            'new_password' => 'Baru12345', 'new_password_confirmation' => 'Baru12345',
        ])->assertOk();

        // Dipakai kedua kali harus gagal.
        $this->postJson('/api/reset-password', [
            'login' => 'wali01', 'token' => $plainResetToken,
            'new_password' => 'Lagi12345', 'new_password_confirmation' => 'Lagi12345',
        ])->assertStatus(422);
    }

    // ── Admin-triggered reset ────────────────────────────────────────────

    public function test_kepala_sekolah_can_force_reset_wali_kelas_in_own_school(): void
    {
        Event::fake([PasswordResetTokenIssued::class]);

        $school = School::factory()->create();
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $school->id]);
        $wali = User::factory()->create(['role' => 'wali_kelas', 'school_id' => $school->id]);

        $response = $this->actingAs($kepsek, 'sanctum')
            ->postJson("/api/users/{$wali->id}/force-reset-password");

        $response->assertOk();
        $this->assertTrue($wali->fresh()->must_change_password);
    }

    public function test_kepala_sekolah_cannot_force_reset_wali_kelas_in_other_school(): void
    {
        $school1 = School::factory()->create();
        $school2 = School::factory()->create();

        $kepsek = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $school1->id]);
        $waliOtherSchool = User::factory()->create(['role' => 'wali_kelas', 'school_id' => $school2->id]);

        $this->actingAs($kepsek, 'sanctum')
            ->postJson("/api/users/{$waliOtherSchool->id}/force-reset-password")
            ->assertStatus(403);
    }

    public function test_kepala_sekolah_cannot_force_reset_another_kepala_sekolah(): void
    {
        $kepsek1 = User::factory()->create(['role' => 'kepala_sekolah']);
        $kepsek2 = User::factory()->create(['role' => 'kepala_sekolah']);

        $this->actingAs($kepsek1, 'sanctum')
            ->postJson("/api/users/{$kepsek2->id}/force-reset-password")
            ->assertStatus(403);
    }

    public function test_super_admin_cannot_force_reset_another_super_admin(): void
    {
        $admin1 = User::factory()->create(['role' => 'super_admin']);
        $admin2 = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin1, 'sanctum')
            ->postJson("/api/users/{$admin2->id}/force-reset-password")
            ->assertStatus(403);
    }

    public function test_force_reset_response_never_contains_password_or_token(): void
    {
        $school = School::factory()->create();
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $school->id]);
        $wali = User::factory()->create(['role' => 'wali_kelas', 'school_id' => $school->id]);

        $response = $this->actingAs($kepsek, 'sanctum')
            ->postJson("/api/users/{$wali->id}/force-reset-password");

        $response->assertJsonMissingPath('data.password');
        $response->assertJsonMissingPath('data.token');
        $this->assertStringNotContainsString('token', strtolower(json_encode($response->json('data'))));
    }

    public function test_user_cannot_force_reset_own_password_via_admin_endpoint(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/users/{$admin->id}/force-reset-password")
            ->assertStatus(403);
    }
}
