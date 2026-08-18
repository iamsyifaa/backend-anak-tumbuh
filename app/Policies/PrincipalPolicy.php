<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;

/**
 * SEC-010 — "Kepala Sekolah dapat melihat data sekolah sendiri, dashboard,
 * laporan, dan statistik. Tidak boleh edit dari dashboard monitoring."
 *
 * PENTING: ini BUKAN "Kepala Sekolah read-only secara umum" — dia tetap
 * bisa manage school/academic_year/teacher/dst (lihat SchoolPolicy, ORG-002).
 * Policy ini KHUSUS untuk permukaan dashboard/laporan/statistik, yang
 * memang read-only by design (tidak ada endpoint mutasi sama sekali di
 * permukaan itu — bukan cuma "ditolak", tapi memang tidak dibuat).
 */
class PrincipalPolicy
{
    public function viewDashboard(User $user, School $school): bool
    {
        return $user->isSuperAdmin() || ($user->isKepalaSekolah() && $user->school_id === $school->id);
    }
}
