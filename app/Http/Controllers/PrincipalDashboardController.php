<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * SEC-010 — scope tugas ini: authorization + read-only enforcement untuk
 * permukaan dashboard, BUKAN statistik/analytics lengkap (itu MASTER-010,
 * Anggota B, dependency eksplisit ke SEC-010 ini — jadi dia HARUS
 * pakai Gate 'principal.dashboard.view' + middleware 'read-only' ini,
 * bukan bikin authorization sendiri).
 */
class PrincipalDashboardController extends Controller
{
    use ApiResponse;

    public function overview(Request $request, School $school)
    {
        $this->authorize('principal.dashboard.view', $school);

        return $this->success([
            'school_id' => $school->id,
            'school_name' => $school->name,
            'note' => 'Placeholder — statistik lengkap dibangun di MASTER-010.',
        ]);
    }
}