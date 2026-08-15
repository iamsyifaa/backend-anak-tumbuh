<?php

namespace App\Policies;

use App\Models\Rombel;
use App\Models\User;
use App\Services\TeacherAssignmentService;

/**
 * SEC-009 — "Wali Kelas hanya dapat mengakses siswa dan aktivitas dalam
 * SATU rombel tanggung jawabnya. Guru lain TIDAK mendapat akses hanya
 * karena mengajar/menggantikan." Sumber kebenaran scope: HANYA baris
 * teacher_rombel_assignments berstatus 'active' — tidak ada jalur lain
 * (tidak ada pengecekan "mengajar mata pelajaran di rombel ini" dsb).
 */
class TeacherPolicy
{
    public function __construct(private readonly TeacherAssignmentService $assignmentService)
    {
    }

    public function viewRombel(User $user, Rombel $rombel): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isKepalaSekolah()) {
            return $user->school_id === $rombel->school_id;
        }

        if (! $user->isWaliKelas()) {
            return false;
        }

        return $this->assignmentService->getActiveRombelId($user) === $rombel->id;
    }
}