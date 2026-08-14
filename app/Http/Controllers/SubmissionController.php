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

    public function __construct(private readonly SubmissionGuardService $guard)
    {
    }

    public function store(SubmissionRequest $request)
    {
        $this->authorize('create', ActivitySubmission::class);

        $studentProfileId = $request->user()->studentProfile->id;
        $activityDate = $request->string('activity_date')->toString();

        if ($this->guard->isBackfillAttempt($activityDate)) {
            return $this->error('Tidak tersedia pengisian susulan untuk hari yang terlewat.', 422);
        }

        if ($this->guard->isFutureDate($activityDate)) {
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