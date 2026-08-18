<?php

namespace App\Http\Controllers;

use App\Models\Rombel;
use App\Models\User;
use App\Services\TeacherAssignmentService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * SEC-009 — scope tugas ini: Policy + assignment enforcement, BUKAN fitur
 * manajemen rombel lengkap (CRUD rombel per kelas/jenjang itu kemungkinan
 * MASTER-002/MASTER-009, Anggota B). Controller ini minimal: cukup untuk
 * membuktikan TeacherPolicy tegak di level API.
 */
class RombelController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TeacherAssignmentService $assignmentService) {}

    public function show(Request $request, Rombel $rombel)
    {
        $this->authorize('viewRombel', $rombel);

        return $this->success($rombel);
    }

    /**
     * POST /api/rombels/{rombel}/assign-teacher
     * Hanya Super Admin/Kepala Sekolah yang menugaskan (mengikuti
     * permission 'teacher.manage' — sama dengan class_group.manage/teacher.manage
     * di permission matrix, keduanya dimiliki Super Admin & Kepala Sekolah).
     */
    public function assignTeacher(Request $request, Rombel $rombel)
    {
        abort_unless($request->user()->can('teacher.manage'), 403);
        abort_if(
            ! $request->user()->isSuperAdmin() && $request->user()->school_id !== $rombel->school_id,
            403,
            'Anda tidak memiliki akses ke sekolah ini.'
        );

        $request->validate(['teacher_id' => ['required', 'exists:users,id']]);

        $teacher = User::findOrFail($request->input('teacher_id'));
        abort_unless($teacher->isWaliKelas(), 422, 'User yang ditugaskan harus berrole wali_kelas.');

        $assignment = $this->assignmentService->assign($teacher, $rombel);

        return $this->success($assignment, 'Wali kelas berhasil ditugaskan.');
    }
}
