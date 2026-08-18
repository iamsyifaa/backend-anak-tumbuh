<?php

namespace App\Policies;

use App\Models\School;
use App\Models\SchoolFeatureSetting;
use App\Models\User;

/**
 * Toggle ranking kelas/angkatan — "Ranking kelas dapat diaktifkan/dinonaktifkan
 * oleh sekolah" (Requirement Bagian 14). Sama pola scope dengan AwardPolicy:
 * Super Admin + Kepala Sekolah (sekolah sendiri) yang boleh ubah, semua role
 * boleh baca (perlu tahu ranking aktif/tidak untuk render UI).
 */
class SchoolFeatureSettingPolicy
{
    public function view(User $user, SchoolFeatureSetting $setting): bool
    {
        return $this->inScope($user, $setting->school);
    }

    public function update(User $user, SchoolFeatureSetting $setting): bool
    {
        return $user->can('school.manage') && $this->inScope($user, $setting->school);
        // Pakai 'school.manage' (bukan permission baru) — toggle fitur sekolah
        // masuk kategori "mengelola sekolah", konsisten dengan SchoolPolicy.
    }

    private function inScope(User $user, School $school): bool
    {
        return $user->isSuperAdmin() || $user->school_id === $school->id;
    }
}
