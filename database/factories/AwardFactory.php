<?php

namespace Database\Factories;

use App\Models\Award;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class AwardFactory extends Factory
{
    protected $model = Award::class;

    public function definition(): array
{
    return [
        'code' => 'AWARD-' . $this->faker->unique()->numberBetween(1000, 9999),
        'name' => $this->faker->words(3, true),
        'description' => $this->faker->sentence(),
        'generates_certificate' => false,
        'active' => true,
    ];
}
}