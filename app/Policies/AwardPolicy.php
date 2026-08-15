<?php

namespace App\Policies;

use App\Models\User;

class AwardPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    // Master award (definisi) — Super Admin only, sama seperti Badge.
    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    // Memberi award KE siswa — boleh Wali Kelas/Kepala Sekolah/Super
    // Admin (guru yang menilai siswanya), TIDAK boleh siswa sendiri.
    public function give(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isKepalaSekolah() || $user->isWaliKelas();
    }
}
