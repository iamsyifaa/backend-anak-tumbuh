<?php

namespace Tests\Feature;

use App\Models\LevelThreshold;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LevelThresholdControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // ------------------------------------------------------------
    // AUTHORIZATION
    // ------------------------------------------------------------

    public function test_super_admin_can_view_level_thresholds(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($superAdmin)->getJson('/api/level-thresholds');

        $response->assertOk();
    }

    public function test_non_super_admin_cannot_create_level_threshold(): void
    {
        $waliKelas = User::factory()->waliKelas()->create();

        $response = $this->actingAs($waliKelas)->postJson('/api/level-thresholds', [
            'level' => 11,
            'required_exp' => 5000,
        ]);

        $response->assertForbidden();
    }

    /**
     * Ini test yang paling penting untuk membuktikan deviasi dari pola Habit:
     * Kepala Sekolah BOLEH kelola habit (habit.manage ada di role-nya di
     * config/permissions.php), tapi TIDAK BOLEH kelola level threshold
     * (requirement Bagian 9 & 10: Super Admin saja). Route middleware-nya
     * sengaja beda dari grup Habit (lihat level_threshold_routes_snippet.php).
     */
    public function test_kepala_sekolah_cannot_access_level_thresholds(): void
    {
        $kepalaSekolah = User::factory()->kepalaSekolah()->create();

        $response = $this->actingAs($kepalaSekolah)->getJson('/api/level-thresholds');

        $response->assertForbidden();
    }

    public function test_guest_cannot_access_level_thresholds(): void
    {
        $response = $this->getJson('/api/level-thresholds');

        $response->assertUnauthorized();
    }

    // ------------------------------------------------------------
    // CRUD
    // ------------------------------------------------------------

    public function test_super_admin_can_create_level_threshold(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($superAdmin)->postJson('/api/level-thresholds', [
            'level' => 11,
            'required_exp' => 12000,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('level_thresholds', [
            'level' => 11,
            'required_exp' => 12000,
        ]);
    }

    public function test_cannot_create_level_threshold_with_duplicate_level(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        LevelThreshold::factory()->create(['level' => 20]);

        $response = $this->actingAs($superAdmin)->postJson('/api/level-thresholds', [
            'level' => 20,
            'required_exp' => 3000,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('level');
    }

    public function test_super_admin_can_update_required_exp(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $threshold = LevelThreshold::factory()->create(['level' => 20, 'required_exp' => 1000]);

        $response = $this->actingAs($superAdmin)
            ->putJson("/api/level-thresholds/{$threshold->id}", [
                'required_exp' => 1500,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('level_thresholds', [
            'id' => $threshold->id,
            'required_exp' => 1500,
        ]);
    }

    public function test_only_highest_level_can_be_deleted(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $middleLevel = LevelThreshold::factory()->create(['level' => 20, 'required_exp' => 2000]);
        LevelThreshold::factory()->create(['level' => 30, 'required_exp' => 9000]);

        $response = $this->actingAs($superAdmin)
            ->deleteJson("/api/level-thresholds/{$middleLevel->id}");

        $response->assertUnprocessable();
        $this->assertDatabaseHas('level_thresholds', ['id' => $middleLevel->id]);
    }

    public function test_highest_level_can_be_deleted(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        LevelThreshold::factory()->create(['level' => 20, 'required_exp' => 2000]);
        $highest = LevelThreshold::factory()->create(['level' => 30, 'required_exp' => 9000]);

        $response = $this->actingAs($superAdmin)
            ->deleteJson("/api/level-thresholds/{$highest->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('level_thresholds', ['id' => $highest->id]);
    }

    // ------------------------------------------------------------
    // CACHE INVALIDATION — ini bagian paling penting untuk task ini
    // ------------------------------------------------------------

    public function test_creating_threshold_invalidates_cache(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        // isi cache dulu, simulasikan sudah pernah dipanggil LevelService
        Cache::put('level_thresholds', LevelThreshold::orderBy('level')->get(), 3600);
        $this->assertTrue(Cache::has('level_thresholds'));

        $this->actingAs($superAdmin)->postJson('/api/level-thresholds', [
            'level' => 11,
            'required_exp' => 12000,
        ]);

        $this->assertFalse(Cache::has('level_thresholds'));
    }

    public function test_updating_threshold_invalidates_cache(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $threshold = LevelThreshold::factory()->create(['level' => 20, 'required_exp' => 1000]);

        Cache::put('level_thresholds', LevelThreshold::orderBy('level')->get(), 3600);
        $this->assertTrue(Cache::has('level_thresholds'));

        $this->actingAs($superAdmin)->putJson("/api/level-thresholds/{$threshold->id}", [
            'required_exp' => 1500,
        ]);

        $this->assertFalse(Cache::has('level_thresholds'));
    }

    public function test_deleting_threshold_invalidates_cache(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $highest = LevelThreshold::factory()->create(['level' => 30, 'required_exp' => 9000]);

        Cache::put('level_thresholds', LevelThreshold::orderBy('level')->get(), 3600);
        $this->assertTrue(Cache::has('level_thresholds'));

        $this->actingAs($superAdmin)->deleteJson("/api/level-thresholds/{$highest->id}");

        $this->assertFalse(Cache::has('level_thresholds'));
    }
}