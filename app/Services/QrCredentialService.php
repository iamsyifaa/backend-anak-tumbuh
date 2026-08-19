<?php

namespace App\Services;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * QR credential = plain-text Sanctum Personal Access Token itu sendiri.
 * TIDAK ada tabel/kolom baru — reuse penuh app/Models/User (HasApiTokens)
 * dan tabel personal_access_tokens milik AUTH-001 (Anggota A).
 *
 * Cara kerja:
 * 1. Generate: buat token baru dengan nama 'qr-login', ability ['qr-login'].
 *    Sebelum itu, SEMUA token 'qr-login' lama milik user itu dihapus dulu
 *    (auto-revoke) — supaya QR lama otomatis tidak berlaku begitu QR baru
 *    dicetak (satu siswa = satu QR aktif).
 * 2. QR image berisi PLAIN TEXT token itu (format Sanctum: "id|random").
 *    Aplikasi mobile/scanner tinggal pakai isi QR itu persis sebagai
 *    Authorization: Bearer <isi_qr> — Sanctum langsung mengenalinya tanpa
 *    endpoint/logic tambahan.
 * 3. Revoke: hapus semua token 'qr-login' milik user (misal siswa lapor
 *    kartu QR hilang/dicuri).
 */
class QrCredentialService
{
    private const TOKEN_NAME = 'qr-login';

    /**
     * Generate token baru untuk 1 siswa. Otomatis revoke token qr-login
     * lama milik siswa itu (kalau ada) sebelum bikin yang baru.
     */
    public function generateForStudent(StudentProfile $profile): string
    {
        $user = $profile->user;

        $this->revokeForStudent($profile);

        $token = $user->createToken(self::TOKEN_NAME, [self::TOKEN_NAME]);

        return $token->plainTextToken;
    }

    /**
     * Generate untuk banyak siswa sekaligus. Return array
     * [student_profile_id => plainTextToken, ...]
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
}