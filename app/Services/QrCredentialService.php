<?php

namespace App\Services;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * QR credential = plain-text Sanctum Personal Access Token, DIBUNGKUS
 * dalam URL deep-link Frontend (ANAKTUMBUH_QR_External_Scanner_Backend_Integration.docx).
 * TIDAK ada tabel/kolom baru — reuse penuh app/Models/User (HasApiTokens)
 * dan tabel personal_access_tokens milik AUTH-001 (Anggota A).
 *
 * Cara kerja:
 * 1. Generate: buat token baru dengan nama 'qr-login', ability ['qr-login'].
 *    Sebelum itu, SEMUA token 'qr-login' lama milik user itu dihapus dulu
 *    (auto-revoke) — supaya QR lama otomatis tidak berlaku begitu QR baru
 *    dicetak (satu siswa = satu QR aktif).
 * 2. QR image berisi FULL URL Frontend, contoh:
 *    https://frontend-anak-tumbuh.vercel.app/auth/qr?token=<plainTextToken>
 *    Scan pakai kamera HP biasa/Google Lens akan langsung membuka URL ini
 *    di browser — Frontend yang extract query param 'token' lalu panggil
 *    POST /auth/qr-login dengan field qr_token = nilai itu.
 *    Base URL diatur via config('services.frontend.qr_login_url'),
 *    env QR_LOGIN_BASE_URL — supaya gampang diganti tanpa ubah kode saat
 *    deploy ke domain production yang beda.
 * 3. Revoke: hapus semua token 'qr-login' milik user (misal siswa lapor
 *    kartu QR hilang/dicuri).
 */
class QrCredentialService
{
    private const TOKEN_NAME = 'qr-login';

    /**
     * Generate token baru untuk 1 siswa, dibungkus jadi full URL siap
     * di-encode ke QR image. Otomatis revoke token qr-login lama milik
     * siswa itu (kalau ada) sebelum bikin yang baru.
     */
    public function generateForStudent(StudentProfile $profile): string
    {
        $user = $profile->user;

        $this->revokeForStudent($profile);

        $token = $user->createToken(self::TOKEN_NAME, [self::TOKEN_NAME]);

        return $this->buildLoginUrl($token->plainTextToken);
    }

    /**
     * Generate untuk banyak siswa sekaligus. Return array
     * [student_profile_id => [...], ...] — 'token' berisi FULL URL,
     * bukan raw token mentah.
     *
     * Dibungkus DB::transaction supaya atomic — kalau ada error di
     * tengah proses (misal siswa ke-N gagal), SEMUA revoke+generate
     * yang sudah terjadi di batch ini ikut di-rollback. Ini mencegah
     * kondisi "campur aduk" di mana sebagian siswa sudah dapat QR baru
     * dan sebagian lagi masih pakai QR lama padahal admin mengira
     * seluruh batch berhasil.
     */
    public function generateBulk(iterable $profiles): array
    {
        return DB::transaction(function () use ($profiles) {
            $result = [];

            foreach ($profiles as $profile) {
                $result[$profile->id] = [
                    'student_profile_id' => $profile->id,
                    'full_name' => $profile->full_name,
                    'nisn' => $profile->nisn,
                    'token' => $this->generateForStudent($profile),
                ];
            }

            return $result;
        });
    }

    public function revokeForStudent(StudentProfile $profile): void
    {
        $profile->user->tokens()->where('name', self::TOKEN_NAME)->delete();
    }

    public function hasActiveQr(StudentProfile $profile): bool
    {
        return $profile->user->tokens()->where('name', self::TOKEN_NAME)->exists();
    }

    /**
     * Bungkus raw Sanctum token jadi full URL deep-link Frontend.
     * Dipisah jadi method sendiri supaya gampang ditest/dipakai ulang.
     */
    private function buildLoginUrl(string $rawToken): string
    {
        $baseUrl = config('services.frontend.qr_login_url');

        return $baseUrl.'?token='.urlencode($rawToken);
    }
}