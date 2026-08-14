<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * password_reset_tokens — dipakai untuk 2 alur (AUTH-003):
     * 1) Self-service "lupa password" (Guru/Kepala Sekolah, punya email).
     * 2) Admin-triggered reset (Super Admin/Kepala Sekolah membantu akun bawahannya
     *    TANPA pernah melihat/menyimpan password plaintext — hanya menerbitkan token).
     * Token disimpan ter-hash, bukan plaintext, sama seperti password.
     */
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token'); // hashed
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            // issued_by NULL = self-service; terisi = admin-triggered reset (audit trail).
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};