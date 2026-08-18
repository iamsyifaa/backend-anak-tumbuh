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
        // unique() memastikan start_date antar panggilan factory tidak pernah sama persis,
        // supaya 'name' (diturunkan dari tahun start_date) juga tidak bentrok antar academic
        // year dalam sekolah yang sama — mencegah UniqueConstraintViolationException flaky
        // saat test membuat >1 AcademicYear untuk school_id yang sama.
        $start = $this->faker->unique()->dateTimeBetween('-5 years', 'now');

        return [
            'school_id' => School::factory(),
            'name' => date('Y', $start->getTimestamp()).'/'.(date('Y', $start->getTimestamp()) + 1),
            'start_date' => $start,
            'end_date' => (clone $start)->modify('+1 year'),
            'status' => 'inactive',
        ];
    }
}
