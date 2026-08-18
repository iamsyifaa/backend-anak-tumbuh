<?php

namespace Database\Factories;

use App\Models\Badge;
use App\Models\StudentBadge;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentBadgeFactory extends Factory
{
    protected $model = StudentBadge::class;

    public function definition(): array
    {
        return [
            'badge_id' => Badge::factory(),
            'awarded_at' => now(),
        ];
    }
}
