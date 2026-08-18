<?php

namespace App\Services\Reporting;

use App\Exports\SchoolReportExport;
use App\Models\ReportExport;
use App\Models\School;
use App\Models\User;
use App\Services\Analytics\SchoolAnalyticsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * MASTER-011 — generate file Excel/PDF laporan sekolah, lalu insert row
 * report_exports. Alur link & download SUDAH ADA di ReportExportController
 * (Anggota A / SEC-011) — service ini TIDAK bikin endpoint download sendiri.
 */
class SchoolReportExportService
{
    public function __construct(private SchoolAnalyticsService $analyticsService) {}

    public function generate(School $school, User $requester, string $format, int $days = 30): ReportExport
    {
        $data = $this->collectData($school, $days);

        $disk = Storage::disk('local');
        $directory = "report-exports/school/{$school->id}";
        $filename = Str::uuid().'.'.$format;
        $relativePath = "{$directory}/{$filename}";

        if ($format === 'xlsx') {
            Excel::store(new SchoolReportExport($data), $relativePath, 'local');
        } else { // pdf
            $pdf = Pdf::loadView('reports.school-summary-pdf', [
                'school' => $school,
                'data' => $data,
            ]);
            $disk->put($relativePath, $pdf->output());
        }

        // File sudah selesai dibuat SEBELUM insert row — sesuai model
        // ReportExport yang tidak punya kolom status (tidak ada state
        // "processing"). TTL 24 jam, konsisten dengan generateLink yang
        // jauh lebih pendek (5 menit) untuk signed URL-nya.
        return ReportExport::create([
            'requested_by' => $requester->id,
            'scope_type' => 'school',
            'scope_id' => $school->id,
            'type' => 'school_overview',
            'file_path' => $relativePath,
            'format' => $format,
            'expires_at' => now()->addDay(),
        ]);
    }

    private function collectData(School $school, int $days): array
    {
        return [
            'summary' => [
                'average_points' => $this->analyticsService->getSchoolAveragePoints($school->id),
                'today_participation_rate' => $this->analyticsService->getTodayParticipationRate($school->id),
            ],
            'rombels' => $this->analyticsService->getRombelAchievements($school->id),
            'trend' => $this->analyticsService->getSchoolTrend($school->id, $days),
        ];
    }
}
