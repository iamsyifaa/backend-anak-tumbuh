<?php

namespace App\Http\Controllers;

use App\Http\Requests\Rombel\StoreRombelRequest;
use App\Http\Requests\Rombel\UpdateRombelRequest;
use App\Models\Rombel;
use App\Models\User;
use App\Services\TeacherAssignmentService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * SEC-009 (assignTeacher, show) — Anggota A.
 * MASTER-xxx (index, store, update, destroy) — CRUD rombel penuh,
 * Anggota B, sesuai catatan koordinasi di TEAM_LOG.
 */
class RombelController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TeacherAssignmentService $assignmentService) {}

    public function index(Request $request)
    {
        $this->authorizeManage($request);

        $rombels = Rombel::where('school_id', $request->query('school_id'))
            ->with(['academicYear', 'educationLevel', 'homeroomTeacher'])
            ->orderBy('name')
            ->get();

        return $this->success($rombels);
    }

    public function show(Request $request, Rombel $rombel)
    {
        $this->authorize('viewRombel', $rombel);

        return $this->success($rombel);
    }

    public function store(StoreRombelRequest $request)
    {
        $this->authorizeSchoolScope($request, (int) $request->validated('school_id'));

        $rombel = Rombel::create($request->validated());

        return $this->success($rombel, 'Rombel berhasil dibuat.', 201);
    }

    public function update(UpdateRombelRequest $request, Rombel $rombel)
    {
        $this->authorizeSchoolScope($request, $rombel->school_id);

        $rombel->update($request->validated());

        return $this->success($rombel->fresh(), 'Rombel berhasil diperbarui.');
    }

    public function destroy(Request $request, Rombel $rombel)
    {
        $this->authorizeSchoolScope($request, $rombel->school_id);

        // Soft-guard: rombel yang masih punya enrollment aktif tidak boleh
        // dihapus langsung — mengikuti prinsip "jangan pernah hilangkan
        // jejak data siswa" yang konsisten dipakai di seluruh sistem ini
        // (lihat: graduated student is not deleted, nullOnDelete di FK).
        abort_if(
            $rombel->assignments()->whereNull('ended_at')->exists(),
            409,
            'Rombel masih punya assignment wali kelas aktif — nonaktifkan assignment-nya dulu.'
        );

        $rombel->delete();

        return $this->success(null, 'Rombel berhasil dihapus.');
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

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->can('teacher.manage'), 403);

        $schoolId = (int) $request->query('school_id');
        abort_if(
            ! $request->user()->isSuperAdmin() && $request->user()->school_id !== $schoolId,
            403,
            'Anda tidak memiliki akses ke sekolah ini.'
        );
    }

    private function authorizeSchoolScope(Request $request, int $schoolId): void
    {
        abort_unless($request->user()->can('teacher.manage'), 403);
        abort_if(
            ! $request->user()->isSuperAdmin() && $request->user()->school_id !== $schoolId,
            403,
            'Anda tidak memiliki akses ke sekolah ini.'
        );
    }
}