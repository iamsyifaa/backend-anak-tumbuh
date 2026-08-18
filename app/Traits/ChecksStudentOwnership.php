<?php

namespace App\Policies\Concerns;

use App\Models\User;

/**
 * SEC-008 — Konsolidasi logic ownership yang sebelumnya diulang-ulang di
 * SubmissionPolicy (SEC-006) dan StudentAchievementPolicy (SEC-007).
 * SATU tempat untuk jawab "apakah student_profile_id ini milik $user".
 *
 * Kenapa penting untuk hardening: sebelum ini, kalau ada bug di satu Policy
 * (mis. typo perbandingan id), bug itu TIDAK otomatis kepropagasi ke Policy
 * lain. Sekarang identitas kepemilikan cuma 1 sumber kebenaran — perbaikan
 * atau audit di sini otomatis berlaku ke SEMUA Policy yang pakai trait ini.
 */
trait ChecksStudentOwnership
{
    protected function isOwnStudentRecord(User $user, int $studentProfileId): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $user->isSiswa()) {
            return false;
            // ⚠️ Wali Kelas/Kepala Sekolah scope (rombel/sekolah) BELUM
            // diimplementasikan di sini — sama seperti catatan berulang di
            // SEC-006/SEC-007: tabel rombel belum ada. Sengaja return false
            // (deny), bukan asumsi aman, sampai scope itu benar-benar dibangun
            // dan diuji.
        }

        return $user->studentProfile?->id === $studentProfileId;
    }
}
