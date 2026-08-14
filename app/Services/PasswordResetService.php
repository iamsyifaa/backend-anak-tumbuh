<?php

namespace App\Services;

use App\Models\PasswordResetToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetService
{
    private const TOKEN_TTL_MINUTES = 60;

    /**
     * Menerbitkan token reset. $issuedBy null = self-service (user minta sendiri
     * lewat "lupa password"). $issuedBy terisi = admin-triggered — TAPI admin
     * tidak pernah melihat password akhir, hanya token yang dia teruskan ke
     * pemilik akun (lewat kanal terpisah, di luar aplikasi ini/luar scope AUTH-003).
     *
     * Token lama yang belum dipakai untuk user ini otomatis di-invalidate,
     * supaya tidak ada token reset ganda yang valid bersamaan.
     */
    public function issueToken(User $user, ?User $issuedBy = null): string
    {
        return DB::transaction(function () use ($user, $issuedBy) {
            PasswordResetToken::where('user_id', $user->id)
                ->whereNull('used_at')
                ->update(['used_at' => now()]); // invalidate token lama

            $plainToken = Str::random(64);

            PasswordResetToken::create([
                'user_id' => $user->id,
                'token' => Hash::make($plainToken),
                'issued_by' => $issuedBy?->id,
                'expires_at' => now()->addMinutes(self::TOKEN_TTL_MINUTES),
            ]);

            return $plainToken;
        });
    }

    /**
     * Menukar token dengan password baru. Setelah dipakai, SEMUA token akses
     * (Sanctum) milik user ini dicabut — memaksa re-login di semua device
     * (mitigasi: kalau reset dipicu karena akun dicurigai bocor).
     */
    public function resetWithToken(User $user, string $plainToken, string $newPassword): bool
    {
        $record = PasswordResetToken::where('user_id', $user->id)
            ->whereNull('used_at')
            ->latest('id')
            ->first();

        if (! $record || ! $record->isValid() || ! Hash::check($plainToken, $record->token)) {
            return false;
        }

        DB::transaction(function () use ($user, $newPassword, $record) {
            $user->forceFill([
                'password' => Hash::make($newPassword),
                'must_change_password' => false,
            ])->save();

            $record->update(['used_at' => now()]);

            $user->tokens()->delete(); // revoke semua sesi Sanctum aktif.
        });

        return true;
    }
}