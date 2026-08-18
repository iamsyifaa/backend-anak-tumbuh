<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * SEC-012 — Membuktikan rate limiting yang ditambahkan benar-benar aktif,
 * bukan cuma diklaim di checklist. Login dicoba 6 kali (limit 5/menit) —
 * percobaan ke-6 harus 429 Too Many Requests.
 */
class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        User::factory()->create([
            'username' => 'wali01',
            'password' => Hash::make('rahasia123'),
            'status' => 'active',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/login', [
                'login' => 'wali01',
                'password' => 'salahterus', // sengaja salah, fokus menguji rate limit bukan hasil login.
            ]);

            $this->assertNotEquals(429, $response->getStatusCode(), 'Percobaan ke-'.($i + 1).' seharusnya belum kena limit.');
        }

        // Percobaan ke-6 harus kena limit.
        $response = $this->postJson('/api/login', [
            'login' => 'wali01',
            'password' => 'salahterus',
        ]);

        $response->assertStatus(429);
    }

    public function test_forgot_password_is_rate_limited_after_three_attempts(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $response = $this->postJson('/api/forgot-password', ['login' => 'siapa_saja']);
            $this->assertNotEquals(429, $response->getStatusCode());
        }

        $this->postJson('/api/forgot-password', ['login' => 'siapa_saja'])
            ->assertStatus(429);
    }
}
