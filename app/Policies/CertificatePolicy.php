<?php

namespace App\Policies;

use App\Models\Certificate;
use App\Models\User;
use App\Policies\Concerns\ChecksStudentOwnership;

class CertificatePolicy
{
    use ChecksStudentOwnership;

    public function view(User $user, Certificate $certificate): bool
    {
        return $this->isOwnStudentRecord($user, $certificate->student_profile_id);
    }
}
