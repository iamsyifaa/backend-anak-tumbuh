<?php

namespace Tests\Unit;

use App\Models\Habit;
use App\Models\HabitIndicator;
use App\Models\IndicatorCondition;
use App\Models\IndicatorOption;
use App\Services\AnswerEngine\AnswerValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnswerValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeHabit(): Habit
    {
        return Habit::create([
            'code' => 'habit_'.uniqid(),
            'name' => 'Kebiasaan Test',
        ]);
    }

    private function makeIndicatorWithOption(int $habitId, string $optionValue = 'yes'): array
    {
        $indicator = HabitIndicator::create([
            'habit_id' => $habitId,
            'code' => 'ind_'.uniqid(),
            'label' => 'Indikator Test',
            'is_required' => true,
            'sort_order' => 1,
            'active' => true,
        ]);

        $option = IndicatorOption::create([
            'indicator_id' => $indicator->id,
            'label' => 'Opsi Test',
            'value' => $optionValue,
            'point_value' => 1,
            'sort_order' => 1,
            'active' => true,
        ]);

        return [$indicator, $option];
    }

    public function test_unconditional_indicator_is_always_valid(): void
    {
        $habit = $this->makeHabit();
        [$indicator, $option] = $this->makeIndicatorWithOption($habit->id);

        $service = app(AnswerValidationService::class);
        $errors = $service->validate([$indicator->id => $option->id]);

        $this->assertEmpty($errors);
    }

    public function test_conditional_indicator_valid_when_condition_met(): void
    {
        $habit = $this->makeHabit();
        [$parent, $parentOption] = $this->makeIndicatorWithOption($habit->id, 'sudah_mandi');
        [$child, $childOption] = $this->makeIndicatorWithOption($habit->id, 'pakai_sabun');

        IndicatorCondition::create([
            'indicator_id' => $child->id,
            'parent_indicator_id' => $parent->id,
            'required_option_value' => 'sudah_mandi',
        ]);

        $service = app(AnswerValidationService::class);
        $errors = $service->validate([
            $parent->id => $parentOption->id,
            $child->id => $childOption->id,
        ]);

        $this->assertEmpty($errors);
    }

    public function test_conditional_indicator_rejected_when_parent_not_answered(): void
    {
        $habit = $this->makeHabit();
        [$parent] = $this->makeIndicatorWithOption($habit->id, 'sudah_mandi');
        [$child, $childOption] = $this->makeIndicatorWithOption($habit->id, 'pakai_sabun');

        IndicatorCondition::create([
            'indicator_id' => $child->id,
            'parent_indicator_id' => $parent->id,
            'required_option_value' => 'sudah_mandi',
        ]);

        $service = app(AnswerValidationService::class);
        $errors = $service->validate([
            $child->id => $childOption->id,
        ]);

        $this->assertArrayHasKey($child->id, $errors);
    }

    public function test_conditional_indicator_rejected_when_parent_answer_does_not_match(): void
    {
        $habit = $this->makeHabit();
        [$parent] = $this->makeIndicatorWithOption($habit->id, 'sudah_mandi');
        [, $wrongParentOption] = $this->makeIndicatorWithOption($habit->id, 'belum_mandi');
        [$child, $childOption] = $this->makeIndicatorWithOption($habit->id, 'pakai_sabun');

        IndicatorCondition::create([
            'indicator_id' => $child->id,
            'parent_indicator_id' => $parent->id,
            'required_option_value' => 'sudah_mandi',
        ]);

        $service = app(AnswerValidationService::class);
        $errors = $service->validate([
            $parent->id => $wrongParentOption->id,
            $child->id => $childOption->id,
        ]);

        $this->assertArrayHasKey($child->id, $errors);
    }

    public function test_option_not_belonging_to_indicator_is_rejected(): void
    {
        $habit = $this->makeHabit();
        [$indicatorA] = $this->makeIndicatorWithOption($habit->id);
        [, $optionFromB] = $this->makeIndicatorWithOption($habit->id);

        $service = app(AnswerValidationService::class);
        $errors = $service->validate([
            $indicatorA->id => $optionFromB->id,
        ]);

        $this->assertArrayHasKey($indicatorA->id, $errors);
    }
}
