<?php

namespace App\Http\Controllers;

use App\Models\PointTransaction;
use App\Models\StudentProfile;
use Illuminate\Http\JsonResponse;

/**
 * FIX (review MASTER-005):
 * 1. Otorisasi ditambahkan — sebelumnya siapa saja yang login bisa lihat
 *    poin siswa manapun.
 * 2. Query disesuaikan ke struktur ASLI point_transactions yang pakai
 *    kolom `user_id` (App\Models\User), BUKAN `student_profile_id`.
 */
class PointController extends Controller
{
    // GET /api/students/{studentProfile}/points
    public function studentPoints(StudentProfile $studentProfile): JsonResponse
    {
        $user = request()->user();

        if ($user->isSiswa() && $user->studentProfile?->id !== $studentProfile->id) {
            abort(403, 'Kamu hanya bisa melihat poin milikmu sendiri.');
        }

        $targetUserId = $studentProfile->user_id;

        $transactions = PointTransaction::where('user_id', $targetUserId)
            ->latest('created_at')
            ->paginate(15);

        $totalPoints = PointTransaction::where('user_id', $targetUserId)->sum('amount');

        return response()->json([
            'status' => 'success',
            'data' => [
                'student_id' => $studentProfile->id,
                'total_points' => $totalPoints,
                'history' => $transactions,
            ],
        ]);
    }
}