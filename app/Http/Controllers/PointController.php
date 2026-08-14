<?php

namespace App\Http\Controllers;

use App\Models\PointTransaction;
use App\Models\StudentProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PointController extends Controller
{
    /**
     * GET /api/students/{studentProfile}/points
     * Ringkasan & Riwayat Poin Siswa (MASTER-005)
     */
    public function studentPoints(StudentProfile $studentProfile): JsonResponse
    {
        $transactions = PointTransaction::where('student_profile_id', $studentProfile->id)
            ->latest()
            ->paginate(15);

        $totalPoints = PointTransaction::where('student_profile_id', $studentProfile->id)
            ->sum('amount');

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