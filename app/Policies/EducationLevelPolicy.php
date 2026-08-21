<?php

namespace App\Policies;

use App\Models\EducationLevel;
use App\Models\School;
use App\Models\User;

/**
 * TODO: education_levels BELUM ADA di dokumen resmi 01_Role_Permission_v2_0
 * (dicek 21 Agustus 2026, tidak ditemukan referensi "education" atau
 * "jenjang" di dokumen tersebut). Policy ini SEMENTARA cek role langsung
 * via canManage(), BUKAN lewat config('permissions') seperti
 * AcademicYearPolicy dkk — karena permission 'education_level.manage'
 * belum didaftarkan di sana (kalau dipaksa pakai $user->can(...) sekarang,
 * selalu return false, endpoint jadi 403 terus buat semua role).
 *
 * Setelah dikonfirmasi ke tim & didaftarkan resmi (permission
 * 'education_level.manage' ditambahkan ke config/permissions.php,
 * dan dokumen 01_Role_Permission diupdate supaya tetap mirror 1:1),
 * ganti canManage() di bawah untuk pakai $user->can('education_level.manage')
 * seperti pola Policy lain di project ini.
 */
class EducationLevelPolicy
{
    public function viewAny(User $user, School $school): bool
    {
        return $this->canManage($user) && $this->inSchoolScope($user, $school);
    }

    public function view(User $user, EducationLevel $educationLevel): bool
    {
        return $this->canManage($user) && $this->inSchoolScope($user, $educationLevel->school);
    }

    public function create(User $user, School $school): bool
    {
        return $this->canManage($user) && $this->inSchoolScope($user, $school);
    }

    public function update(User $user, EducationLevel $educationLevel): bool
    {
        return $this->canManage($user) && $this->inSchoolScope($user, $educationLevel->school);
    }

    public function delete(User $user, EducationLevel $educationLevel): bool
    {
        return $this->canManage($user) && $this->inSchoolScope($user, $educationLevel->school);
    }

    private function canManage(User $user): bool
    {
        return in_array($user->role, [User::ROLE_SUPER_ADMIN, User::ROLE_KEPALA_SEKOLAH], true);
    }

    private function inSchoolScope(User $user, School $school): bool
    {
        return $user->isSuperAdmin() || $user->school_id === $school->id;
    }
}