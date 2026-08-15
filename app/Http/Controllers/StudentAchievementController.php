<?php

namespace App\Http\Controllers;

use App\Models\StudentAward;
use App\Models\StudentBadge;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * SEC-007 — scope tugas ini: authorization untuk MELIHAT badge/award milik
 * siswa, bukan alur EVALUASI/pemberian badge/award (itu BE-008, Anggota C,
 * hari H9 juga tapi task terpisah — evaluator streak/badge/award).
 * Controller ini minimal, cukup untuk membuktikan StudentAchievementPolicy
 * tegak di level API (acceptance: "student only self achievements").
 */
class StudentAchievementController extends Controller
{
    use ApiResponse;

    public function showBadge(Request $request, StudentBadge $studentBadge)
    {
        $this->authorize('viewBadge', $studentBadge);

        return $this->success($studentBadge->load('badge'));
    }

    public function showAward(Request $request, StudentAward $studentAward)
    {
        $this->authorize('viewAward', $studentAward);

        return $this->success($studentAward->load('award'));
    }
}