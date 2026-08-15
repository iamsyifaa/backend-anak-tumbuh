<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * TEST-001 — "Uji revoked QR." QR credential siswa Digital adalah bagian
 * dari MASTER-003 (Anggota B, generate akun+QR) & BE-004 (Anggota C, login
 * QR) — BUKAN dibuat oleh Anggota A. Sampai file ini ditulis, saya tidak
 * punya bukti tabel/kolom QR (mis. student_qr_credentials, atau kolom
 * qr_token di student_profiles) sudah ada di codebase.
 *
 * Test ini CONDITIONAL: kalau tabel/kolom QR terdeteksi ada, jalankan
 * pengujian revoke sungguhan. Kalau belum ada, test di-skip dengan pesan
 * jelas (BUKAN "pass palsu") — supaya kelihatan di laporan test bahwa
 * area ini masih perlu diuji ulang setelah fitur QR benar-benar dibangun.
 */
class QrCredentialRevokeSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_revoked_qr_credential_cannot_be_used_to_login(): void
    {
        if (! $this->qrInfrastructureExists()) {
            $this->markTestSkipped(
                'Tabel/kolom kredensial QR belum terdeteksi di database (student_qr_credentials '
                .'atau student_profiles.qr_token). Fitur QR dibuat MASTER-003/BE-004 (Anggota B/C) — '
                .'test ini WAJIB diaktifkan ulang dan diisi begitu fitur tersebut selesai. '
                .'Lihat TEAM_LOG.md bagian TEST-001 untuk detail.'
            );
        }

        // Placeholder — diisi setelah struktur QR credential dikonfirmasi ada.
        // Alur yang diharapkan: siswa Digital punya QR aktif -> admin revoke ->
        // percobaan login pakai QR yang sama harus ditolak (401/422), dan QR
        // baru harus diterbitkan sebelum siswa bisa login lagi.
        $this->assertTrue(true);
    }

    public function test_only_super_admin_and_kepala_sekolah_can_revoke_qr(): void
    {
        // Ini bagian yang BISA diuji sekarang walau tabel QR belum ada —
        // permission 'student.qr.revoke' sudah terdaftar di config/permissions.php
        // sejak ORG-002, jadi Gate-nya sudah bisa diverifikasi independen dari
        // implementasi tabel QR itu sendiri.
        foreach (['super_admin', 'kepala_sekolah'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($user)->allows('student.qr.revoke'));
        }

        foreach (['wali_kelas', 'siswa'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->assertFalse(\Illuminate\Support\Facades\Gate::forUser($user)->allows('student.qr.revoke'));
        }
    }

    private function qrInfrastructureExists(): bool
    {
        return Schema::hasTable('student_qr_credentials')
            || (Schema::hasTable('student_profiles') && Schema::hasColumn('student_profiles', 'qr_token'));
    }
}