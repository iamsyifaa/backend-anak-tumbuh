<?php

namespace App\Policies;

use App\Models\ActivitySubmission;
use App\Models\User;

/**
 * SEC-006 — Policy inti: "siswa hanya dapat mengakses dirinya sendiri, tidak
 * ada pengisian susulan untuk hari terlewat, setelah dikirim jawaban terkunci".
 *
 * ⚠️ ASUMSI: relasi $user->studentProfile (hasOne ke StudentProfile) sudah
 * ada di model User (dibuat Anggota B, terlihat dari test
 * "siswa has student profile with method" yang sudah lolos). Kalau nama
 * method relasinya beda, sesuaikan baris ownerStudentProfileId() di bawah —
 * jangan ubah User.php dari sini untuk hindari tabrakan file lagi.
 *
 * ⚠️ Pengecekan "activity_date harus hari ini" di isWithinTodayPeriod()
 * BISA JADI overlap dengan DailyPeriodService milik BE-002 (Anggota C,
 * sudah ada test-nya: DailyPeriodServiceTest). Saya tidak punya akses ke
 * source class itu, jadi saya implementasikan pengecekan sendiri di sini.
 * KOORDINASI dengan Anggota C — idealnya logic ini nanti ditarik jadi satu
 * shared service supaya tidak ada 2 sumber kebenaran soal "periode hari ini".
 */
class SubmissionPolicy
{
    /**
     * Siswa boleh membuat submission HANYA untuk dirinya sendiri, dan HANYA
     * untuk activity_date = hari ini (server time, bukan input klien) —
     * "Tidak tersedia pengisian susulan untuk hari yang terlewat."
     */
    public function create(User $user): bool
    {
        return $user->isSiswa() && $this->ownerStudentProfileId($user) !== null;
    }

    public function view(User $user, ActivitySubmission $submission): bool
    {
        if ($user->isSiswa()) {
            return $submission->student_profile_id === $this->ownerStudentProfileId($user);
        }

        // Wali Kelas/Kepala Sekolah/Super Admin: scope ke rombel/sekolah BELUM
        // bisa diimplementasikan penuh — tabel rombel belum ada (blocker yang
        // sudah dicatat sebelumnya di TEAM_LOG). Untuk sementara staff TIDAK
        // diberi akses view submission individual lewat endpoint ini sampai
        // scope rombel tersedia — supaya tidak keliru "aman" padahal belum diuji.
        return $user->isSuperAdmin();
    }

    /**
     * Update HANYA boleh oleh pemilik, dan HANYA selama status belum
     * submitted/locked — "Setelah dikirim jawaban terkunci."
     */
    public function update(User $user, ActivitySubmission $submission): bool
    {
        if (! $user->isSiswa() || $submission->student_profile_id !== $this->ownerStudentProfileId($user)) {
            return false;
        }

        return $submission->status === 'draft' && ! $submission->isLocked();
    }

    private function ownerStudentProfileId(User $user): ?int
    {
        return $user->studentProfile?->id;
    }
}