<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * POST /api/login
     * Login via username atau email + password. Token Sanctum diterbitkan untuk role
     * Super Admin/Kepala Sekolah/Wali Kelas/Siswa (login QR siswa Digital ada di task terpisah - BE-004).
     */
    public function login(LoginRequest $request)
    {
        $login = $request->string('login')->toString();

        $user = User::query()
            ->where('username', $login)
            ->orWhere('email', $login)
            ->first();

        // Pesan error auth SELALU generik (tidak membedakan "user tidak ada" vs "password salah")
        // supaya tidak bocor informasi keberadaan akun (enumeration).
        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Username/email atau password salah.'],
            ]);
        }

        if (! $user->isActive()) {
            return $this->error('Akun tidak aktif. Hubungi administrator sekolah.', 403);
        }

        // Token diberi nama berdasarkan device/client agar bisa di-audit/revoke per-device di kemudian hari.
        $token = $user->createToken(
            name: $request->userAgent() ?? 'api-client',
            abilities: ['*'],
        )->plainTextToken;

        $user->forceFill(['last_login_at' => now()])->save();

        return $this->success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'school_id' => $user->school_id,
                'must_change_password' => $user->must_change_password,
            ],
        ], 'Login berhasil.');
    }

    /**
     * POST /api/logout
     * Mencabut token yang sedang dipakai (bukan seluruh token milik user, supaya
     * sesi di device lain tidak ikut logout tanpa diminta).
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logout berhasil.');
    }

    /**
     * GET /api/me
     * Identitas user yang sedang login beserta role & scope dasar.
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return $this->success([
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'school_id' => $user->school_id,
            'status' => $user->status,
            'must_change_password' => $user->must_change_password,
        ]);
    }
}
