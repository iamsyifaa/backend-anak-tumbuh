<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => User::ROLE_SISWA,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_SUPER_ADMIN]);
    }

    public function kepalaSekolah(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_KEPALA_SEKOLAH]);
    }

    public function waliKelas(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_WALI_KELAS]);
    }

    public function siswa(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_SISWA]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
