<?php

namespace App\Services\Auth;

use App\Models\StudentProfile;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

class StudentQrAuthService
{
    private const ABILITY = 'qr-login';

    /**
     * Validasi input hasil scan QR — bisa berupa RAW TOKEN mentah
     * ATAU FULL URL deep-link Frontend (format baru,
     * ANAKTUMBUH_QR_External_Scanner_Backend_Integration.docx).
     * Kalau formatnya URL, token di-extract dari query param 'token'
     * dulu sebelum divalidasi seperti biasa — supaya tetap backward
     * compatible kalau ada pemanggil lain yang masih kirim raw token.
     *
     * Pastikan milik siswa Digital yang aktif, lalu kembalikan data
     * untuk sesi login.
     *
     * @throws AuthenticationException kalau token kosong/salah/revoked/
     *                                 bukan token qr-login/siswa bukan
     *                                 Digital/siswa tidak aktif
     */
    public function loginWithQr(?string $rawInput): array
    {
        if (blank($rawInput)) {
            throw new AuthenticationException('QR token tidak boleh kosong.');
        }

        $rawToken = $this->extractToken($rawInput);

        $token = PersonalAccessToken::findToken($rawToken);

        if (! $token) {
            // Mencakup 2 kasus sekaligus: token salah, ATAU sudah di-revoke
            // (revoke = baris token dihapus dari personal_access_tokens,
            // jadi otomatis "tidak ketemu" di sini).
            $this->logAttempt(null, false, 'token_invalid_or_revoked');

            throw new AuthenticationException('QR token tidak valid atau sudah dinonaktifkan.');
        }

        if (! $token->can(self::ABILITY)) {
            $this->logAttempt($token->tokenable_id, false, 'wrong_ability');

            throw new AuthenticationException('Token ini bukan untuk login QR siswa.');
        }

        $user = $token->tokenable;

        if (! $user) {
            $this->logAttempt(null, false, 'user_not_found');

            throw new AuthenticationException('QR token tidak valid.');
        }

        $profile = StudentProfile::where('user_id', $user->id)->first();

        if (! $profile) {
            $this->logAttempt($user->id, false, 'not_student_account');

            throw new AuthenticationException('Akun ini bukan akun siswa.');
        }

        if ($profile->isManual()) {
            $this->logAttempt($user->id, false, 'manual_student');

            throw new AuthenticationException('Siswa Manual tidak menggunakan login QR.');
        }

        // FIX (ANAKTUMBUH_QR_External_Scanner_Backend_Integration.docx,
        // bagian 9): "credential valid DAN AKTIF dapat masuk ke konteks
        // siswa" — sebelumnya status aktif siswa tidak dicek, jadi siswa
        // graduated/transferred yang QR-nya belum di-revoke masih bisa
        // login.
        if (! $profile->isActive()) {
            $this->logAttempt($user->id, false, 'student_not_active');

            throw new AuthenticationException('Akun siswa tidak aktif.');
        }

        $this->logAttempt($user->id, true, null);

        return [
            'user' => $user,
            'student_profile' => $profile,
            'token' => $rawToken, // token QR yang sama dipakai lanjut sebagai bearer token
        ];
    }

    /**
     * Deteksi apakah input berupa full URL deep-link (format baru) atau
     * raw token mentah (format lama). Kalau URL, extract query param
     * 'token'. Kalau bukan URL sama sekali, anggap sudah raw token —
     * dikembalikan apa adanya (backward-compatible).
     */
    private function extractToken(string $rawInput): string
    {
        if (! filter_var($rawInput, FILTER_VALIDATE_URL)) {
            return $rawInput;
        }

        parse_str((string) parse_url($rawInput, PHP_URL_QUERY), $params);

        return $params['token'] ?? $rawInput;
    }

    /**
     * Audit log sederhana (ANAKTUMBUH_QR_External_Scanner_Backend_Integration.docx,
     * bagian 7 & 11 — "rate limit dan audit log"). Belum ada tabel
     * audit_logs terpisah di project ini, jadi dicatat lewat log channel
     * dulu — bisa dipindah ke tabel kalau tim sepakat butuh query-able log.
     */
    private function logAttempt(?int $userId, bool $success, ?string $reason): void
    {
        Log::channel('stack')->info('qr_login_attempt', [
            'success' => $success,
            'reason' => $reason,
            'user_id' => $userId,
        ]);
    }
}