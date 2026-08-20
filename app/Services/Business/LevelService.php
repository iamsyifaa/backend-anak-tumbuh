<?php

namespace App\Services\Business;

use App\Models\LevelThreshold;
use Illuminate\Support\Facades\Cache;

class LevelService
{
    private const CACHE_KEY = 'level_thresholds';

    /**
     * Ambil semua threshold level dari database, urut dari level terkecil.
     * Di-cache karena data ini jarang berubah tapi sering dibaca
     * (setiap submission/scoring memanggil calculateLevel).
     */
    private function thresholds(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, function () {
            return LevelThreshold::orderBy('level')
                ->pluck('required_exp', 'level')
                ->all();
        });
    }

    public function calculateLevel(int $totalExp): int
    {
        $level = 1;
        foreach ($this->thresholds() as $lvl => $requiredExp) {
            if ($totalExp >= $requiredExp) {
                $level = $lvl;
            }
        }

        return $level;
    }

    public function expToNextLevel(int $totalExp): ?int
    {
        $thresholds = $this->thresholds();
        $currentLevel = $this->calculateLevel($totalExp);
        $nextLevel = $currentLevel + 1;

        if (! isset($thresholds[$nextLevel])) {
            return null; // sudah level maksimum
        }

        return $thresholds[$nextLevel] - $totalExp;
    }
}