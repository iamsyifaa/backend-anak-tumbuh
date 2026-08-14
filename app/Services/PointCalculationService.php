<?php

namespace App\Services;

use App\Models\IndicatorOption;
use App\Models\PointTransaction;
use App\Models\StudentProfile;
use App\Models\Submission;
use Illuminate\Support\Facades\DB;

class PointCalculationService
{
    /**
     * Hitung poin dari submission dan catat transaksi poin.
     */
    public function calculateAndRecord(Submission $submission): int
    {
        return DB::transaction(function () use ($submission) {
            $totalPoints = 0;

            // Load jawaban beserta opsi yang dipilih
            $submission->load('answers');

            foreach ($submission->answers as $answer) {
                // Cari poin bawaan dari opsi jawaban yang dipilih
                $option = IndicatorOption::where('indicator_id', $answer->indicator_id)
                    ->where('value', $answer->value)
                    ->first();

                if ($option) {
                    $totalPoints += $option->point_value;
                }
            }

            // Catat ke Point Transaction Log
            PointTransaction::create([
                'student_profile_id' => $submission->student_profile_id,
                'submission_id' => $submission->id,
                'amount' => $totalPoints,
                'type' => 'earned',
                'description' => 'Poin harian dari pengisian habit tanggal ' . $submission->date,
            ]);

            return $totalPoints;
        });
    }
}