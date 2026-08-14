<?php

namespace App\Http\Controllers;

use App\Models\StudentProfile;
use App\Models\StudentTrophy;
use App\Models\Trophy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrophyController extends Controller
{
    // GET /api/trophies - Daftar Seluruh Master Piala
    public function index(): JsonResponse
    {
        $trophies = Trophy::all();

        return response()->json([
            'status' => 'success',
            'data' => $trophies,
        ]);
    }

    // GET /api/students/{studentProfile}/trophies - Daftar Piala Milik Siswa
    public function studentTrophies(StudentProfile $studentProfile): JsonResponse
    {
        $studentTrophies = StudentTrophy::with('trophy')
            ->where('student_profile_id', $studentProfile->id)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'student_id' => $studentProfile->id,
                'total_trophies' => $studentTrophies->count(),
                'trophies' => $studentTrophies,
            ],
        ]);
    }
}