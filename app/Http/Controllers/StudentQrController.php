<?php

namespace App\Http\Controllers;

use App\Models\StudentProfile;
use App\Services\QrCredentialService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class StudentQrController extends Controller
{
    public function __construct(private QrCredentialService $service) {}

    /**
     * POST /api/students/{studentProfile}/qr/generate
     * Generate 1 QR untuk 1 siswa. Token lama (kalau ada) otomatis revoke.
     */
    public function generate(StudentProfile $studentProfile): JsonResponse
    {
        $token = $this->service->generateForStudent($studentProfile);

        return response()->json([
            'student_profile_id' => $studentProfile->id,
            'full_name' => $studentProfile->full_name,
            // token plain-text HANYA muncul sekali di response ini,
            // setelahnya tidak bisa diambil lagi (sifat Sanctum).
            'qr_token' => $token,
        ]);
    }

    /**
     * POST /api/students/qr/generate-bulk
     * Body: { "student_profile_ids": [1, 2, 3] }
     */
    public function generateBulk(Request $request): JsonResponse
    {
        $request->validate([
            'student_profile_ids' => ['required', 'array', 'min:1'],
            'student_profile_ids.*' => ['integer', 'exists:student_profiles,id'],
        ]);

        $profiles = StudentProfile::whereIn('id', $request->input('student_profile_ids'))->get();
        $result = $this->service->generateBulk($profiles);

        return response()->json(['generated' => array_values($result)]);
    }

    /**
     * DELETE /api/students/{studentProfile}/qr
     * Revoke QR aktif siswa (misal kartu hilang/dicuri).
     */
    public function revoke(StudentProfile $studentProfile): JsonResponse
    {
        $this->service->revokeForStudent($studentProfile);

        return response()->json(['message' => 'QR credential berhasil di-revoke.']);
    }

    /**
     * POST /api/students/qr/export-pdf
     * Body: { "student_profile_ids": [1, 2, 3] }
     *
     * ⚠️ PENTING: PDF ini menampilkan token PLAIN-TEXT di dalam QR image.
     * Sekali di-generate, token TIDAK BISA dimunculkan ulang lewat endpoint
     * manapun (Sanctum cuma simpan hash-nya). Kalau PDF ini hilang, satu-satunya
     * cara pulihkan akses siswa adalah generate ulang (otomatis revoke yang lama).
     */
    public function exportPdf(Request $request)
    {
        $request->validate([
            'student_profile_ids' => ['required', 'array', 'min:1'],
            'student_profile_ids.*' => ['integer', 'exists:student_profiles,id'],
        ]);

        $profiles = StudentProfile::whereIn('id', $request->input('student_profile_ids'))->get();
        $generated = $this->service->generateBulk($profiles);

        $cards = collect($generated)->map(function (array $row) {
            return [
                'full_name' => $row['full_name'],
                'nisn' => $row['nisn'],
                'qr_svg' => QrCode::size(220)->generate($row['token']),
            ];
        });

        $pdf = Pdf::loadView('pdf.student-qr-cards', ['cards' => $cards]);

        return $pdf->download('kartu-qr-siswa-'.now()->format('Ymd-His').'.pdf');
    }
}
