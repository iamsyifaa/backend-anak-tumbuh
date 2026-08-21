<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * TEST-001 — awalnya placeholder ber-skip (fitur QR belum ada saat ditulis).
 *
 * ✅ UPDATE: Fitur QR sudah selesai dibangun (MASTER-003/BE-004) dan sudah
 * diuji LENGKAP di Tests\Feature\StudentQrLoginTest & Tests\Feature\StudentQrTest
 * — termasuk skenario inti "revoked qr token is rejected" yang jadi tujuan
 * placeholder ini. Implementasinya pakai Sanctum token dengan custom ability
 * (bukan tabel/kolom qr_token terpisah seperti dugaan awal saya), makanya
 * deteksi otomatis versi lama tidak pernah match.
 *
 * Test placeholder DIHAPUS (bukan di-skip lagi) untuk menghindari duplikasi
 * coverage. Yang tersisa di sini cuma bagian yang MEMANG unik saya uji sejak
 * awal: permission Gate 'student.qr.revoke' (siapa BOLEH memicu revoke),
 * yang terpisah dari mekanisme QR itu sendiri.
 */
class QrCredentialRevokeSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_and_kepala_sekolah_can_revoke_qr(): void
    {
        foreach (['super_admin', 'kepala_sekolah'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->assertTrue(Gate::forUser($user)->allows('student.qr.revoke'));
        }

        foreach (['wali_kelas', 'siswa'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->assertFalse(Gate::forUser($user)->allows('student.qr.revoke'));
        }
    }
}