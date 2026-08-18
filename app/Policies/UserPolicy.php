<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Otorisasi siapa yang boleh memicu force reset password akun pengguna lain.
     */
    public function resetPassword(User $actor, User $target): bool
    {
        // 1. Tidak bisa mereset password diri sendiri lewat admin endpoint
        if ($actor->id === $target->id) {
            return false;
        }

        // 2. Super Admin bisa reset siapa saja KECUALI sesama Super Admin
        $isSuperAdmin = method_exists($actor, 'isSuperAdmin') ? $actor->isSuperAdmin() : $actor->role === 'super_admin';
        if ($isSuperAdmin) {
            return $target->role !== 'super_admin';
        }

        // 3. Kepala Sekolah hanya bisa reset Wali Kelas di sekolah yang SAMA
        $isKepalaSekolah = method_exists($actor, 'isKepalaSekolah') ? $actor->isKepalaSekolah() : $actor->role === 'kepala_sekolah';
        if ($isKepalaSekolah) {
            if ($target->role !== 'wali_kelas') {
                return false;
            }

            // Jika salah satu tidak punya school_id (null), dianggap tidak di sekolah yang sama
            if (is_null($actor->school_id) || is_null($target->school_id)) {
                return false;
            }

            return (int) $actor->school_id === (int) $target->school_id;
        }

        return false;
    }
}
