<?php

namespace Database\Factories;

use App\Models\Award;
use App\Models\StudentAward;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentAwardFactory extends Factory
{
    protected $model = StudentAward::class;

    public function definition(): array
    {
        return [
            'student_profile_id' => StudentProfile::factory(),
            'award_id' => Award::factory(),
            'given_by' => User::factory(),
        ];
    }
}