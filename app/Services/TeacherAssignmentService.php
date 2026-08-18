<?php

namespace App\Services;

use App\Models\Rombel;
use App\Models\TeacherRombelAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * SEC-009 — "Wali Kelas tepat SATU rombel sebagai tanggung jawab."
 * Enforcement dua arah dalam SATU transaksi:
 * 1. Guru yang ditugaskan otomatis kehilangan assignment aktif SEBELUMNYA
 *    (kalau ada) — satu guru tidak bisa aktif di 2 rombel sekaligus.
 * 2. Rombel yang ditugaskan otomatis melepas wali kelas aktif SEBELUMNYA
 *    (kalau ada, mis. guru lama diganti) — satu rombel tidak bisa punya
 *    2 wali kelas aktif sekaligus.
 */
class TeacherAssignmentService
{
    public function assign(User $teacher, Rombel $rombel): TeacherRombelAssignment
    {
        return DB::transaction(function () use ($teacher, $rombel) {
            // 1. Akhiri assignment aktif guru ini di rombel LAIN (kalau ada).
            TeacherRombelAssignment::where('teacher_id', $teacher->id)
                ->where('status', 'active')
                ->where('rombel_id', '!=', $rombel->id)
                ->update(['status' => 'ended', 'ended_at' => now()]);

            // 2. Akhiri assignment aktif rombel ini oleh guru LAIN (kalau ada).
            TeacherRombelAssignment::where('rombel_id', $rombel->id)
                ->where('status', 'active')
                ->where('teacher_id', '!=', $teacher->id)
                ->update(['status' => 'ended', 'ended_at' => now()]);

            $assignment = TeacherRombelAssignment::create([
                'teacher_id' => $teacher->id,
                'rombel_id' => $rombel->id,
                'status' => 'active',
                'assigned_at' => now(),
            ]);

            $rombel->update(['homeroom_teacher_id' => $teacher->id]);

            return $assignment;
        });
    }

    public function getActiveRombelId(User $teacher): ?int
    {
        return TeacherRombelAssignment::where('teacher_id', $teacher->id)
            ->where('status', 'active')
            ->value('rombel_id');
    }
}
