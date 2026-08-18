<?php

namespace Tests\Unit\Domain;

use App\Services\Business\LevelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BusinessRulesRegressionTest extends TestCase
{
    use RefreshDatabase;

    public static function levelThresholdProvider(): array
    {
        return [
            'exp 0 -> level 1' => [0, 1],
            'exp 99 -> level 1' => [99, 1],
            'exp 100 -> level 2' => [100, 2],
            'exp 249 -> level 2' => [249, 2],
            'exp 250 -> level 3' => [250, 3],
            'exp 2700 -> level 10' => [2700, 10],
            'exp jauh di atas max -> tetap level 10' => [999999, 10],
        ];
    }

    #[DataProvider('levelThresholdProvider')]
    public function test_level_thresholds_are_correct(int $exp, int $expectedLevel): void
    {
        $level = app(LevelService::class)->calculateLevel($exp);

        $this->assertSame($expectedLevel, $level, "EXP {$exp} seharusnya level {$expectedLevel}, dapat level {$level}");
    }

    public static function initiativeBonusProvider(): array
    {
        return [
            'sadar sendiri -> dapat bonus poin' => ['sadar_sendiri', true],
            'disuruh -> TIDAK dapat bonus' => ['disuruh', false],
        ];
    }

    #[DataProvider('initiativeBonusProvider')]
    public function test_initiative_bonus_rules(string $optionValue, bool $shouldGetBonus): void
    {
        $isInitiative = ($optionValue === 'sadar_sendiri');

        $this->assertSame(
            $shouldGetBonus,
            $isInitiative,
            "Aturan inisiatif untuk opsi '{$optionValue}' tidak sesuai."
        );
    }
}
