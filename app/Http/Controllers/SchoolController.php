<?php

namespace App\Http\Controllers;

use App\Http\Requests\School\SchoolRequest;
use App\Models\School;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class SchoolController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/schools
     * Otorisasi permission via SchoolPolicy::viewAny (school.view).
     * Scope query (Kepala Sekolah hanya lihat sekolahnya sendiri) tetap di sini
     * karena itu urusan query-building, bukan yes/no authorization.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', School::class);

        $user = $request->user();
        $query = School::query();

        if (! $user->isSuperAdmin()) {
            $query->where('id', $user->school_id);
        }

        return $this->success($query->orderBy('name')->paginate(20));
    }

    /**
     * POST /api/schools — SchoolPolicy::create (hanya Super Admin).
     */
    public function store(SchoolRequest $request)
    {
        $this->authorize('create', School::class);

        $school = School::create($request->validated());

        return $this->success($school, 'Sekolah berhasil dibuat.', 201);
    }

    public function show(Request $request, School $school)
    {
        $this->authorize('view', $school);

        return $this->success($school->load('academicYears'));
    }

    /**
     * PUT/PATCH /api/schools/{school}
     * SchoolPolicy::update mengecek permission + scope. Field mana yang boleh
     * diubah oleh non-Super-Admin tetap keputusan controller (field-level, bukan
     * model-level authorization).
     */
    public function update(SchoolRequest $request, School $school)
    {
        $this->authorize('update', $school);

        $data = $request->validated();

        if (! $request->user()->isSuperAdmin()) {
            unset($data['code'], $data['status']);
        }

        $school->update($data);

        return $this->success($school, 'Sekolah berhasil diperbarui.');
    }

    /**
     * DELETE /api/schools/{school} — SchoolPolicy::delete (hanya Super Admin).
     * Nonaktifkan (soft status), bukan hard delete — banyak tabel bergantung school_id.
     */
    public function destroy(Request $request, School $school)
    {
        $this->authorize('delete', $school);

        $school->update(['status' => 'inactive']);

        return $this->success(null, 'Sekolah berhasil dinonaktifkan.');
    }
}