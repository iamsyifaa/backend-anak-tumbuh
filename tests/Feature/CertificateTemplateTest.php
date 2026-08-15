<?php

namespace Tests\Feature;

use App\Models\Award;
use App\Models\Badge;
use App\Models\CertificateTemplate;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateTemplateTest extends TestCase
{
    use RefreshDatabase;

    // ── Otorisasi ────────────────────────────────────────────────

    public function test_siswa_cannot_create_certificate_template(): void
    {
        $siswa = User::factory()->create(['role' => User::ROLE_SISWA]);

        $this->actingAs($siswa, 'sanctum')->postJson('/api/certificate-templates', [
            'code' => 'HACK', 'name' => 'Hack Template',
        ])->assertStatus(403);
    }

    public function test_super_admin_can_create_certificate_template(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/certificate-templates', [
            'code' => 'TPL_KLASIK',
            'name' => 'Template Klasik',
            'layout_config' => ['name_x' => 100, 'name_y' => 200],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('certificate_templates', ['code' => 'TPL_KLASIK']);
    }

    public function test_all_roles_can_view_certificate_templates(): void
    {
        CertificateTemplate::create(['code' => 'X', 'name' => 'X']);
        $siswa = User::factory()->create(['role' => User::ROLE_SISWA]);

        $this->actingAs($siswa, 'sanctum')->getJson('/api/certificate-templates')->assertOk();
    }

    public function test_super_admin_can_update_and_delete_template(): void
    {
        $template = CertificateTemplate::create(['code' => 'X', 'name' => 'X']);
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($admin, 'sanctum')->putJson("/api/certificate-templates/{$template->id}", [
            'name' => 'X Updated',
        ])->assertOk();

        $this->assertDatabaseHas('certificate_templates', ['id' => $template->id, 'name' => 'X Updated']);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/certificate-templates/{$template->id}")->assertOk();
        $this->assertDatabaseMissing('certificate_templates', ['id' => $template->id]);
    }

    // ── Badge dengan kolom criteria (fix kolom yang kelewat) ───────

    public function test_badge_can_be_created_with_criteria(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/badges', [
            'code' => 'RAJIN', 'name' => 'Rajin',
            'target_type' => 'total_points', 'target_value' => 50,
            'criteria' => ['min_consecutive_days' => 5],
        ]);

        $response->assertCreated();
        $badge = Badge::where('code', 'RAJIN')->firstOrFail();
        $this->assertSame(['min_consecutive_days' => 5], $badge->criteria);
    }

    // ── Award per-sekolah dengan criteria (fix kolom yang kelewat) ──

    public function test_award_can_be_created_scoped_to_school_with_criteria(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/awards', [
            'school_id' => $school->id,
            'code' => 'TELADAN_BULANAN',
            'name' => 'Siswa Teladan Bulanan',
            'criteria' => ['period' => 'monthly', 'basis' => 'habit_consistency'],
        ]);

        $response->assertCreated();
        $award = Award::where('code', 'TELADAN_BULANAN')->firstOrFail();
        $this->assertSame($school->id, $award->school_id);
        $this->assertSame(['period' => 'monthly', 'basis' => 'habit_consistency'], $award->criteria);
    }

    public function test_award_can_be_global_without_school_id(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/awards', [
            'code' => 'GLOBAL_AWARD', 'name' => 'Award Global',
        ]);

        $response->assertCreated();
        $award = Award::where('code', 'GLOBAL_AWARD')->firstOrFail();
        $this->assertNull($award->school_id);
    }

    public function test_super_admin_can_update_and_delete_award(): void
    {
        $award = Award::create(['code' => 'X', 'name' => 'X']);
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($admin, 'sanctum')->putJson("/api/awards/{$award->id}", [
            'name' => 'X Updated',
        ])->assertOk();

        $this->assertDatabaseHas('awards', ['id' => $award->id, 'name' => 'X Updated']);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/awards/{$award->id}")->assertOk();
        $this->assertDatabaseMissing('awards', ['id' => $award->id]);
    }
}
