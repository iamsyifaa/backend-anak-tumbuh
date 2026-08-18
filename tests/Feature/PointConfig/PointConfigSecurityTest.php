<?php

namespace Tests\Feature\PointConfig;

use App\Models\AuditLog;
use App\Models\PointConfig;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointConfigSecurityTest extends TestCase
{
    use RefreshDatabase;

    // ── Authorization ────────────────────────────────────────────────────

    public function test_super_admin_can_create_draft_config_for_any_school(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/schools/{$school->id}/point-configs", [
                'version' => 1,
                'effective_date' => now()->addWeek()->toDateString(),
            ])->assertCreated();
    }

    public function test_kepala_sekolah_can_create_draft_config_for_own_school(): void
    {
        $school = School::factory()->create();
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $school->id]);

        $this->actingAs($kepsek, 'sanctum')
            ->postJson("/api/schools/{$school->id}/point-configs", [
                'version' => 1,
                'effective_date' => now()->addWeek()->toDateString(),
            ])->assertCreated();
    }

    public function test_kepala_sekolah_cannot_create_config_for_other_school(): void
    {
        $ownSchool = School::factory()->create();
        $otherSchool = School::factory()->create();
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $ownSchool->id]);

        $this->actingAs($kepsek, 'sanctum')
            ->postJson("/api/schools/{$otherSchool->id}/point-configs", [
                'version' => 1,
                'effective_date' => now()->addWeek()->toDateString(),
            ])->assertStatus(403);
    }

    public function test_wali_kelas_cannot_manage_point_config(): void
    {
        $school = School::factory()->create();
        $wali = User::factory()->create(['role' => 'wali_kelas', 'school_id' => $school->id]);

        $this->actingAs($wali, 'sanctum')
            ->postJson("/api/schools/{$school->id}/point-configs", [
                'version' => 1,
                'effective_date' => now()->addWeek()->toDateString(),
            ])->assertStatus(403);
    }

    public function test_siswa_cannot_manage_point_config(): void
    {
        $school = School::factory()->create();
        $siswa = User::factory()->create(['role' => 'siswa', 'school_id' => $school->id]);

        $this->actingAs($siswa, 'sanctum')
            ->postJson("/api/schools/{$school->id}/point-configs", [
                'version' => 1,
                'effective_date' => now()->addWeek()->toDateString(),
            ])->assertStatus(403);
    }

    // ── Immutability ─────────────────────────────────────────────────────

    public function test_published_config_cannot_be_updated(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $config = PointConfig::factory()->published()->create(['school_id' => $school->id]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/schools/{$school->id}/point-configs/{$config->id}", [
                'version' => $config->version,
                'effective_date' => now()->addMonth()->toDateString(),
            ])->assertStatus(403);
    }

    public function test_published_config_cannot_be_deleted(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $config = PointConfig::factory()->published()->create(['school_id' => $school->id]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/schools/{$school->id}/point-configs/{$config->id}")
            ->assertStatus(403);
    }

    public function test_already_published_config_cannot_be_published_again(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $config = PointConfig::factory()->published()->create(['school_id' => $school->id]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/schools/{$school->id}/point-configs/{$config->id}/publish")
            ->assertStatus(403);
    }

    // ── Audit trail (inti SEC-005) ──────────────────────────────────────

    public function test_creating_config_is_audited(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/schools/{$school->id}/point-configs", [
                'version' => 1,
                'effective_date' => now()->addWeek()->toDateString(),
            ]);

        $configId = $response->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'point_config.created',
            'entity_type' => PointConfig::class,
            'entity_id' => $configId,
        ]);
    }

    public function test_updating_config_is_audited_with_before_after_snapshot(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $config = PointConfig::factory()->create(['school_id' => $school->id, 'version' => 1]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/schools/{$school->id}/point-configs/{$config->id}", [
                'version' => 2,
                'effective_date' => now()->addMonth()->toDateString(),
            ])->assertOk();

        $log = AuditLog::where('action', 'point_config.updated')
            ->where('entity_id', $config->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(1, $log->metadata['before']['version']);
        $this->assertSame(2, $log->metadata['after']['version']);
    }

    public function test_publishing_config_is_audited(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $config = PointConfig::factory()->create(['school_id' => $school->id]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/schools/{$school->id}/point-configs/{$config->id}/publish")
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'point_config.published',
            'entity_type' => PointConfig::class,
            'entity_id' => $config->id,
        ]);
    }

    public function test_deleting_config_is_audited(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $config = PointConfig::factory()->create(['school_id' => $school->id]);
        $configId = $config->id;

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/schools/{$school->id}/point-configs/{$configId}")
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'point_config.deleted',
            'entity_type' => PointConfig::class,
            'entity_id' => $configId,
        ]);
    }

    public function test_audit_log_cannot_be_updated_or_deleted(): void
    {
        $log = AuditLog::create([
            'user_id' => null,
            'action' => 'test.action',
            'entity_type' => PointConfig::class,
            'entity_id' => 1,
            'metadata' => [],
        ]);

        $this->expectException(\LogicException::class);
        $log->action = 'tampered';
        $log->save();
    }

    public function test_audit_log_delete_is_blocked(): void
    {
        $log = AuditLog::create([
            'user_id' => null,
            'action' => 'test.action',
            'entity_type' => PointConfig::class,
            'entity_id' => 1,
            'metadata' => [],
        ]);

        $this->expectException(\LogicException::class);
        $log->delete();
    }

    public function test_config_from_another_school_returns_404_not_403(): void
    {
        $school = School::factory()->create();
        $otherSchool = School::factory()->create();
        $config = PointConfig::factory()->create(['school_id' => $otherSchool->id]);
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/schools/{$school->id}/point-configs/{$config->id}/publish")
            ->assertStatus(404);
    }
}
