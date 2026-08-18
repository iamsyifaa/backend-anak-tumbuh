<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use App\Models\StudentBadge;
use App\Models\StudentProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FIX (MASTER-007): tambah field `criteria` (JSON) yang sudah ada di
 * kolom database (lihat migration ALTER 2026_08_16_000001) tapi belum
 * bisa diisi lewat controller ini.
 */
class BadgeController extends Controller
{
    // GET /api/badges
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Badge::class);

        return response()->json(['status' => 'success', 'data' => Badge::all()]);
    }

    // POST /api/badges
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Badge::class);

        $validated = $request->validate([
            'code' => 'required|string|unique:badges,code',
            'name' => 'required|string',
            'description' => 'nullable|string',
            'target_type' => 'required|string|in:total_points,total_exp',
            'target_value' => 'required|integer|min:1',
            'criteria' => 'nullable|array',
            'active' => 'nullable|boolean',
        ]);

        $badge = Badge::create($validated);

        return response()->json(['status' => 'success', 'data' => $badge], 201);
    }

    // PUT /api/badges/{badge}
    public function update(Request $request, Badge $badge): JsonResponse
    {
        $this->authorize('update', Badge::class);

        $validated = $request->validate([
            'code' => 'sometimes|required|string|unique:badges,code,'.$badge->id,
            'name' => 'sometimes|required|string',
            'description' => 'nullable|string',
            'target_type' => 'sometimes|required|string|in:total_points,total_exp',
            'target_value' => 'sometimes|required|integer|min:1',
            'criteria' => 'nullable|array',
            'active' => 'nullable|boolean',
        ]);

        $badge->update($validated);

        return response()->json(['status' => 'success', 'data' => $badge]);
    }

    // DELETE /api/badges/{badge}
    public function destroy(Badge $badge): JsonResponse
    {
        $this->authorize('delete', Badge::class);

        $badge->delete();

        return response()->json(['status' => 'success', 'message' => 'Badge berhasil dihapus']);
    }

    // GET /api/students/{studentProfile}/badges
    public function studentBadges(StudentProfile $studentProfile): JsonResponse
    {
        $user = request()->user();

        if ($user->isSiswa() && $user->studentProfile?->id !== $studentProfile->id) {
            abort(403, 'Kamu hanya bisa melihat badge milikmu sendiri.');
        }

        $badges = StudentBadge::with('badge')
            ->where('student_profile_id', $studentProfile->id)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'student_id' => $studentProfile->id,
                'total_badges' => $badges->count(),
                'badges' => $badges,
            ],
        ]);
    }
}
