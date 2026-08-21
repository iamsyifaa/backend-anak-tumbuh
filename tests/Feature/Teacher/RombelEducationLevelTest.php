<?php

namespace Tests\Feature\Teacher;

use App\Models\EducationLevel;
use App\Models\Rombel;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tugas kecil dari Anggota B (via Anggota C) — sambungkan rombels ke
 * education_levels (master data baru milik Anggota B). Test ini cuma
 * verifikasi relasi & FK jalan benar; TIDAK menguji CRUD education_levels
 * itu sendiri (itu tanggung jawab Anggota B).
 *
 * ⚠️ ASUMSI: model App\Models\EducationLevel & factory-nya sudah ada di
 * codebase (dibuat Anggota B, katanya sudah merge ke main). Kalau nama
 * model/factory beda, sesuaikan test ini.
 */
class RombelEducationLevelTest extends TestCase
{
    use RefreshDatabase;

    public function test_rombel_can_be_linked_to_education_level(): void
    {
        $school = School::factory()->create();
        $level = EducationLevel::factory()->create(['school_id' => $school->id, 'name' => 'Kelas 5']);
        $rombel = Rombel::factory()->create(['school_id' => $school->id, 'education_level_id' => $level->id]);

        $this->assertSame('Kelas 5', $rombel->fresh()->educationLevel->name);
    }

    public function test_rombel_education_level_is_nullable_for_existing_rows(): void
    {
        $rombel = Rombel::factory()->create(); // education_level_id tidak di-set.

        $this->assertNull($rombel->fresh()->education_level_id);
        $this->assertNull($rombel->fresh()->educationLevel);
    }

    public function test_deleting_education_level_does_not_delete_rombel(): void
    {
        $school = School::factory()->create();
        $level = EducationLevel::factory()->create(['school_id' => $school->id]);
        $rombel = Rombel::factory()->create(['school_id' => $school->id, 'education_level_id' => $level->id]);

        $level->delete();

        $this->assertDatabaseHas('rombels', ['id' => $rombel->id, 'education_level_id' => null]);
    }
}