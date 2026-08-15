<?php

namespace App\Http\Controllers;

use App\Models\Award;
use App\Models\StudentAward;
use App\Models\StudentProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'code' => 'required|string|unique:awards,code',
            'name' => 'required|string',
            'description' => 'nullable|string',
            'generates_certificate' => 'nullable|boolean',
            'active' => 'nullable|boolean',
        ]);

        $award = Award::create($validated);

        return response()->json(['status' => 'success', 'data' => $award], 201);
    }

    // POST /api/students/{studentProfile}/awards
    // Pemberian MANUAL award ke siswa oleh guru/kepsek/admin.
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
