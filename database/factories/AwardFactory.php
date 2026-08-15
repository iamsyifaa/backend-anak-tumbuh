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
            'school_id' => School::factory(),
            'name' => $this->faker->words(3, true),
            'criteria' => ['type' => 'kebiasaan_konsisten', 'periode' => 'bulanan'],
            'active' => true,
        ];
    }
}