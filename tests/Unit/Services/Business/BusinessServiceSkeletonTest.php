<?php

namespace Tests\Unit\Services\Business;

use App\Services\Business\PointService;
use App\Services\Business\ExpService;
use App\Services\Business\LevelService;
use App\Services\Business\RankingService;
use Tests\TestCase;

class BusinessServiceSkeletonTest extends TestCase
{
    public function test_all_business_services_can_be_resolved_from_container(): void
    {
        $this->assertInstanceOf(PointService::class, app(PointService::class));
        $this->assertInstanceOf(ExpService::class, app(ExpService::class));
        $this->assertInstanceOf(LevelService::class, app(LevelService::class));
        $this->assertInstanceOf(RankingService::class, app(RankingService::class));
    }
}