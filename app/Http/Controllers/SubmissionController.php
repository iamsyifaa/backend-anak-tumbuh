<?php

namespace App\Http\Controllers;

use App\Http\Requests\Submission\SubmissionRequest;
use App\Models\ActivitySubmission;
use App\Services\SubmissionGuardService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * SEC-006 — scope tugas ini: ownership + lock + anti-backfill policy, BUKAN
 * alur submit lengkap (validasi jawaban 7 Kebiasaan, scoring Poin/EXP —
 * itu BE-005/BE-006/BE-007, Anggota C, dan MASTER-006, Anggota B, semua
 * paralel/menyusul di hari yang sama). Controller ini SENGAJA minimal:
 * cukup untuk membuktikan Policy tegak di level API sesuai acceptance
 * criteria SEC-006 ("duplicate ditolak, locked/closed ditolak, akses
 * siswa lain ditolak"). BE-007 kemungkinan akan MEMANGGIL Policy yang
 * sama ($this->authorize('create', ActivitySubmission::class)) dari
 * SubmitDailyActivityAction miliknya, bukan menulis ulang authorization.
 */
class SubmissionController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly SubmissionGuardService $guard) {}

    public function store(SubmissionRequest $request)
    {
        $this->authorize('create', ActivitySubmission::class);

        $studentProfile = $request->user()->studentProfile;

        if (! $studentProfile) {
            return $this->error('Hanya siswa yang dapat membuat submisi.', 403);
        }

        $studentProfileId = $studentProfile->id;
        $activityDate = $request->string('activity_date')->toString();

        // [Gap Timezone, Requirement Bagian 8] Ambil timezone sekolah siswa
        // via rantai relasi student_profile -> enrollment aktif -> rombel ->
        // school. ⚠️ ASUMSI nama relasi (enrollments/rombel/school) belum
        // 100% terverifikasi ke struktur Anggota B — pakai optional chaining
        // (?->) supaya kalau salah satu link relasi tidak sesuai dugaan,
        // otomatis fallback ke null (perilaku lama: pakai timezone server),
        // BUKAN error 500. Kalau ada yang lebih tau rantai relasi persisnya,
        // tolong perbaiki baris ini.
        $schoolTimezone = $studentProfile
            ?->enrollments()->where('status', 'active')->first()
            ?->rombel?->school?->timezone;

        if ($this->guard->isBackfillAttempt($activityDate, $schoolTimezone)) {
            return $this->error('Tidak tersedia pengisian susulan untuk hari yang terlewat.', 422);
        }

        if ($this->guard->isFutureDate($activityDate, $schoolTimezone)) {
            return $this->error('Tidak dapat mengisi untuk tanggal yang belum terjadi.', 422);
        }

        if ($this->guard->hasExistingSubmission($studentProfileId, $activityDate)) {
            return $this->error('Kamu sudah mengisi aktivitas untuk hari ini.', 422);
        }

        $submission = ActivitySubmission::create([
            'student_profile_id' => $studentProfileId,
            'activity_date' => $activityDate,
            'status' => 'draft',
        ]);

        return $this->success($submission, 'Submission berhasil dibuat.', 201);
    }

    public function show(Request $request, ActivitySubmission $submission)
    {
        $this->authorize('view', $submission);

        return $this->success($submission);
    }

    /**
     * PATCH /api/submissions/{submission}/lock
     * Placeholder — locking sesungguhnya dipicu otomatis oleh BE-007 setelah
     * submit+scoring berhasil, bukan dipanggil manual oleh siswa. Endpoint
     * ini disediakan supaya SubmissionPolicy::update bisa dites langsung
     * tanpa menunggu BE-007 selesai.
     */
    public function lock(Request $request, ActivitySubmission $submission)
    {
        $this->authorize('update', $submission);

        $submission->update([
            'status' => 'locked',
            'submitted_at' => now(),
            'locked_at' => now(),
        ]);

        return $this->success($submission, 'Submission dikunci.');
    }
}