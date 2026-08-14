<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\PasswordResetService;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\TransientToken;

class AccountSecurityController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly PasswordResetService $passwordResetService)
    {
    }

    /**
     * POST /api/account/change-password
     */
    public function changePassword(ChangePasswordRequest $request)
    {
        $user = $request->user();

        if (! Hash::check($request->string('current_password')->toString(), $user->password)) {
            return $this->error('Password saat ini salah.', 422, [
                'current_password' => ['Password saat ini salah.'],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($request->string('new_password')->toString()),
            'must_change_password' => false,
        ])->save();

        // Aman untuk unit test (Sanctum actingAs) & produksi:
        // Cabut semua token lain, sisakan sesi yang sedang dipakai jika token fisik ada.
        $currentToken = $user->currentAccessToken();
        if ($currentToken && ! ($currentToken instanceof TransientToken)) {
            $user->tokens()->where('id', '!=', $currentToken->id)->delete();
        } else {
            $user->tokens()->delete();
        }

        return $this->success(null, 'Password berhasil diubah.');
    }

    /**
     * POST /api/forgot-password
     */
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $loginInput = $request->string('login')->toString() ?: $request->string('email')->toString();

        $user = User::query()
            ->where('username', $loginInput)
            ->orWhere('email', $loginInput)
            ->first();

        if ($user && $user->isActive()) {
            $token = $this->passwordResetService->issueToken($user);
            event(new \App\Events\PasswordResetTokenIssued($user, $token));
        }

        return $this->success(null, 'Jika akun ditemukan, instruksi reset password telah dikirim.');
    }

    /**
     * POST /api/reset-password
     */
    public function resetPassword(ResetPasswordRequest $request)
    {
        $loginInput = $request->string('login')->toString() ?: $request->string('email')->toString();

        $user = User::query()
            ->where('username', $loginInput)
            ->orWhere('email', $loginInput)
            ->first();

        if (! $user) {
            return $this->error('Token tidak valid atau sudah kedaluwarsa.', 422, [
                'token' => ['Token tidak valid atau expired.'],
            ]);
        }

        $tokenInput = $request->string('token')->toString();
        $passwordInput = $request->string('new_password')->toString() ?: $request->string('password')->toString();

        $ok = $this->passwordResetService->resetWithToken(
            $user,
            $tokenInput,
            $passwordInput
        );

        if (! $ok) {
            return $this->error('Token tidak valid atau sudah kedaluwarsa.', 422, [
                'token' => ['Token tidak valid atau expired.'],
            ]);
        }

        return $this->success(null, 'Password berhasil direset. Silakan login dengan password baru.');
    }
}