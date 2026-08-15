<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\StudentProfile;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * SEC-008 — "student/me" pattern: TIDAK PERNAH menerima student ID dari
 * request (bukan route param, bukan query, bukan body). Identitas diambil
 * SEMATA-MATA dari $request->user()->studentProfile — jadi endpoint ini
 * IDOR-immune BY DESIGN, tidak bergantung pada Policy check yang bisa lupa
 * dipasang. Ini pola yang disarankan MASTER-008 (Anggota B) pakai untuk
 * "GET student/me" — bandingkan dengan showCertificate() di bawah yang
 * MEMANG terima {id} dan karena itu WAJIB authorize() eksplisit.
 */
class StudentSelfController extends Controller
{
    use ApiResponse;

    public function me(Request $request)
    {
        $profile = $request->user()->studentProfile;

        abort_if($profile === null, 404, 'Profil siswa tidak ditemukan untuk akun ini.');

        return $this->success($profile);
    }

    /**
     * GET /api/certificates/{certificate} — endpoint yang MENERIMA ID
     * eksplisit, jadi WAJIB authorize(). Ini contoh pola yang harus diikuti
     * MASTER-008 untuk endpoint mana pun yang menerima ID di URL.
     */
    public function showCertificate(Request $request, Certificate $certificate)
    {
        $this->authorize('view', $certificate);

        return $this->success($certificate);
    }

    /**
     * GET /api/students/{studentProfile} — sengaja disediakan sebagai contoh
     * endpoint "by ID" yang HARUS ditolak untuk siswa lain, dipakai untuk
     * regression test IDOR. MASTER-008 kemungkinan tidak akan pernah
     * mengekspos endpoint ini ke siswa (mereka pakai /student/me), tapi
     * kalau staff butuh lihat profil siswa tertentu, endpoint ini yang dipakai.
     */
    public function showProfile(Request $request, StudentProfile $studentProfile)
    {
        $this->authorize('view', $studentProfile);

        return $this->success($studentProfile);
    }
}