<?php

namespace App\Services\Gamification;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\StudentAward;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CertificateGenerationService
{
    public function generateForAward(StudentAward $studentAward): ?Certificate
    {
        $award = $studentAward->award;

        if (! $award || ! $award->generates_certificate) {
            return null;
        }

        // ASUMSI: 1 template default aktif dipakai untuk semua certificate.
        // Belum ada relasi Award -> CertificateTemplate spesifik di skema
        // yang ada — kalau nanti perlu per-award template, tinggal tambah
        // kolom template_id di awards, tidak mengubah service ini.
        $template = CertificateTemplate::where('active', true)->first();

        if (! $template) {
            return null; // tidak ada template, tidak bisa generate — bukan error fatal
        }

        $studentAward->loadMissing('studentProfile');

        $pdf = Pdf::loadView('pdf.certificate', [
            'studentName' => $studentAward->studentProfile->full_name,
            'awardName' => $award->name,
            'issuedAt' => now()->translatedFormat('d F Y'),
            'template' => $template,
        ]);

        $filename = 'certificates/'.Str::uuid().'.pdf';
        Storage::disk(config('filesystems.export_disk'))->put($filename, $pdf->output());

        return Certificate::create([
            'student_profile_id' => $studentAward->student_profile_id,
            'award_id' => $award->id,
            'template_id' => $template->id,
            'file_path' => $filename,
            'issued_at' => now(),
        ]);
    }
}