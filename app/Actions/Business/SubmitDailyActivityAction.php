<?php

namespace App\Actions\Business;

use App\Models\ActivitySubmission;
use App\Models\SubmissionAnswer;
use App\Services\AnswerEngine\AnswerValidationService;
use App\Services\Scoring\ScoringService;
use Illuminate\Support\Facades\DB;

class SubmitDailyActivityAction
{
    public function __construct(
        private AnswerValidationService $answerValidationService,
        private ScoringService $scoringService,
    ) {}

    /**
     * @param  array<int,int>  $answers  [indicator_id => indicator_option_id]
     * @return array{status: string, submission: ActivitySubmission, errors?: array}
     */
    public function execute(ActivitySubmission $submission, array $answers): array
    {
        // IDEMPOTENCY: kalau submission ini sudah locked (pernah berhasil
        // di-submit sebelumnya), JANGAN proses ulang. Retry (misal client
        // resubmit karena network timeout) harus aman — tidak boleh bikin
        // scoring/transaksi dobel. Kembalikan hasil yang sudah ada, bukan error.
        if ($submission->isLocked()) {
            return [
                'status' => 'already_submitted',
                'submission' => $submission,
            ];
        }

        $errors = $this->answerValidationService->validate($answers);

        if (! empty($errors)) {
            return [
                'status' => 'validation_failed',
                'submission' => $submission,
                'errors' => $errors,
            ];
        }

        DB::transaction(function () use ($submission, $answers) {
            foreach ($answers as $indicatorId => $optionId) {
                // updateOrCreate, bukan create polos — proteksi tambahan
                // idempotency di level DB, selain unique constraint yang
                // sudah ada di migration submission_answers (BE-005).
                SubmissionAnswer::updateOrCreate(
                    [
                        'activity_submission_id' => $submission->id,
                        'indicator_id' => $indicatorId,
                    ],
                    [
                        'indicator_option_id' => $optionId,
                    ]
                );
            }

            $this->scoringService->scoreSubmission($submission->fresh());

            $submission->update([
                'status' => 'locked',
                'submitted_at' => now(),
                'locked_at' => now(),
            ]);
        });

        return [
            'status' => 'submitted',
            'submission' => $submission->fresh(),
        ];
    }
}