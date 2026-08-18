<?php

namespace Database\Factories;

use App\Models\Award;
use App\Models\Certificate;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    protected $model = Certificate::class;

    public function definition(): array
    {
        return [
            'student_profile_id' => StudentProfile::factory(),
            'award_id' => Award::factory(),
            'template_id' => null,
            'file_path' => 'certificates/'.$this->faker->uuid().'.pdf',
            'issued_at' => now(),
        ];
    }
}
