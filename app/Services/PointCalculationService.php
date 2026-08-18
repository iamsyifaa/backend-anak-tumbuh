<?php

namespace App\Services;

use App\Models\ActivitySubmission;
use App\Models\PointTransaction;
use Illuminate\Support\Facades\DB;

/**
 * FIX (review MASTER-005) — mengikuti struktur ASLI point_transactions
 * (Anggota A, SEC-005) dan submission_answers (Anggota C, BE-005/006/007,
 * baru tersedia 15 Agustus 2026):
 *
 * - Poin dicatat PER-JAWABAN. source_type='submission_answer',
 *   source_id = id baris submission_answers.
 * - Kolomnya user_id (App\Models\User), BUKAN student_profile_id.
 * - submission_answers menyimpan `indicator_option_id` (FK langsung ke
 *   indicator_options), BUKAN string 'value' seperti dugaan awal —
 *   jadi tidak perlu lagi query cari opsi berdasarkan value, tinggal
 *   pakai relasi `option` yang sudah disediakan SubmissionAnswer.
 * - Idempotency dicek PER-JAWABAN via (source_type, source_id).
 */
class PointCalculationService
{
    private const SOURCE_TYPE = 'submission_answer';

    /**
     * Hitung & catat poin untuk setiap jawaban dalam 1 submission yang
     * BELUM pernah dihitung. Return total poin baru yang ditambahkan.
     */
    public function calculateAndRecord(ActivitySubmission $submission): int
    {
        return DB::transaction(function () use ($submission) {
            if (! method_exists($submission, 'answers')) {
                throw new \RuntimeException(
                    'ActivitySubmission belum punya relasi answers() ke submission_answers.'
                );
            }

            $submission->loadMissing(['answers.option', 'studentProfile.user']);
            $answers = $submission->answers;

            if ($answers->isEmpty()) {
                throw new \RuntimeException(
                    "Submission #{$submission->id} belum punya jawaban — tidak bisa dihitung poinnya."
                );
            }

            $userId = $submission->studentProfile->user_id;
            $totalNewPoints = 0;

            foreach ($answers as $answer) {
                $alreadyRecorded = PointTransaction::where('source_type', self::SOURCE_TYPE)
                    ->where('source_id', $answer->id)
                    ->exists();

                if ($alreadyRecorded) {
                    continue;
                }

                $points = $answer->option->point_value ?? 0;

                PointTransaction::create([
                    'user_id' => $userId,
                    'amount' => $points,
                    'source_type' => self::SOURCE_TYPE,
                    'source_id' => $answer->id,
                    'period_date' => $submission->activity_date,
                ]);

                $totalNewPoints += $points;
            }

            return $totalNewPoints;
        });
    }
}
