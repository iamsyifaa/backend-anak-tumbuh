<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dipicu setiap kali token reset password diterbitkan (self-service ATAU
 * admin-triggered). Listener pengirim notifikasi (email/SMS) ada di luar
 * scope AUTH-003 — didesain sebagai event supaya tim lain tinggal menambah
 * listener tanpa menyentuh AccountSecurityController/UserController.
 *
 * $token adalah PLAINTEXT sekali-pakai (bukan hash) — hanya boleh dipakai di
 * listener notifikasi, TIDAK PERNAH di-log dan TIDAK PERNAH dikembalikan lewat
 * response API.
 */
class PasswordResetTokenIssued
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly string $token,
    ) {}
}
