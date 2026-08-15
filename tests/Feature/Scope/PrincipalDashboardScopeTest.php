<?php

namespace Tests\Feature\Principal;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrincipalDashboardScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_kepala_sekolah_can_view_own_school_dashboard(): void
    {
        $school = School::factory()->create();
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $school->id]);

        $this->actingAs($kepsek, 'sanctum')
            ->getJson("/api/schools/{$school->id}/dashboard/overview")
            ->assertOk();
    }

    public function test_kepala_sekolah_cannot_view_other_schools_dashboard(): void
    {
        $ownSchool = School::factory()->create();
        $otherSchool = School::factory()->create();
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $ownSchool->id]);

        $this->actingAs($kepsek, 'sanctum')
            ->getJson("/api/schools/{$otherSchool->id}/dashboard/overview")
            ->assertStatus(403);
    }

    public function test_super_admin_can_view_any_school_dashboard(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/schools/{$school->id}/dashboard/overview")
            ->assertOk();
    }

    public function test_wali_kelas_cannot_view_school_dashboard(): void
    {
        $school = School::factory()->create();
        $wali = User::factory()->create(['role' => 'wali_kelas', 'school_id' => $school->id]);

        $this->actingAs($wali, 'sanctum')
            ->getJson("/api/schools/{$school->id}/dashboard/overview")
            ->assertStatus(403);
    }

    public function test_siswa_cannot_view_school_dashboard(): void
    {
        $school = School::factory()->create();
        $siswa = User::factory()->create(['role' => 'siswa', 'school_id' => $school->id]);

        $this->actingAs($siswa, 'sanctum')
            ->getJson("/api/schools/{$school->id}/dashboard/overview")
            ->assertStatus(403);
    }

    /**
     * Inti "read-only": middleware harus menolak method non-GET APAPUN
     * yang menyasar grup route dashboard — bahkan sebelum ada Policy yang
     * sempat dicek, murni berdasarkan HTTP method.
     */
    public function test_mutation_attempt_on_dashboard_route_group_is_rejected_by_method(): void
    {
        $school = School::factory()->create();
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah', 'school_id' => $school->id]);

        // overview cuma didaftarkan sebagai GET, jadi POST ke path yang sama
        // seharusnya sudah 404 dari router. Untuk membuktikan middleware itu
        // sendiri (bukan cuma "rute tidak ada"), kita test langsung via
        // instance middleware terpisah.
        $middleware = new \App\Http\Middleware\EnsureReadOnlyAccess();

        $getRequest = \Illuminate\Http\Request::create('/test', 'GET');
        $postRequest = \Illuminate\Http\Request::create('/test', 'POST');

        $passed = false;
        $middleware->handle($getRequest, function () use (&$passed) {
            $passed = true;

            return response('ok');
        });
        $this->assertTrue($passed, 'GET request seharusnya lolos middleware read-only.');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $middleware->handle($postRequest, fn () => response('should not reach here'));
    }

    public function test_dashboard_route_group_has_no_mutation_routes_registered(): void
    {
        $routes = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/schools/{school}/dashboard'));

        foreach ($routes as $route) {
            $methods = $route->methods();
            $this->assertEmpty(
                array_diff($methods, ['GET', 'HEAD']),
                "Route dashboard '{$route->uri()}' punya method non-GET: ".implode(',', $methods)
            );
        }
    }
}