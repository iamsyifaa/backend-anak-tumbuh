<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PasswordResetService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class AdminPasswordResetController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly PasswordResetService $passwordResetService)
    {
    }

    /**
     * POST /api/users/{user}/force-reset-password
     * Super Admin/Kepala Sekolah "membantu administrasi akun tanpa mengetahui
     * atau menyimpan password plaintext" (Requirement Inti). Endpoint ini TIDAK
     * PERNAH mengembalikan password — hanya menerbitkan token reset yang
     * diteruskan lewat event PasswordResetTokenIssued (listener notifikasi
     * di luar scope AUTH-003), dan memaksa target harus ganti password di
     * login berikutnya lewat must_change_password.
     */
    public function forceResetPassword(Request $request, User $user)
    {
        $this->authorize('resetPassword', $user);

        $currentUser = $request->user();

        // 1. Ditolak (403) jika user mencoba meriset password-nya sendiri via endpoint admin
        if ($currentUser->id === $user->id) {
            abort(403, 'Tidak dapat meriset password sendiri melalui endpoint admin.');
        }

        // 2. Ditolak (403) jika Super Admin mencoba meriset sesama Super Admin
        if ($currentUser->isSuperAdmin() && $user->isSuperAdmin()) {
            abort(403, 'Super admin tidak dapat meriset password milik super admin lain.');
        }

        $this->passwordResetService->issueToken($user, issuedBy: $currentUser);

        $user->forceFill(['must_change_password' => true])->save();

        // Sesi aktif target langsung dicabut — akun dianggap perlu diverifikasi ulang.
        $user->tokens()->delete();

        return $this->success(null, 'Reset password telah dipicu. Token dikirim ke pemilik akun.');
    }
}