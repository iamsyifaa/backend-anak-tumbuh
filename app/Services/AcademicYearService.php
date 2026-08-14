<?php

namespace App\Services;

use App\Models\AcademicYear;
use Illuminate\Support\Facades\DB;

/**
 * Hanya boleh ada SATU tahun ajaran berstatus "active" per sekolah dalam satu waktu.
 * Saat sebuah tahun ajaran diaktifkan, tahun ajaran aktif sebelumnya (jika ada)
 * otomatis dinonaktifkan — histori enrollment tidak terpengaruh (tidak dihapus).
 */
class AcademicYearService
{
    public function setActive(AcademicYear $academicYear): AcademicYear
    {
        return DB::transaction(function () use ($academicYear) {
            AcademicYear::query()
                ->where('school_id', $academicYear->school_id)
                ->where('id', '!=', $academicYear->id)
                ->where('status', 'active')
                ->update(['status' => 'inactive']);

            $academicYear->update(['status' => 'active']);

            return $academicYear->fresh();
        });
    }
}