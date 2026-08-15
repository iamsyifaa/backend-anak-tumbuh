<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait ChecksStudentOwnership
{
    /**
     * Memeriksa apakah data record (badge/award) dimiliki oleh siswa yang sedang login.
     */
    protected function isOwnStudentRecord(User $user, mixed $record): bool
    {
        if ($user->role !== 'siswa' || ! $user->studentProfile) {
            return false;
        }

        $studentProfileId = is_object($record) ? ($record->student_profile_id ?? null) : $record;

        return (int) $user->studentProfile->id === (int) $studentProfileId;
    }

    /**
     * Memeriksa apakah data siswa yang diakses milik user siswa tersebut,
     * atau user memiliki akses institusional.
     */
    protected function checkStudentOwnership(User $user, int|string $studentProfileId): bool
    {
        if ($user->role === 'siswa') {
            return $user->studentProfile && (int) $user->studentProfile->id === (int) $studentProfileId;
        }

        return true;
    }
}