<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Services\Reporting\SchoolReportExportService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * MASTER-011 — endpoint tipis untuk generate laporan Excel/PDF sekolah.
 * Semua logic pengumpulan data & pembuatan file ada di
 * SchoolReportExportService — controller ini cuma validasi request,
 * cek otorisasi, dan panggil service.
 *
 * Otorisasi pakai Gate 'principal.dashboard.view' yang sama dengan
 * SEC-010/MASTER-010 (PrincipalDashboardController) — konsisten dengan
 * prinsip "satu gate scope sekolah untuk semua fitur read Kepala Sekolah".
 */
class SchoolReportExportController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly SchoolReportExportService $service)
    {
    }

    /**
     * POST /api/schools/{school}/reports/export
     * Body: { "format": "xlsx"|"pdf", "days"?: int }
     */
    public function store(Request $request, School $school)
    {
        $this->authorize('principal.dashboard.view', $school);

        $validated = $request->validate([
            'format' => ['required', 'string', 'in:xlsx,pdf'],
            'days' => ['sometimes', 'integer', 'min:1', 'max:365'],
        ]);

        $reportExport = $this->service->generate(
            $school,
            $request->user(),
            $validated['format'],
            $validated['days'] ?? 30,
        );

        return $this->success([
            'report_export_id' => $reportExport->id,
            'format' => $reportExport->format,
            'expires_at' => $reportExport->expires_at,
        ], 201);
    }
}