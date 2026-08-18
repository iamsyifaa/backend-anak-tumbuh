<?php

namespace App\Services\Auth;

use App\Models\StudentProfile;
use Illuminate\Auth\AuthenticationException;
use Laravel\Sanctum\PersonalAccessToken;

class StudentQrAuthService
{
    private const ABILITY = 'qr-login';

    /**
     * Validasi token mentah hasil scan QR, pastikan milik siswa Digital,
     * lalu kembalikan data untuk sesi login.
     *
     * @throws AuthenticationException kalau token kosong/salah/revoked/
     *                                 bukan token qr-login/siswa bukan Digital
     */
    public function loginWithQr(?string $rawToken): array
    {
        if (blank($rawToken)) {
            throw new AuthenticationException('QR token tidak boleh kosong.');
        }

        $token = PersonalAccessToken::findToken($rawToken);

        if (! $token) {
            // Mencakup 2 kasus sekaligus: token salah, ATAU sudah di-revoke
            // (revoke = baris token dihapus dari personal_access_tokens,
            // jadi otomatis "tidak ketemu" di sini).
            throw new AuthenticationException('QR token tidak valid atau sudah dinonaktifkan.');
        }

        if (! $token->can(self::ABILITY)) {
            throw new AuthenticationException('Token ini bukan untuk login QR siswa.');
        }

        $user = $token->tokenable;

        if (! $user) {
            throw new AuthenticationException('QR token tidak valid.');
        }

        $profile = StudentProfile::where('user_id', $user->id)->first();

        if (! $profile) {
            throw new AuthenticationException('Akun ini bukan akun siswa.');
        }

        if ($profile->isManual()) {
            throw new AuthenticationException('Siswa Manual tidak menggunakan login QR.');
        }

        return [
            'user' => $user,
            'student_profile' => $profile,
            'token' => $rawToken, // token QR yang sama dipakai lanjut sebagai bearer token
        ];
    }
}
