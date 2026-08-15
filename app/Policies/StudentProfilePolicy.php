<?php

namespace App\Policies;

use App\Models\StudentProfile;
use App\Models\User;
use App\Policies\Concerns\ChecksStudentOwnership;

class StudentProfilePolicy
{
    use ChecksStudentOwnership;

    public function view(User $user, StudentProfile $studentProfile): bool
    {
        if ($user->role === 'super_admin' || (method_exists($user, 'hasRole') && $user->hasRole('super_admin'))) {
            return true;
        }

        return $this->isOwnStudentRecord($user, $studentProfile->id);
    }
}