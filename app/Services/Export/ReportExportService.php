<?php

namespace App\Services\Export;

use App\Exports\ReportArrayExport;
use App\Models\ReportExport;
use App\Models\Rombel;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Report\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * MASTER-011 — generate file Excel/PDF dari data yang SUDAH ADA di
 * ReportService (BE-012, Anggota C) — TIDAK menghitung ulang angka
 * apapun sendiri, supaya angka di file export selalu sama persis
 * dengan yang ditampilkan di dashboard/API (satu sumber kebenaran).
 *
 * File disimpan di disk PRIVATE — lokal saat dev, Supabase Storage saat
 * production (lihat config('filesystems.export_disk')) — konsisten dengan
 * prinsip SEC-011 di ReportExportController ("file tidak pernah di disk
 * public"). Download TETAP lewat alur SEC-011 yang sudah ada (generateLink
 * + download route+policy) — service ini TIDAK bikin cara download baru.
 */
class ReportExportService
{
    private const EXPIRES_IN_HOURS = 24;

    public function __construct(private readonly ReportService $reportService)
    {
    }

    /**
     * Bikin instance ReportExport TRANSIENT (belum disimpan) untuk
     * dipakai cek otorisasi ($this->authorize('download', ...)) SEBELUM
     * file benar-benar di-generate — reuse ReportExportPolicy::download()
     * apa adanya, tanpa perlu ubah/duplikasi logic Policy Anggota A.
     */
    public function makeTransientForAuthCheck(string $scopeType, int $scopeId): ReportExport
    {
        return new ReportExport([
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'expires_at' => now()->addMinute(), // asal belum lewat, cukup untuk lolos isExpired()
        ]);
    }

    public function generate(
        string $scopeType,
        int $scopeId,
        string $format,
        Carbon $startDate,
        Carbon $endDate,
        User $requestedBy,
    ): ReportExport {
        [$rows, $headings, $title] = $this->buildDataset($scopeType, $scopeId, $startDate, $endDate);

        $filename = sprintf(
            '%s_%d_%s_%s.%s',
            $scopeType,
            $scopeId,
            now()->format('Ymd-His'),
            \Illuminate\Support\Str::random(6),
            $format === 'pdf' ? 'pdf' : 'xlsx',
        );

        $path = "reports/{$scopeType}/{$filename}";
        $disk = config('filesystems.export_disk');

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('pdf.report-export', [
                'title' => $title,
                'period' => ['start' => $startDate->toDateString(), 'end' => $endDate->toDateString()],
                'headings' => $headings,
                'rows' => $rows,
            ]);

            Storage::disk($disk)->put($path, $pdf->output());
        } else {
            Excel::store(new ReportArrayExport($rows, $headings), $path, $disk);
        }

        return ReportExport::create([
            'requested_by' => $requestedBy->id,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'file_path' => $path,
            'format' => $format,
            'expires_at' => now()->addHours(self::EXPIRES_IN_HOURS),
        ]);
    }

    /**
     * @return array{0: array, 1: array, 2: string} [rows, headings, title]
     */
    private function buildDataset(string $scopeType, int $scopeId, Carbon $startDate, Carbon $endDate): array
    {
        return match ($scopeType) {
            'student' => $this->studentDataset($scopeId, $startDate, $endDate),
            'rombel' => $this->rombelDataset($scopeId, $startDate, $endDate),
            'school' => $this->schoolDataset($scopeId, $startDate, $endDate),
            default => throw new \InvalidArgumentException("scope_type tidak didukung: {$scopeType}"),
        };
    }

    private function studentDataset(int $studentProfileId, Carbon $start, Carbon $end): array
    {
        $profile = StudentProfile::findOrFail($studentProfileId);
        $report = $this->reportService->getStudentReport($profile, $start, $end);

        $headings = ['Nama', 'Total Poin', 'Total EXP', 'Level', 'Hari Terisi'];
        $rows = [[
            $report['full_name'], $report['total_points'], $report['total_exp'],
            $report['level'], $report['submitted_days'],
        ]];

        return [$rows, $headings, 'Laporan Siswa: '.$report['full_name']];
    }

    private function rombelDataset(int $rombelId, Carbon $start, Carbon $end): array
    {
        $rombel = Rombel::findOrFail($rombelId);
        $report = $this->reportService->getRombelReport($rombelId, $start, $end);

        $headings = ['Nama', 'Total Poin', 'Total EXP', 'Level', 'Hari Terisi'];
        $rows = array_map(
            fn (array $s) => [$s['full_name'], $s['total_points'], $s['total_exp'], $s['level'], $s['submitted_days']],
            $report['students'],
        );

        return [$rows, $headings, 'Laporan Rombel: '.$rombel->name];
    }

    private function schoolDataset(int $schoolId, Carbon $start, Carbon $end): array
    {
        $school = School::findOrFail($schoolId);
        $report = $this->reportService->getSchoolReport($schoolId);

        $headings = ['Rombel', 'Jumlah Siswa', 'Rata-rata Poin'];
        $rows = array_map(
            fn (array $r) => [$r['rombel_name'] ?? $r['rombel_id'], $r['student_count'] ?? '-', $r['avg_points'] ?? '-'],
            $report['rombel_achievements'],
        );

        return [$rows, $headings, 'Laporan Sekolah: '.$school->name];
    }
}