<?php

namespace App\Policies;

use App\Models\Award;
use App\Models\User;

class AwardPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Award $award): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Award $award): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, Award $award): bool
    {
        return $user->isSuperAdmin();
    }

    public function give(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isKepalaSekolah() || $user->isWaliKelas();
    }
}
