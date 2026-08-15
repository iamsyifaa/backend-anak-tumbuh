<?php

namespace App\Policies;

use App\Models\ReportExport;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\TeacherAssignmentService;

/**
 * SEC-011 — "Super Admin sesuai kewenangan; Kepala Sekolah sekolah sendiri;
 * Wali Kelas rombel sendiri; siswa hanya data sendiri." Policy ini MENARIK
 * scope check dari service yang SUDAH ADA (bukan reimplement) — ownership
 * siswa dari relasi $user->studentProfile (sama seperti ChecksStudentOwnership,
 * SEC-008), scope rombel dari TeacherAssignmentService (SEC-009), scope
 * sekolah dari school_id (pola konsisten sejak ORG-002).
 *
 * PENTING: otorisasi berbasis SCOPE laporan (scope_type + scope_id), BUKAN
 * "siapa yang generate file ini" — supaya 2 Wali Kelas berbeda yang
 * (secara hipotetis) sama-sama py scope ke rombel yang sama tetap bisa
 * akses file yang sama tanpa generate ulang. Untuk kasus nyata saat ini
 * (satu rombel = satu Wali Kelas aktif), efeknya sama saja.
 */
class ReportExportPolicy
{
    public function __construct(private readonly TeacherAssignmentService $assignmentService)
    {
    }

    public function download(User $user, ReportExport $export): bool
    {
        if ($export->isExpired()) {
            return false;
        }

        return match ($export->scope_type) {
            'student' => $this->canAccessStudentScope($user, $export->scope_id),
            'rombel' => $this->canAccessRombelScope($user, $export->scope_id),
            'school' => $this->canAccessSchoolScope($user, $export->scope_id),
            default => false,
        };
    }

    private function canAccessStudentScope(User $user, int $studentProfileId): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isSiswa()) {
            return $user->studentProfile?->id === $studentProfileId;
        }

        if ($user->isWaliKelas()) {
            $activeRombelId = $this->assignmentService->getActiveRombelId($user);

            if ($activeRombelId === null) {
                return false;
            }

            return StudentProfile::query()
                ->where('id', $studentProfileId)
                ->whereHas('enrollments', fn ($q) => $q->where('rombel_id', $activeRombelId)->where('status', 'active'))
                ->exists();
            // ⚠️ ASUMSI: StudentProfile punya relasi hasMany 'enrollments' dengan
            // kolom rombel_id & status (dibuat Anggota B, MASTER-001). Kalau nama
            // relasi/kolom beda, sesuaikan baris ini — jangan ubah model mereka.
        }

        return false; // Kepala Sekolah TIDAK otomatis dapat akses laporan per-siswa individual di sini.
    }

    private function canAccessRombelScope(User $user, int $rombelId): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isWaliKelas()) {
            return $this->assignmentService->getActiveRombelId($user) === $rombelId;
        }

        return false; // Kepala Sekolah: lihat scope 'school' untuk laporan agregat, bukan per-rombel di sini.
    }

    private function canAccessSchoolScope(User $user, int $schoolId): bool
    {
        return $user->isSuperAdmin() || ($user->isKepalaSekolah() && $user->school_id === $schoolId);
    }
}