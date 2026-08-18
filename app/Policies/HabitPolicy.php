<?php

namespace App\Policies;

use App\Models\User;

/**
 * Policy untuk struktur GLOBAL 7 Kebiasaan (habits, habit_indicators,
 * indicator_options) — TIDAK terikat sekolah manapun (tidak ada school_id
 * di tabelnya). Dipakai untuk 3 model sekaligus lewat 1 policy karena
 * aturan otorisasinya identik untuk ketiganya.
 *
 * PENTING (beda dengan HabitConfigPolicy): ini soal STRUKTUR/DEFINISI
 * (nama kebiasaan, indikator, pilihan jawaban) yang berlaku sama untuk
 * SEMUA sekolah — bukan soal "kebiasaan mana yang diaktifkan sekolah X",
 * itu ranahnya HabitConfig. Karena berdampak ke semua sekolah sekaligus,
 * HANYA Super Admin yang boleh mengubah struktur ini, walaupun
 * `habit.manage` di permission matrix juga dimiliki Kepala Sekolah —
 * `habit.manage` Kepala Sekolah berlaku untuk HabitConfig (scoped ke
 * sekolahnya), BUKAN untuk mengubah struktur global ini.
 */
class HabitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('habit.view');
    }

    public function view(User $user): bool
    {
        return $user->can('habit.view');
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
