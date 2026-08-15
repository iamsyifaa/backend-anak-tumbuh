<?php

namespace App\Policies;

use App\Models\User;

/**
 * Mengikuti pola persis HabitPolicy (Anggota A) — struktur GLOBAL,
 * berdampak ke semua sekolah, jadi hanya Super Admin yang boleh ubah.
 */
class BadgePolicy
{
    public function viewAny(User $user): bool
    {
        return true; // semua role boleh lihat daftar badge yang tersedia
    }

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
}
