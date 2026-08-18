<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'school_id' => null,
            'username' => $this->faker->unique()->userName(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => $this->faker->randomElement([
                'super_admin',
                'kepala_sekolah',
                'wali_kelas',
                'siswa',
            ]),
            'status' => 'active',
            'must_change_password' => false,
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => [
            'role' => 'super_admin',
        ]);
    }

    public function kepalaSekolah(): static
    {
        return $this->state(fn () => [
            'role' => 'kepala_sekolah',
        ]);
    }

    public function waliKelas(): static
    {
        return $this->state(fn () => [
            'role' => 'wali_kelas',
        ]);
    }

    public function siswa(): static
    {
        return $this->state(fn () => [
            'role' => 'siswa',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => 'inactive',
        ]);
    }
}
