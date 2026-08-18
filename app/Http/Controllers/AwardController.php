<?php

namespace App\Http\Controllers;

use App\Models\Award;
use App\Models\StudentAward;
use App\Models\StudentProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FIX (MASTER-007): disesuaikan dengan skema Award terbaru (Anggota A,
 * SEC-007) — sekarang punya `school_id` (nullable = global/Super Admin,
 * diisi = milik sekolah tertentu) dan `criteria` (JSON, syarat berbasis
 * kebiasaan/periode — BUKAN Poin/Level, sesuai requirement eksplisit
 * MASTER-007).
 *
 * Ditambahkan juga update()/destroy() yang kemarin belum ada.
 */
class AwardController extends Controller
{
    // GET /api/awards
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Award::class);

        return response()->json(['status' => 'success', 'data' => Award::all()]);
    }

    // POST /api/awards
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Award::class);

        $validated = $request->validate([
            'school_id' => 'nullable|integer|exists:schools,id',
            'code' => 'required|string|unique:awards,code',
            'name' => 'required|string',
            'description' => 'nullable|string',
            // criteria berbasis kebiasaan/periode, BUKAN poin/level —
            // divalidasi generic (array bebas), enforcement "bukan
            // poin/level" ada di lapisan dokumentasi/review, karena
            // sifatnya definisi terbuka (configurable) sesuai requirement.
            'criteria' => 'nullable|array',
            'generates_certificate' => 'nullable|boolean',
            'active' => 'nullable|boolean',
        ]);

        $award = Award::create($validated);

        return response()->json(['status' => 'success', 'data' => $award], 201);
    }

    // PUT /api/awards/{award}
    public function update(Request $request, Award $award): JsonResponse
    {
        $this->authorize('update', $award);

        $validated = $request->validate([
            'school_id' => 'nullable|integer|exists:schools,id',
            'code' => 'sometimes|required|string|unique:awards,code,'.$award->id,
            'name' => 'sometimes|required|string',
            'description' => 'nullable|string',
            'criteria' => 'nullable|array',
            'generates_certificate' => 'nullable|boolean',
            'active' => 'nullable|boolean',
        ]);

        $award->update($validated);

        return response()->json(['status' => 'success', 'data' => $award]);
    }

    // DELETE /api/awards/{award}
    public function destroy(Award $award): JsonResponse
    {
        $this->authorize('delete', $award);

        $award->delete();

        return response()->json(['status' => 'success', 'message' => 'Award berhasil dihapus']);
    }

    // POST /api/students/{studentProfile}/awards
    public function give(Request $request, StudentProfile $studentProfile): JsonResponse
    {
        $this->authorize('give', Award::class);

        $validated = $request->validate([
            'award_id' => 'required|integer|exists:awards,id',
            'note' => 'nullable|string',
        ]);

        $studentAward = StudentAward::create([
            'student_profile_id' => $studentProfile->id,
            'award_id' => $validated['award_id'],
            'given_by' => $request->user()->id,
            'note' => $validated['note'] ?? null,
            'given_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Penghargaan berhasil diberikan',
            'data' => $studentAward->load('award'),
        ], 201);
    }

    // GET /api/students/{studentProfile}/awards
    public function studentAwards(StudentProfile $studentProfile): JsonResponse
    {
        $user = request()->user();

        if ($user->isSiswa() && $user->studentProfile?->id !== $studentProfile->id) {
            abort(403, 'Kamu hanya bisa melihat penghargaan milikmu sendiri.');
        }

        $awards = StudentAward::with(['award', 'givenBy'])
            ->where('student_profile_id', $studentProfile->id)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'student_id' => $studentProfile->id,
                'total_awards' => $awards->count(),
                'awards' => $awards,
            ],
        ]);
    }
}
