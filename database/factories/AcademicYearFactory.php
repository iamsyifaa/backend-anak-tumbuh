<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class AcademicYearFactory extends Factory
{
    protected $model = AcademicYear::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('-1 year', 'now');

        return [
            'school_id' => School::factory(),
            'name' => date('Y', $start->getTimestamp()).'/'.(date('Y', $start->getTimestamp()) + 1),
            'start_date' => $start,
            'end_date' => (clone $start)->modify('+1 year'),
            'status' => 'inactive',
        ];
    }
}