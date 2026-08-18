<?php

namespace App\Policies;

use App\Models\HabitConfig;
use App\Models\School;
use App\Models\User;

/**
 * Policy untuk habit_configs — "konfigurasi 7 Kebiasaan yang diizinkan"
 * PER SEKOLAH (Requirement Bagian 22: "Kepala Sekolah dapat menyesuaikan
 * konfigurasi 7 Kebiasaan yang diizinkan"). Ini beda dari HabitPolicy
 * (struktur global) — di sini Kepala Sekolah MEMANG berwenang, tapi
 * scoped ketat ke sekolahnya sendiri.
 */
class HabitConfigPolicy
{
    public function viewAny(User $user, School $school): bool
    {
        return $user->can('habit.view') && $this->inScope($user, $school);
    }

    public function view(User $user, HabitConfig $config): bool
    {
        return $user->can('habit.view') && $this->inScope($user, $config->school);
    }

    public function create(User $user, School $school): bool
    {
        return $user->can('habit.manage') && $this->inScope($user, $school);
    }

    /**
     * Update HANYA boleh untuk config berstatus 'draft'. Config yang sudah
     * 'published' bersifat immutable — perubahan harus lewat versi baru,
     * supaya histori submission yang sudah dibuat berdasarkan versi lama
     * tidak pernah berubah maknanya (immutable history principle).
     */
    public function update(User $user, HabitConfig $config): bool
    {
        return $user->can('habit.manage')
            && $this->inScope($user, $config->school)
            && $config->status === 'draft';
    }

    /**
     * Publish adalah aksi TERPISAH dari update biasa (bukan cuma ubah field
     * status) — supaya bisa diaudit khusus & dikunci permission-nya sendiri
     * kalau nanti dibutuhkan approval flow tambahan.
     */
    public function publish(User $user, HabitConfig $config): bool
    {
        return $user->can('habit.manage')
            && $this->inScope($user, $config->school)
            && $config->status === 'draft';
    }

    public function delete(User $user, HabitConfig $config): bool
    {
        return $user->can('habit.manage')
            && $this->inScope($user, $config->school)
            && $config->status === 'draft'; // published tidak boleh dihapus — histori.
    }

    private function inScope(User $user, School $school): bool
    {
        return $user->isSuperAdmin() || $user->school_id === $school->id;
    }
}
