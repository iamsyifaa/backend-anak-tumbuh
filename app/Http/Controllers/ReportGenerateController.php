<?php

namespace App\Http\Controllers;

use App\Models\ReportExport;
use App\Services\Export\ReportExportService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * MASTER-011 — endpoint untuk MEMICU pembuatan file export. TIDAK
 * membuat endpoint download baru — setelah export ini selesai, client
 * pakai alur SEC-011 yang sudah ada:
 *   1. POST /report-exports (endpoint ini) → dapat report_export_id
 *   2. POST /report-exports/{id}/link → dapat signed URL
 *   3. GET  url itu → file ke-download
 */
class ReportGenerateController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ReportExportService $exportService)
    {
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'scope_type' => ['required', 'string', 'in:student,rombel,school'],
            'scope_id' => ['required', 'integer'],
            'format' => ['required', 'string', 'in:excel,pdf'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        // Reuse ReportExportPolicy::download() apa adanya lewat instance
        // transient — supaya aturan scope siapa-boleh-akses-apa CUMA ada
        // di 1 tempat (Policy Anggota A), tidak diduplikasi di sini.
        $transient = $this->exportService->makeTransientForAuthCheck(
            $validated['scope_type'],
            $validated['scope_id'],
        );
        $this->authorize('download', $transient);

        $start = isset($validated['start_date']) ? Carbon::parse($validated['start_date']) : now()->subDays(29);
        $end = isset($validated['end_date']) ? Carbon::parse($validated['end_date']) : now();

        $export = $this->exportService->generate(
            $validated['scope_type'],
            $validated['scope_id'],
            $validated['format'],
            $start,
            $end,
            $request->user(),
        );

        return $this->success([
            'report_export_id' => $export->id,
            'format' => $export->format,
            'expires_at' => $export->expires_at,
        ], 'Export berhasil dibuat. Gunakan report_export_id untuk minta link unduhan.', 201);
    }
}
