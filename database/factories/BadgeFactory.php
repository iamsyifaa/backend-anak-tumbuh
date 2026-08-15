<?php

namespace Database\Factories;

use App\Models\Badge;
use Illuminate\Database\Eloquent\Factories\Factory;

class BadgeFactory extends Factory
{
    protected $model = Badge::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('BADGE-####')),
            'name' => $this->faker->words(3, true),
            'criteria' => ['type' => 'target_pencapaian', 'value' => 5],
            'active' => true,
        ];
    }
}