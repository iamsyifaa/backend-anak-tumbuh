<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSchoolReportExportRequest;
use App\Models\School;
use App\Services\Reporting\SchoolReportExportService;
use App\Support\ApiResponse;

class SchoolReportExportController extends Controller
{
    use ApiResponse;

    public function __construct(private SchoolReportExportService $service) {}

    public function store(StoreSchoolReportExportRequest $request, School $school)
    {
        // NOTE: samakan nama policy method ini dengan yang dipakai
        // PrincipalDashboardController (kemungkinan 'view' pada SchoolPolicy)
        // supaya konsisten dengan otorisasi dashboard yang sudah ada.
        $this->authorize('view', $school);

        $reportExport = $this->service->generate(
            $school,
            $request->user(),
            $request->validated('format'),
            $request->validated('days', 30)
        );

        return $this->success(
            ['report_export_id' => $reportExport->id],
            'Export berhasil dibuat. Gunakan report-exports/{id}/link untuk mendapatkan link unduhan.'
        );
    }
}
