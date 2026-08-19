<?php

namespace App\Http\Controllers;

use App\Models\ReportExport;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * SEC-011 — scope tugas ini: authorization + proteksi file download, BUKAN
 * pembuatan export Excel/PDF (itu MASTER-011, Anggota B — dia diharapkan
 * membuat baris `report_exports` setelah generate file, lalu memakai alur
 * download yang SAMA di controller ini, bukan bikin endpoint download sendiri).
 *
 * Prinsip kunci "files protected":
 * 1. File TIDAK PERNAH disimpan di disk 'public' — selalu disk private
 *    (lokal saat dev, Supabase Storage saat production — lihat
 *    config('filesystems.export_disk')), supaya tidak ada URL publik
 *    yang bisa ditebak/diakses langsung.
 * 2. Link download HARUS signed URL (Laravel URL::temporarySignedRoute) —
 *    kadaluwarsa otomatis, dan signature gagal kalau parameter diutak-atik.
 * 3. Signed URL SENDIRI TIDAK CUKUP — tetap dicek Policy scope, supaya
 *    signature yang bocor/diteruskan ke orang lain tidak otomatis berhasil
 *    kalau orang itu login sebagai user di luar scope.
 */
class ReportExportController extends Controller
{
    use ApiResponse;

    /**
     * POST /api/report-exports/{reportExport}/link
     * Menghasilkan signed URL sementara (bukan file langsung) — dipanggil
     * setelah user login & lolos Policy, link ini yang dibagikan ke frontend.
     */
    public function generateLink(Request $request, ReportExport $reportExport)
    {
        $this->authorize('download', $reportExport);

        $url = URL::temporarySignedRoute(
            'report-exports.download',
            now()->addMinutes(5), // jauh lebih pendek dari expires_at record — link sekali pakai/sesaat.
            ['reportExport' => $reportExport->id]
        );

        return $this->success(['url' => $url], 'Link unduhan dibuat, berlaku 5 menit.');
    }

    /**
     * GET /api/report-exports/{reportExport}/download — route bernama
     * 'report-exports.download', dilindungi middleware 'signed' bawaan
     * Laravel (menolak kalau signature invalid/kadaluwarsa) DAN tetap
     * dicek Policy (defense in depth, sama prinsip dengan SEC-010).
     */
    public function download(Request $request, ReportExport $reportExport)
    {
        $this->authorize('download', $reportExport);

        abort_if($reportExport->isExpired(), 410, 'Link unduhan sudah kedaluwarsa.');

        $disk = Storage::disk(config('filesystems.export_disk'));

        abort_unless($disk->exists($reportExport->file_path), 404, 'File tidak ditemukan.');

        return $disk->download($reportExport->file_path);
    }
}