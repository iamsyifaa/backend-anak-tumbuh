<?php

namespace App\Http\Controllers;

use App\Models\ActivitySubmission;
use App\Models\StudentAward;
use App\Models\StudentBadge;
use App\Models\StudentProfile;
use App\Services\TeacherAssignmentService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * MASTER-009 — Teacher API (monitoring, READ ONLY).
 *
 * Scope enforcement SAMA PERSIS pola TeacherPolicy (SEC-009, Anggota A):
 * satu-satunya sumber kebenaran adalah TeacherAssignmentService::getActiveRombelId().
 * Tidak ada jalur scope lain (tidak cek "mengajar mapel di rombel ini" dst).
 *
 * ⚠️ SENGAJA TIDAK ADA endpoint input/rekap manual apapun (Input Cepat
 * Rekap Manual, Isi Massal, Salin Hari Sebelumnya, Import Rekap Buku) —
 * ini dilarang eksplisit di requirement. Siswa Manual tetap terlihat di
 * daftar (dengan status method='manual'), tapi TIDAK ADA cara guru
 * menginput/mengubah data aktivitas siswa lewat controller ini atau
 * manapun di sistem.
 */
class TeacherController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TeacherAssignmentService $assignmentService)
    {
    }

    /**
     * Ambil rombel_id aktif guru yang login, 404 kalau belum ada
     * assignment (bukan 403 — belum ada rombel ≠ tidak boleh akses).
     */
    private function activeRombelId(Request $request): int
    {
        abort_unless($request->user()->isWaliKelas(), 404);

        $rombelId = $this->assignmentService->getActiveRombelId($request->user());

        abort_if($rombelId === null, 404, 'Anda belum ditugaskan ke rombel manapun.');

        return $rombelId;
    }

    /**
     * Pastikan siswa yang diminta benar-benar berada di rombel aktif
     * (enrollment status active) guru yang login. 404 kalau bukan
     * (anti-IDOR: tidak bocor info "siswa ini ada tapi bukan milikmu").
     */
    private function studentInOwnRombel(Request $request, int $studentProfileId): StudentProfile
    {
        $rombelId = $this->activeRombelId($request);

        $profile = StudentProfile::whereHas('enrollments', function ($q) use ($rombelId) {
            $q->where('rombel_id', $rombelId)->where('status', 'active');
        })->find($studentProfileId);

        abort_if($profile === null, 404, 'Siswa tidak ditemukan di rombel Anda.');

        return $profile;
    }

    // GET /api/teacher/rombel/students
    public function students(Request $request)
    {
        $rombelId = $this->activeRombelId($request);

        $query = StudentProfile::whereHas('enrollments', function ($q) use ($rombelId) {
            $q->where('rombel_id', $rombelId)->where('status', 'active');
        });

        if ($request->filled('method')) {
            $query->where('method', $request->string('method'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $students = $query->orderBy('full_name')->paginate(20);

        return $this->success($students);
    }

    // GET /api/teacher/rombel/students/{studentProfile}
    public function studentDetail(Request $request, int $studentProfile)
    {
        $profile = $this->studentInOwnRombel($request, $studentProfile);

        return $this->success($profile);
    }

    // GET /api/teacher/rombel/students/{studentProfile}/activity
    public function studentActivity(Request $request, int $studentProfile)
    {
        $profile = $this->studentInOwnRombel($request, $studentProfile);

        $activity = ActivitySubmission::where('student_profile_id', $profile->id)
            ->with('answers.indicator', 'answers.option')
            ->orderByDesc('activity_date')
            ->paginate(15);

        return $this->success($activity);
    }

    /**
     * GET /api/teacher/rombel/students/{studentProfile}/progress
     * Rasio hari terisi vs hari sejak enrollment aktif dimulai —
     * indikator konsistensi pengisian, BUKAN nilai/skor.
     */
    public function studentProgress(Request $request, int $studentProfile)
    {
        $profile = $this->studentInOwnRombel($request, $studentProfile);
        $enrollment = $profile->currentEnrollment()->first();

        $daysSinceStart = $enrollment
            ? now()->diffInDays($enrollment->started_at) + 1
            : 0;

        $filledDays = ActivitySubmission::where('student_profile_id', $profile->id)
            ->distinct('activity_date')
            ->count('activity_date');

        return $this->success([
            'days_since_enrolled' => $daysSinceStart,
            'days_filled' => $filledDays,
            'fill_rate' => $daysSinceStart > 0 ? round($filledDays / $daysSinceStart, 2) : 0,
        ]);
    }

    // GET /api/teacher/rombel/students/{studentProfile}/achievements
    public function studentAchievements(Request $request, int $studentProfile)
    {
        $profile = $this->studentInOwnRombel($request, $studentProfile);

        $badges = StudentBadge::with('badge')->where('student_profile_id', $profile->id)->get();
        $awards = StudentAward::with('award')->where('student_profile_id', $profile->id)->get();

        return $this->success([
            'badges' => $badges,
            'awards' => $awards,
        ]);
    }

    /**
     * GET /api/teacher/rombel/export
     * Export CSV daftar siswa rombel + ringkasan progress — READ ONLY,
     * tidak ada mekanisme upload/import balik dari file ini.
     */
    public function export(Request $request): StreamedResponse
    {
        $rombelId = $this->activeRombelId($request);

        $students = StudentProfile::whereHas('enrollments', function ($q) use ($rombelId) {
            $q->where('rombel_id', $rombelId)->where('status', 'active');
        })->get();

        $filename = 'rombel-export-'.now()->format('Ymd-His').'.csv';

        $callback = function () use ($students) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['NISN', 'Nama', 'Method', 'Status']);

            foreach ($students as $student) {
                fputcsv($handle, [$student->nisn, $student->full_name, $student->method, $student->status]);
            }

            fclose($handle);
        };

        return response()->streamDownload($callback, $filename, ['Content-Type' => 'text/csv']);
    }
}
