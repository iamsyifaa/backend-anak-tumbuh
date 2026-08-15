<?php

namespace App\Policies;

use App\Models\ActivitySubmission;
use App\Models\User;
use App\Policies\Concerns\ChecksStudentOwnership;

/**
 * SEC-006 — Policy inti: "siswa hanya dapat mengakses dirinya sendiri, tidak
 * ada pengisian susulan untuk hari terlewat, setelah dikirim jawaban terkunci".
 *
 * SEC-008 — Refactor: Ownership check ditarik ke trait ChecksStudentOwnership 
 * (dipakai bersama StudentAchievementPolicy & CertificatePolicy) agar DRY.
 */
class SubmissionPolicy
{
    use ChecksStudentOwnership;

    /**
     * Siswa boleh membuat submission HANYA untuk dirinya sendiri.
     */
    public function create(User $user): bool
    {
        return $user->isSiswa() && $this->ownerStudentProfileId($user) !== null;
    }

    /**
     * Siswa hanya bisa melihat submission miliknya sendiri (via trait).
     * Super Admin diberi akses view untuk monitoring.
     * Staff lain (Wali Kelas/Kepala Sekolah) ditolak sampai scope rombel/sekolah siap.
     */
    public function view(User $user, ActivitySubmission $submission): bool
    {
        if ($user->isSiswa()) {
            return $this->isOwnStudentRecord($user, $submission->student_profile_id);
        }

        return $user->isSuperAdmin();
    }

    /**
     * Update HANYA boleh oleh siswa pemiliknya sendiri, dan HANYA selama
     * status belum submitted/locked — "Setelah dikirim jawaban terkunci."
     */
    public function update(User $user, ActivitySubmission $submission): bool
    {
        if (! $user->isSiswa() || ! $this->isOwnStudentRecord($user, $submission->student_profile_id)) {
            return false;
        }

        return $submission->status === 'draft' && ! $submission->isLocked();
    }

    /**
     * Helper untuk mengambil ID profil siswa yang sedang login.
     */
    private function ownerStudentProfileId(User $user): ?int
    {
        return $user->studentProfile?->id;
    }
}