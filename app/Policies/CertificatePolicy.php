<?php

namespace App\Policies;

use App\Models\Certificate;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\TeacherAssignmentService;

class CertificatePolicy
{
    /**
     * Keputusan tim: siswa TIDAK BOLEH lihat/download sertifikat sendiri
     * (endpoint siswa dihapus). Cuma wali kelas dari rombel siswa
     * bersangkutan dan super_admin yang boleh akses.
     *
     * Pola scope SAMA PERSIS TeacherController::studentInOwnRombel() —
     * satu-satunya sumber kebenaran rombel aktif guru adalah
     * TeacherAssignmentService::getActiveRombelId().
     */
    public function view(User $user, Certificate $certificate): bool
    {
        return $this->isHomeroomTeacherOfCertificateOwner($user, $certificate);
    }

    public function download(User $user, Certificate $certificate): bool
    {
        return $this->isHomeroomTeacherOfCertificateOwner($user, $certificate);
    }

    private function isHomeroomTeacherOfCertificateOwner(User $user, Certificate $certificate): bool
    {
        if ($user->role === 'super_admin') {
            return true;
        }

        if ($user->role !== 'wali_kelas') {
            return false;
        }

        $rombelId = app(TeacherAssignmentService::class)->getActiveRombelId($user);

        if ($rombelId === null) {
            return false;
        }

        return StudentProfile::whereHas('enrollments', function ($q) use ($rombelId) {
            $q->where('rombel_id', $rombelId)->where('status', 'active');
        })->whereKey($certificate->student_profile_id)->exists();
    }
}