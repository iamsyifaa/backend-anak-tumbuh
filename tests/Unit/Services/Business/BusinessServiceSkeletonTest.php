<?php

namespace Tests\Unit\Services\Business;

use App\Services\Business\AchievementService;
use App\Services\Business\CommentService;
use App\Services\Business\ExpService;
use App\Services\Business\LevelService;
use App\Services\Business\PointService;
use App\Services\Business\RankingService;
use App\Services\Business\StreakService;
use App\Services\Business\SubmissionService;
use Tests\TestCase;

class BusinessServiceSkeletonTest extends TestCase
{
    public function test_all_business_services_can_be_resolved_from_container(): void
    {
        $this->assertInstanceOf(SubmissionService::class, app(SubmissionService::class));
        $this->assertInstanceOf(PointService::class, app(PointService::class));
        $this->assertInstanceOf(ExpService::class, app(ExpService::class));
        $this->assertInstanceOf(LevelService::class, app(LevelService::class));
        $this->assertInstanceOf(StreakService::class, app(StreakService::class));
        $this->assertInstanceOf(AchievementService::class, app(AchievementService::class));
        $this->assertInstanceOf(RankingService::class, app(RankingService::class));
        $this->assertInstanceOf(CommentService::class, app(CommentService::class));
    }
}
