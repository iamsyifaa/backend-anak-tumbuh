<?php

namespace App\Services\StudentStatistics;

use App\Queries\StudentExpQuery;
use App\Queries\StudentPointQuery;

class StudentStatisticsService
{
    public function __construct(
        private StudentPointQuery $pointQuery,
        private StudentExpQuery $expQuery,
    ) {}

    /**
     * Contract stabil: bentuk array ini TIDAK BOLEH berubah sewaktu-waktu,
     * karena dashboard (Anggota D) akan bergantung pada struktur ini.
     * Level dan streak masih placeholder — diisi sungguhan di BE-008.
     */
    public function getStatistics(int $userId): array
    {
        return [
            'user_id' => $userId,
            'total_points' => $this->pointQuery->totalPoints($userId),
            'total_exp' => $this->expQuery->totalExp($userId),
            'level' => null, // placeholder — diisi LevelService di BE-008
            'streak' => null, // placeholder — diisi StreakService di BE-008
            'activity_history' => [], // placeholder — diisi saat submission history tersedia (BE-007)
        ];
    }
}
