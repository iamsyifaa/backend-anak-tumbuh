<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Rombel;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class RombelFactory extends Factory
{
    protected $model = Rombel::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'name' => 'Kelas '.$this->faker->numberBetween(1, 6).$this->faker->randomLetter(),
            'status' => 'active',
        ];
    }
}