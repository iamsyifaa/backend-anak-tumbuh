<?php

namespace App\Actions\Business;

use App\Models\ActivitySubmission;
use App\Models\SubmissionAnswer;
use App\Services\AnswerEngine\AnswerValidationService;
use App\Services\BadgeEvaluationService;
use App\Services\Gamification\StreakService;
use App\Services\Scoring\ScoringService;
use Illuminate\Support\Facades\DB;

class SubmitDailyActivityAction
{
    public function __construct(
        private AnswerValidationService $answerValidationService,
        private ScoringService $scoringService,
        private StreakService $streakService,
        private BadgeEvaluationService $badgeEvaluationService,
    ) {}

    public function execute(ActivitySubmission $submission, array $answers): array
    {
        if ($submission->isLocked()) {
            return ['status' => 'already_submitted', 'submission' => $submission];
        }

        $errors = $this->answerValidationService->validate($answers);

        if (! empty($errors)) {
            return ['status' => 'validation_failed', 'submission' => $submission, 'errors' => $errors];
        }

        $result = DB::transaction(function () use ($submission, $answers) {
            // lockForUpdate(): kunci baris ini di level DB sepanjang transaksi.
            // Kalau ada request lain masuk BERSAMAAN untuk submission yang sama,
            // dia akan MENUNGGU transaksi ini selesai, baru baca status TERBARU
            // (yang sudah 'locked') — bukan status lama yang masih 'draft'.
            // Ini menutup celah race condition yang tidak tertangkap test retry
            // berurutan (SubmissionRegressionTest hanya menguji panggilan
            // sekuensial, bukan konkuren).
            $lockedSubmission = ActivitySubmission::where('id', $submission->id)
                ->lockForUpdate()
                ->first();

            if ($lockedSubmission->isLocked()) {
                return ['status' => 'already_submitted', 'submission' => $lockedSubmission];
            }

            foreach ($answers as $indicatorId => $optionId) {
                SubmissionAnswer::updateOrCreate(
                    ['activity_submission_id' => $lockedSubmission->id, 'indicator_id' => $indicatorId],
                    ['indicator_option_id' => $optionId]
                );
            }

            $this->scoringService->scoreSubmission($lockedSubmission->fresh());

            $userId = $lockedSubmission->studentProfile->user_id;
            $this->streakService->recordActivity($userId, $lockedSubmission->activity_date);
            $this->badgeEvaluationService->checkAndAwardBadges($lockedSubmission->studentProfile);

            $lockedSubmission->update(['status' => 'locked', 'submitted_at' => now(), 'locked_at' => now()]);

            return ['status' => 'submitted', 'submission' => $lockedSubmission->fresh()];
        });

        return $result;
    }
}
