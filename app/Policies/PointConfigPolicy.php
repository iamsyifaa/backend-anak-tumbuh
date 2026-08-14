<?php

namespace App\Policies;

use App\Models\PointConfig;
use App\Models\School;
use App\Models\User;

/**
 * Policy untuk point_configs — pola sama persis dengan HabitConfigPolicy
 * (AUTH-004): Super Admin lintas sekolah, Kepala Sekolah hanya sekolah
 * sendiri, config published bersifat immutable (harus versi baru).
 *
 * "Poin memengaruhi histori; perubahan harus dapat diaudit" (SEC-005) —
 * bagian audit-nya BUKAN di Policy ini (Policy cuma jawab boleh/tidak),
 * tapi di PointConfigController yang memanggil AuditLogService setelah
 * Policy meloloskan aksi. Lihat PointConfigController.
 */
class PointConfigPolicy
{
    public function viewAny(User $user, School $school): bool
    {
        return $user->can('point_config.manage') && $this->inScope($user, $school);
        // Tidak ada point_config.view terpisah di permission matrix — hanya
        // role yang boleh mengelola (Super Admin/Kepala Sekolah) yang pernah
        // berurusan dengan daftar versi config poin sama sekali.
    }

    public function view(User $user, PointConfig $config): bool
    {
        return $user->can('point_config.manage') && $this->inScope($user, $config->school);
    }

    public function create(User $user, School $school): bool
    {
        return $user->can('point_config.manage') && $this->inScope($user, $school);
    }

    public function update(User $user, PointConfig $config): bool
    {
        return $user->can('point_config.manage')
            && $this->inScope($user, $config->school)
            && $config->status === 'draft';
    }

    public function publish(User $user, PointConfig $config): bool
    {
        return $user->can('point_config.manage')
            && $this->inScope($user, $config->school)
            && $config->status === 'draft';
    }

    public function delete(User $user, PointConfig $config): bool
    {
        return $user->can('point_config.manage')
            && $this->inScope($user, $config->school)
            && $config->status === 'draft'; // published = immutable, tidak bisa dihapus.
    }

    private function inScope(User $user, School $school): bool
    {
        return $user->isSuperAdmin() || $user->school_id === $school->id;
    }
}