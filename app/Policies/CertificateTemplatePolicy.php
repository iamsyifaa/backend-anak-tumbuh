<?php

namespace App\Policies;

use App\Models\User;

/**
 * Mengikuti pola BadgePolicy/HabitPolicy — struktur GLOBAL (template
 * bisa dipakai lintas sekolah), jadi hanya Super Admin yang boleh
 * kelola. Sekolah tinggal PAKAI template yang ada saat generate
 * sertifikat, tidak perlu izin buat/ubah template.
 */
class CertificateTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
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
