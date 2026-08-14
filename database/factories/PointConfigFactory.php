<?php

namespace Database\Factories;

use App\Models\PointConfig;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class PointConfigFactory extends Factory
{
    protected $model = PointConfig::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'version' => $this->faker->unique()->numberBetween(1, 100000),
            'effective_date' => $this->faker->dateTimeBetween('now', '+1 month'),
            'status' => 'draft',
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}