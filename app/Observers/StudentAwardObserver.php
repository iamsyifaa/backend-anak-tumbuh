<?php

namespace App\Observers;

use App\Models\StudentAward;
use App\Services\Gamification\CertificateGenerationService;

class StudentAwardObserver
{
    public function __construct(private CertificateGenerationService $certificateService) {}

    public function created(StudentAward $studentAward): void
    {
        $this->certificateService->generateForAward($studentAward);
    }
}