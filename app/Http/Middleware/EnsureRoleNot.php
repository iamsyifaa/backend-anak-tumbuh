<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route::middleware('role.not:siswa') — penolakan EKSPLISIT di level route,
 * bukan hanya mengandalkan Policy/Gate. Dipakai khusus untuk aturan yang
 * sifatnya "role ini TIDAK BOLEH punya endpoint ini sama sekali" (bukan soal
 * scope), seperti: "Siswa tidak dapat melihat atau mengubah password internal."
 */
class EnsureRoleNot
{
    public function handle(Request $request, Closure $next, string ...$blockedRoles): Response
    {
        if (in_array($request->user()?->role, $blockedRoles, true)) {
            abort(403, 'Role ini tidak memiliki akses ke endpoint ini.');
        }

        return $next($request);
    }
}