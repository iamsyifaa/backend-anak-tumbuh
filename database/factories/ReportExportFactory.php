<?php

namespace Database\Factories;

use App\Models\ReportExport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportExportFactory extends Factory
{
    protected $model = ReportExport::class;

    public function definition(): array
    {
        return [
            'requested_by' => User::factory(),
            'type' => 'recap', // <-- Ditambahkan field 'type' default
            'scope_type' => 'school',
            'scope_id' => 1,
            'file_path' => 'reports/'.$this->faker->uuid().'.xlsx',
            'format' => 'xlsx',
            'expires_at' => now()->addDay(),
        ];
    }

    public function forStudent(int $studentProfileId): static
    {
        return $this->state(fn () => ['scope_type' => 'student', 'scope_id' => $studentProfileId]);
    }

    public function forRombel(int $rombelId): static
    {
        return $this->state(fn () => ['scope_type' => 'rombel', 'scope_id' => $rombelId]);
    }

    public function forSchool(int $schoolId): static
    {
        return $this->state(fn () => ['scope_type' => 'school', 'scope_id' => $schoolId]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }
}
