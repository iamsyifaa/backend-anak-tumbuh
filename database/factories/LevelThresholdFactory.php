<?php

namespace Database\Factories;

use App\Models\LevelThreshold;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LevelThreshold>
 */
class LevelThresholdFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'level' => fake()->unique()->numberBetween(1, 100),
            'required_exp' => fake()->numberBetween(0, 50000),
        ];
    }
}