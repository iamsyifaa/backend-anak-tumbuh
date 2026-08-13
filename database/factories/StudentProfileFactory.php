<?php

namespace Database\Factories;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentProfileFactory extends Factory
{
    protected $model = StudentProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->siswa(),
            'full_name' => fake()->name(),
            'method' => 'digital',
            'birth_date' => fake()->dateTimeBetween('-13 years', '-6 years'),
        ];
    }
}
