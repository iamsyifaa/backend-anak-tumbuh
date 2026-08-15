<?php

namespace App\Actions\Business;

use App\Services\Business\SubmissionService;
use App\Services\Business\PointService;
use App\Services\Business\ExpService;

class SubmitDailyActivityAction
{
    public function __construct(
        private SubmissionService $submissionService,
        private PointService $pointService,
        private ExpService $expService,
    ) {}

    // Logika orkestrasi lengkap diisi di BE-007 (Submission Pipeline).
    public function execute(): void
    {
        // TODO BE-007
    }
}