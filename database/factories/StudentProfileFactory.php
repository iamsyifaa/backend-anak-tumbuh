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
            'user_id' => User::factory()->state(['role' => User::ROLE_SISWA]),
            'full_name' => fake()->name(),
            'method' => StudentProfile::METHOD_DIGITAL,
            'status' => StudentProfile::STATUS_ACTIVE,
            'birth_date' => fake()->dateTimeBetween('-13 years', '-6 years'),
            'nisn' => fake()->unique()->numerify('##########'),
        ];
    }

    public function manual(): static
    {
        return $this->state(fn () => ['method' => StudentProfile::METHOD_MANUAL]);
    }

    public function graduated(): static
    {
        return $this->state(fn () => ['status' => StudentProfile::STATUS_GRADUATED]);
    }

    public function transferred(): static
    {
        return $this->state(fn () => ['status' => StudentProfile::STATUS_TRANSFERRED]);
    }
}