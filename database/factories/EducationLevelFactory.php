<?php

namespace Database\Factories;

use App\Models\EducationLevel;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * ⚠️ Dibuat oleh Anggota A karena model App\Models\EducationLevel (milik
 * Anggota B) sudah ter-push ke main, tapi factory-nya belum ikut. Struktur
 * kolom di sini mengikuti spesifikasi dari brief Anggota B: id, school_id,
 * name, order, status. Kalau ternyata kolomnya beda (mis. field tambahan
 * yang belum disebutkan), sesuaikan atau biarkan Anggota B menimpa file ini
 * dengan versi resminya — tidak masalah, ini cuma untuk testing.
 */
class EducationLevelFactory extends Factory
{
    protected $model = EducationLevel::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => 'Kelas '.$this->faker->numberBetween(1, 6),
            'order' => $this->faker->numberBetween(1, 6),
            'status' => 'active',
        ];
    }
}