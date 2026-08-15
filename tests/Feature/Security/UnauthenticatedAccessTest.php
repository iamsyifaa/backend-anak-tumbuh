<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * TEST-001 — "Uji direct API access." Daripada menulis 1 test per endpoint
 * (rawan ada yang kelewat kalau endpoint baru ditambah tim lain), test ini
 * MENGENUMERASI semua route yang terdaftar di grup middleware 'auth:sanctum'
 * secara otomatis, lalu memastikan SEMUANYA menolak request tanpa token.
 *
 * Kalau ada route baru ditambahkan ke grup auth:sanctum di masa depan (oleh
 * siapa pun di tim), test ini OTOMATIS ikut mengujinya — tidak perlu update
 * manual daftar endpoint di sini.
 */
class UnauthenticatedAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Route yang SENGAJA dikecualikan dari pengujian generik ini karena
     * butuh setup khusus (signed URL, path dinamis dengan format berbeda)
     * — route-route ini sudah diuji SECARA SPESIFIK di file test lain
     * (lihat ReportExportSecurityTest untuk 'report-exports.download').
     */
    private const EXCLUDED_ROUTE_NAMES = [
        'report-exports.download', // butuh signature valid, diuji terpisah.
    ];

    public function test_all_sanctum_protected_get_routes_reject_unauthenticated_requests(): void
    {
        $protectedRoutes = collect(Route::getRoutes())
            ->filter(function ($route) {
                if (in_array($route->getName(), self::EXCLUDED_ROUTE_NAMES, true)) {
                    return false;
                }

                return in_array('auth:sanctum', $route->gatherMiddleware(), true)
                    && in_array('GET', $route->methods(), true)
                    && str_starts_with($route->uri(), 'api/');
            });

        $this->assertGreaterThan(
            0,
            $protectedRoutes->count(),
            'Tidak ada route ter-enumerasi — kemungkinan middleware auth:sanctum tidak terpasang sesuai ekspektasi, cek routes/api.php.'
        );

        foreach ($protectedRoutes as $route) {
            // Route dengan parameter (mis. {school}, {submission}) diisi angka
            // dummy — kita HANYA menguji lapis autentikasi (401), bukan
            // otorisasi/resolusi model, jadi 404 dari model binding pun
            // dianggap gagal test ini (harusnya 401 duluan sebelum model
            // di-resolve, karena middleware auth ada di urutan pertama).
            $uri = preg_replace('/\{[^}]+\}/', '1', $route->uri());

            $response = $this->getJson('/'.$uri);

            $this->assertSame(
                401,
                $response->getStatusCode(),
                "Route GET /{$uri} (name: {$route->getName()}) seharusnya menolak unauthenticated request dengan 401, tapi dapat {$response->getStatusCode()}."
            );
        }
    }
}