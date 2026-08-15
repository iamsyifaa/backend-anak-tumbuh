<?php

namespace App\Services;

use App\Models\ActivitySubmission;
use App\Models\IndicatorOption;
use App\Models\PointTransaction;
use Illuminate\Support\Facades\DB;

/**
 * FIX (review MASTER-005) — mengikuti struktur ASLI point_transactions
 * (dibuat Anggota A, SEC-005), yang berbeda dari asumsi awal:
 *
 * - Poin dicatat PER-JAWABAN (bukan 1 baris rangkuman per submission).
 *   Komentar migration eksplisit contohkan:
 *   source_type = 'submission_answer', source_id = id baris submission_answers.
 * - Kolomnya user_id (App\Models\User), BUKAN student_profile_id.
 * - Idempotency dicek PER-JAWABAN via (source_type, source_id) — supaya
 *   kalau proses kepotong di tengah jalan, baris yang sudah tercatat
 *   tidak dihitung ulang, sisanya tetap bisa lanjut (bukan all-or-nothing
 *   di level submission).
 *
 * ⚠️ BLOCKER YANG MASIH ADA: tabel `submission_answers` (dan relasi
 * `answers` di ActivitySubmission) belum dibuat — itu scope BE-005/006/007
 * (Anggota C), belum dimulai per 15 Agustus 2026. Method di bawah
 * ditulis defensif, siap dipanggil begitu itu ada, TANPA perlu ditulis
 * ulang — asal nama relasi & kolom yang dipakai di sini (indicator_id,
 * value) dikoordinasikan dulu ke Anggota C sebelum dia bikin tabelnya.
 */
class PointCalculationService
{
    private const SOURCE_TYPE = 'submission_answer';

    /**
     * Hitung & catat poin untuk setiap jawaban dalam 1 submission yang
     * BELUM pernah dihitung. Return total poin baru yang ditambahkan
     * (bisa 0 kalau semua jawaban ternyata sudah pernah dihitung).
     */
    public function calculateAndRecord(ActivitySubmission $submission): int
    {
        return DB::transaction(function () use ($submission) {
            if (! method_exists($submission, 'answers')) {
                throw new \RuntimeException(
                    'ActivitySubmission belum punya relasi answers() — fitur submit jawaban (BE-005/006/007) belum tersedia.'
                );
            }

            $submission->loadMissing(['answers', 'studentProfile.user']);
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

                $option = IndicatorOption::where('indicator_id', $answer->indicator_id)
                    ->where('value', $answer->value)
                    ->first();

                $points = $option->point_value ?? 0;

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