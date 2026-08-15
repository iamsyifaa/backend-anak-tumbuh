<?php

namespace App\Policies;

use App\Models\User;

/**
 * Policy otorisasi Badge (Master):
 * Badges berdasar struktur GLOBAL (tidak ada school_id) — sama pola dengan HabitPolicy (AUTH-004).
 * Hanya Super Admin yang boleh mengubah struktur/kriteria badge karena berdampak
 * ke semua sekolah sekaligus. Semua role boleh melihat daftar badge (master).
 */
class BadgePolicy
{
    public function viewAny(User $user): bool
    {
        return true; // Semua role boleh lihat daftar badge yang tersedia (referensi umum, tidak sensitif).
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