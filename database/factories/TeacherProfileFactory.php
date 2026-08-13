<?php

namespace Database\Factories;

use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeacherProfileFactory extends Factory
{
    protected $model = TeacherProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->waliKelas(),
            'full_name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
        ];
    }
}
