<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_username_and_correct_password(): void
    {
        $user = User::factory()->create([
            'username' => 'wali01',
            'password' => Hash::make('rahasia123'),
            'role' => 'wali_kelas',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/login', [
            'login' => 'wali01',
            'password' => 'rahasia123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['data' => ['token', 'token_type', 'user']]);
    }

    public function test_user_can_login_with_email(): void
    {
        User::factory()->create([
            'email' => 'kepsek@sekolah.id',
            'password' => Hash::make('rahasia123'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/login', [
            'login' => 'kepsek@sekolah.id',
            'password' => 'rahasia123',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_login_rejected_with_wrong_password(): void
    {
        User::factory()->create([
            'username' => 'wali01',
            'password' => Hash::make('rahasia123'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/login', [
            'login' => 'wali01',
            'password' => 'salahpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('login');
    }

    public function test_login_rejected_for_nonexistent_user_with_generic_message(): void
    {
        $response = $this->postJson('/api/login', [
            'login' => 'tidak_ada',
            'password' => 'apapun',
        ]);

        $response->assertStatus(422);
        // Pastikan pesan tidak membedakan "akun tidak ada" vs "password salah" (anti user-enumeration).
        $this->assertSame(
            'Username/email atau password salah.',
            $response->json('errors.login.0')
        );
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'username' => 'nonaktif01',
            'password' => Hash::make('rahasia123'),
            'status' => 'inactive',
        ]);

        $response = $this->postJson('/api/login', [
            'login' => 'nonaktif01',
            'password' => 'rahasia123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_password_is_never_returned_in_response(): void
    {
        User::factory()->create([
            'username' => 'wali01',
            'password' => Hash::make('rahasia123'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/login', [
            'login' => 'wali01',
            'password' => 'rahasia123',
        ]);

        $response->assertJsonMissingPath('data.user.password');
    }

    public function test_password_is_stored_hashed_not_plaintext(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('rahasia123'),
        ]);

        $this->assertNotSame('rahasia123', $user->password);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
    }

    public function test_authenticated_user_can_fetch_me(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/me');

        $response->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function test_unauthenticated_user_cannot_fetch_me(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
    }

    public function test_user_can_logout_and_token_is_revoked(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout');

        $response->assertOk()->assertJsonPath('success', true);

        // Token yang sama dipakai lagi harus ditolak.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertStatus(401);
    }
}