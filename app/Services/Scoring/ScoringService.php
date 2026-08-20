<?php

namespace App\Services\Scoring;

use App\Models\ActivitySubmission;
use App\Models\PointConfig;
use App\Models\SubmissionAnswer;
use App\Services\Business\ExpService;
use App\Services\Business\PointService;
use App\Services\DailyPeriod\DailyPeriodService;
use Carbon\Carbon;

class ScoringService
{
    private const INITIATIVE_INDICATOR_CODE = 'inisiatif';

    private const INITIATIVE_OPTION_VALUE = 'sadar_sendiri';

    public function __construct(
        private PointService $pointService,
        private ExpService $expService,
        private DailyPeriodService $dailyPeriodService,
    ) {}

    public function scoreSubmission(ActivitySubmission $submission): void
    {
        $activityDate = Carbon::parse($submission->activity_date);

        if (! $this->dailyPeriodService->isPeriodOpenForSubmission($activityDate)) {
            // Konsisten dengan aturan BE-002: tidak ada backfill.
            // Submission di luar periode aktif TIDAK dihitung skornya sama sekali.
            return;
        }

        $userId = $submission->studentProfile->user_id;
        $periodDate = $activityDate->toDateString();

        $pointConfig = $this->resolveActivePointConfig($submission);
        $bonusPoints = $pointConfig?->initiative_bonus_points ?? 0;

        $answers = SubmissionAnswer::with(['indicator', 'option'])
            ->where('activity_submission_id', $submission->id)
            ->get();

        $answersByHabit = $answers->groupBy(fn (SubmissionAnswer $a) => $a->indicator->habit_id);

        foreach ($answersByHabit as $habitAnswers) {
            $initiativeAnswer = $habitAnswers->first(
                fn (SubmissionAnswer $a) => $a->indicator->code === self::INITIATIVE_INDICATOR_CODE
            );

            $isSadarSendiri = $initiativeAnswer
                && $initiativeAnswer->option->value === self::INITIATIVE_OPTION_VALUE;

            foreach ($habitAnswers as $answer) {
                $basePoints = $answer->option->point_value;
                $baseExp = $answer->option->exp_value;

                $this->pointService->record(
                    $userId, $basePoints, 'submission_answer', $answer->id, $periodDate
                );
                $this->expService->record(
                    $userId, $baseExp, 'submission_answer', $answer->id, $periodDate
                );
            }

            if ($isSadarSendiri && $bonusPoints > 0) {
                $this->pointService->record(
                    $userId, $bonusPoints, 'initiative_bonus', $initiativeAnswer->id, $periodDate
                );
            }
        }
    }

    private function resolveActivePointConfig(ActivitySubmission $submission): ?PointConfig
    {
        $schoolId = $submission->studentProfile->currentEnrollment()->first()?->academicYear?->school_id;

        if (! $schoolId) {
            return null;
        }

        return PointConfig::where('school_id', $schoolId)
            ->where('status', 'published')
            ->where('effective_date', '<=', $submission->activity_date)
            ->orderByDesc('effective_date')
            ->first();
    }
}
