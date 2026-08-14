<?php

namespace App\Policies;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\User;

class AcademicYearPolicy
{
    public function viewAny(User $user, School $school): bool
    {
        return $user->can('academic_year.manage') && $this->inSchoolScope($user, $school);
        // academic_year.manage dipakai juga untuk "view" karena di permission matrix
        // tidak ada academic_year.view terpisah — hanya Super Admin/Kepala Sekolah
        // yang pernah berurusan dengan tahun ajaran sama sekali.
    }

    public function view(User $user, AcademicYear $academicYear): bool
    {
        return $user->can('academic_year.manage') && $this->inSchoolScope($user, $academicYear->school);
    }

    public function create(User $user, School $school): bool
    {
        return $user->can('academic_year.manage') && $this->inSchoolScope($user, $school);
    }

    public function update(User $user, AcademicYear $academicYear): bool
    {
        return $user->can('academic_year.manage') && $this->inSchoolScope($user, $academicYear->school);
    }

    public function activate(User $user, AcademicYear $academicYear): bool
    {
        return $user->can('academic_year.manage') && $this->inSchoolScope($user, $academicYear->school);
    }

    public function delete(User $user, AcademicYear $academicYear): bool
    {
        return $user->can('academic_year.manage') && $this->inSchoolScope($user, $academicYear->school);
    }

    private function inSchoolScope(User $user, School $school): bool
    {
        return $user->isSuperAdmin() || $user->school_id === $school->id;
    }
}