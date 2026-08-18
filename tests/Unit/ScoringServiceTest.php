<?php

namespace Tests\Unit;

use App\Services\Scoring\ScoringService;
use Tests\TestCase;

class ScoringServiceTest extends TestCase
{
    public function test_scoring_service_can_be_resolved_with_its_dependencies(): void
    {
        $this->assertInstanceOf(
            ScoringService::class,
            app(ScoringService::class)
        );
    }
}
