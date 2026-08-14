<?php

namespace App\Services\Scoring;

use App\Services\Business\PointService;
use App\Services\Business\ExpService;
use App\Services\DailyPeriod\DailyPeriodService;

class ScoringService
{
    public function __construct(
        private PointService $pointService,
        private ExpService $expService,
        private DailyPeriodService $dailyPeriodService,
    ) {}

    // Logika hitung Poin/EXP sungguhan diisi di BE-006 (Scoring Engine).
    // Hari ini baru kerangka + dependency injection saja, sesuai batasan BE-002:
    // "Jangan menghitung Poin/EXP dulu jika data option belum tersedia."
}