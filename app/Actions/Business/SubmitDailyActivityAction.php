<?php

namespace App\Actions\Business;

use App\Models\ActivitySubmission;
use App\Models\SubmissionAnswer;
use App\Services\AnswerEngine\AnswerValidationService;
use App\Services\Scoring\ScoringService;
use App\Services\Gamification\StreakService;
use App\Services\BadgeEvaluationService;
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

        DB::transaction(function () use ($submission, $answers) {
            foreach ($answers as $indicatorId => $optionId) {
                SubmissionAnswer::updateOrCreate(
                    ['activity_submission_id' => $submission->id, 'indicator_id' => $indicatorId],
                    ['indicator_option_id' => $optionId]
                );
            }

            $this->scoringService->scoreSubmission($submission->fresh());

            $userId = $submission->studentProfile->user_id;
            $this->streakService->recordActivity($userId, $submission->activity_date);
            $this->badgeEvaluationService->checkAndAwardBadges($submission->studentProfile);

            $submission->update(['status' => 'locked', 'submitted_at' => now(), 'locked_at' => now()]);
        });

        return ['status' => 'submitted', 'submission' => $submission->fresh()];
    }
}