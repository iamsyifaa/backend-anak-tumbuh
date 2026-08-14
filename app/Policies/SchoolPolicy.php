<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;

class SchoolPolicy
{
    /**
     * Lihat daftar sekolah: Super Admin & Kepala Sekolah (school.view).
     * Wali Kelas & Siswa tidak punya endpoint list sekolah sama sekali.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('school.view');
    }

    /**
     * Lihat detail satu sekolah: harus punya school.view DAN dalam scope
     * (Super Admin lintas sekolah; Kepala Sekolah hanya sekolahnya sendiri).
     */
    public function view(User $user, School $school): bool
    {
        return $user->can('school.view') && $this->inScope($user, $school);
    }

    /**
     * Membuat sekolah baru: HANYA Super Admin. Ini bukan soal school.manage
     * (yang juga dimiliki Kepala Sekolah), tapi karena sekolah adalah entitas
     * platform-level — Kepala Sekolah tidak bisa "membuat sekolah" karena dia
     * baru punya scope setelah sekolahnya ada & dia ditugaskan ke sana.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Update: school.manage + scope. Field mana yang boleh diubah (mis. Kepala
     * Sekolah tidak boleh ubah 'code') tetap jadi tanggung jawab controller,
     * karena itu soal field-level bukan model-level authorization.
     */
    public function update(User $user, School $school): bool
    {
        return $user->can('school.manage') && $this->inScope($user, $school);
    }

    /**
     * Nonaktifkan sekolah: HANYA Super Admin (konsisten dengan create — perubahan
     * status platform-level, bukan operasional harian Kepala Sekolah).
     */
    public function delete(User $user, School $school): bool
    {
        return $user->isSuperAdmin();
    }

    private function inScope(User $user, School $school): bool
    {
        return $user->isSuperAdmin() || $user->school_id === $school->id;
    }
}