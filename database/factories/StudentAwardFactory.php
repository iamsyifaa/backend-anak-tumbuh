<?php

namespace Database\Factories;

use App\Models\Award;
use App\Models\StudentAward;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentAwardFactory extends Factory
{
    protected $model = StudentAward::class;

    public function definition(): array
    {
        return [
            'award_id' => Award::factory(),
            'awarded_at' => now(),
        ];
    }
}