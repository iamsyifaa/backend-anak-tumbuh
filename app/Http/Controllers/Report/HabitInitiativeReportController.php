<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\FilterHabitInitiativeReportRequest;
use App\Models\Rombel;
use App\Models\School;
use App\Services\Report\ReportService;
use App\Support\ApiResponse;
use Carbon\Carbon;

/**
 * Report Filter Kebiasaan & Inisiatif — Wali Kelas & Kepala Sekolah.
 *
 * Otorisasi TIDAK reimplement scope check sendiri — reuse policy yang
 * sudah ada:
 * - rombel_id -> TeacherPolicy::viewRombel (Rombel::class sudah terdaftar
 *   di AuthServiceProvider). Ini otomatis mencakup Wali Kelas rombel
 *   tsb DAN Kepala Sekolah sekolah tsb (lihat isi TeacherPolicy).
 * - school_id -> Gate 'principal.dashboard.view' (SEC-010), sama seperti
 *   PrincipalDashboardController.
 */
class HabitInitiativeReportController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ReportService $reportService)
    {
    }

    /**
     * GET /api/reports/habit-initiative
     */
    public function __invoke(FilterHabitInitiativeReportRequest $request)
    {
        if ($request->filled('rombel_id')) {
            $rombel = Rombel::findOrFail($request->integer('rombel_id'));
            $this->authorize('viewRombel', $rombel);
        } else {
            $school = School::findOrFail($request->integer('school_id'));
            $this->authorize('principal.dashboard.view', $school);
        }

        $result = $this->reportService->getHabitInitiativeReport(
            habitId: $request->integer('habit_id'),
            initiatives: $request->input('initiatives', []),
            rombelId: $request->filled('rombel_id') ? $request->integer('rombel_id') : null,
            schoolId: $request->filled('school_id') ? $request->integer('school_id') : null,
            startDate: Carbon::parse($request->string('start_date')),
            endDate: Carbon::parse($request->string('end_date')),
        );

        return $this->success($result);
    }
}