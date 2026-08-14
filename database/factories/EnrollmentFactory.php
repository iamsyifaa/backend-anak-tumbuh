<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * NOTE: factory ini pakai App\Models\AcademicYear::factory(), model &
 * factory itu dibuat Anggota A di ORG-001. Kalau belum ada di project
 * kamu, minta AcademicYearFactory dari dia dulu sebelum test ini jalan.
 *
 * `rombel_id` sengaja diisi angka acak (bukan factory relasi) karena
 * tabel rombel belum ada — lihat catatan blocker di migration enrollments.
 */
class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    public function definition(): array
    {
        return [
            'student_profile_id' => StudentProfile::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'rombel_id' => fake()->numberBetween(1, 20),
            'status' => Enrollment::STATUS_ACTIVE,
            'started_at' => now()->subMonths(2),
            'ended_at' => null,
            'reason' => null,
        ];
    }

    public function ended(): static
    {
        return $this->state(fn () => [
            'status' => Enrollment::STATUS_ENDED,
            'ended_at' => now(),
        ]);
    }
}
