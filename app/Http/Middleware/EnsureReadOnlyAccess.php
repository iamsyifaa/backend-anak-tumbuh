<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SEC-010 — Defense in depth untuk permukaan dashboard/laporan Kepala
 * Sekolah: bahkan kalau di masa depan ada developer (termasuk MASTER-010,
 * Anggota B) yang TIDAK SENGAJA mendaftarkan route POST/PUT/PATCH/DELETE di
 * bawah grup dashboard/report, middleware ini menolaknya di level HTTP
 * method SEBELUM sempat masuk ke controller manapun — tidak bergantung
 * SEMATA pada disiplin developer untuk selalu pasang Policy yang benar.
 *
 * Pakai: Route::middleware('read-only')->group(fn () => ...);
 */
class EnsureReadOnlyAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe()) { // GET/HEAD = safe; POST/PUT/PATCH/DELETE = ditolak.
            abort(403, 'Permukaan ini read-only — mutasi tidak diizinkan.');
        }

        return $next($request);
    }
}