<?php

namespace App\Policies;

use App\Models\StudentAward;
use App\Models\StudentBadge;
use App\Models\User;
use App\Policies\Concerns\ChecksStudentOwnership;

/**
 * Policy untuk student_badges & student_awards — "Siswa hanya melihat
 * pencapaian miliknya" (SEC-007 acceptance criteria).
 *
 * [SEC-008] Ownership check ditarik ke trait ChecksStudentOwnership,
 * dipakai bersama SubmissionPolicy & CertificatePolicy.
 */
class StudentAchievementPolicy
{
    use ChecksStudentOwnership;

    public function viewBadge(User $user, StudentBadge $studentBadge): bool
    {
        // Super Admin & Admin/Staff bisa melihat badge siapapun
        if (in_array($user->role, ['super_admin', 'admin', 'kepala_sekolah', 'wali_kelas'], true)) {
            return true;
        }

        // Siswa hanya bisa melihat badge milik sendiri (menggunakan student_profile_id)
        return $this->isOwnStudentRecord($user, $studentBadge->student_profile_id);
    }

    public function viewAward(User $user, StudentAward $studentAward): bool
    {
        // Super Admin & Admin/Staff bisa melihat award siapapun
        if (in_array($user->role, ['super_admin', 'admin', 'kepala_sekolah', 'wali_kelas'], true)) {
            return true;
        }

        // Siswa hanya bisa melihat award milik sendiri (menggunakan student_profile_id)
        return $this->isOwnStudentRecord($user, $studentAward->student_profile_id);
    }
}