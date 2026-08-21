<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Meniru persis pola ReportExportController (SEC-011) — signed URL
 * sementara + Policy check ganda (signature saja tidak cukup).
 * File certificate disimpan di disk yang sama dengan report export
 * (config('filesystems.export_disk')), lihat CertificateGenerationService.
 */
class CertificateDownloadController extends Controller
{
    use ApiResponse;

    /**
     * POST /api/certificates/{certificate}/link
     */
    public function generateLink(Request $request, Certificate $certificate)
    {
        $this->authorize('download', $certificate);

        $url = URL::temporarySignedRoute(
            'certificates.download',
            now()->addMinutes(5),
            ['certificate' => $certificate->id]
        );

        return $this->success(['url' => $url], 'Link unduhan dibuat, berlaku 5 menit.');
    }

    /**
     * GET /api/certificates/{certificate}/download — route bernama
     * 'certificates.download', dilindungi middleware 'signed' bawaan
     * Laravel DAN tetap dicek Policy (defense in depth).
     */
    public function download(Request $request, Certificate $certificate)
    {
        $this->authorize('download', $certificate);

        $disk = Storage::disk(config('filesystems.export_disk'));
        abort_unless($disk->exists($certificate->file_path), 404, 'File sertifikat tidak ditemukan.');

        return $disk->download($certificate->file_path);
    }
}