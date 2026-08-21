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
            'criteria' => ['nullable', 'array', $this->criteriaNotPointOrLevelBased()],
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
            'criteria' => ['nullable', 'array', $this->criteriaNotPointOrLevelBased()],
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

    /**
     * FIX (GAP-3 audit): Award tidak boleh berbasis Poin/Level (requirement
     * eksplisit MASTER-007). Sebelumnya 'criteria' cuma divalidasi
     * 'nullable|array' generic, jadi tidak ada yang menegakkan aturan ini
     * secara teknis. Rule ini menolak eksplisit kalau ada key yang
     * menyerempet poin/level/exp, dicek rekursif untuk jaga-jaga kalau
     * criteria punya struktur nested.
     */
    private function criteriaNotPointOrLevelBased(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $forbidden = ['point', 'poin', 'points', 'level', 'exp', 'score'];

            $containsForbiddenKey = function (array $criteria) use (&$containsForbiddenKey, $forbidden): bool {
                foreach ($criteria as $key => $val) {
                    if (is_string($key)) {
                        foreach ($forbidden as $term) {
                            if (str_contains(strtolower($key), $term)) {
                                return true;
                            }
                        }
                    }

                    if (is_array($val) && $containsForbiddenKey($val)) {
                        return true;
                    }
                }

                return false;
            };

            if (is_array($value) && $containsForbiddenKey($value)) {
                $fail('Kriteria Award tidak boleh berbasis Poin, Level, atau EXP sesuai requirement.');
            }
        };
    }
}
