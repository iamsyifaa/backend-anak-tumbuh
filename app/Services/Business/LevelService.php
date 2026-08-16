<?php

namespace App\Services\Business;

class LevelService
{
    /**
     * PLACEHOLDER — angka ini BUKAN final. Requirement eksplisit bilang:
     * "Angka final kebutuhan EXP ditentukan saat balancing/configuration."
     * Kurva di bawah cuma contoh masuk akal (tiap level makin sulit),
     * supaya dashboard bisa jalan sekarang. WAJIB direview tim sebelum rilis.
     *
     * Index array = level, value = kumulatif EXP minimum untuk level itu.
     */
    private const LEVEL_THRESHOLDS = [
        1 => 0,
        2 => 100,
        3 => 250,
        4 => 450,
        5 => 700,
        6 => 1000,
        7 => 1350,
        8 => 1750,
        9 => 2200,
        10 => 2700,
    ];

    public function calculateLevel(int $totalExp): int
    {
        $level = 1;

        foreach (self::LEVEL_THRESHOLDS as $lvl => $requiredExp) {
            if ($totalExp >= $requiredExp) {
                $level = $lvl;
            }
        }

        return $level;
    }

    public function expToNextLevel(int $totalExp): ?int
    {
        $currentLevel = $this->calculateLevel($totalExp);
        $nextLevel = $currentLevel + 1;

        if (! isset(self::LEVEL_THRESHOLDS[$nextLevel])) {
            return null; // sudah level maksimum
        }

        return self::LEVEL_THRESHOLDS[$nextLevel] - $totalExp;
    }
}