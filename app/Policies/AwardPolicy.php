<?php

namespace App\Policies;

use App\Models\Award;
use App\Models\School;
use App\Models\User;

/**
 * Policy otorisasi Award:
 * - Manajemen Award per sekolah: Super Admin lintas sekolah, Kepala Sekolah hanya untuk sekolahnya sendiri.
 * - Pemberian Award (give): Super Admin, Kepala Sekolah, dan Wali Kelas.
 */
class AwardPolicy
{
    public function viewAny(User $user, School $school): bool
    {
        return $this->inScope($user, $school);
    }

    public function view(User $user, Award $award): bool
    {
        return $this->inScope($user, $award->school);
    }

    public function create(User $user, School $school): bool
    {
        return $user->can('award.manage') && $this->inScope($user, $school);
    }

    public function update(User $user, Award $award): bool
    {
        return $user->can('award.manage') && $this->inScope($user, $award->school);
    }

    public function delete(User $user, Award $award): bool
    {
        return $user->can('award.manage') && $this->inScope($user, $award->school);
    }

    /**
     * Memberi award KE siswa — boleh Wali Kelas, Kepala Sekolah, atau Super Admin.
     */
    public function give(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isKepalaSekolah() || $user->isWaliKelas();
    }

    private function inScope(User $user, School $school): bool
    {
        return $user->isSuperAdmin() || $user->school_id === $school->id;
    }
}