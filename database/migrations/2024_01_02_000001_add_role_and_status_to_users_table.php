<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan kolom role & status akun ke tabel users bawaan Laravel.
 *
 * Catatan tim:
 * - Kolom `username` ditambahkan karena Kepala Sekolah, Wali Kelas, dan Super Admin
 *   login menggunakan username/password (lihat Flow Sistem §2), sedangkan `email`
 *   tetap dipertahankan sebagai field opsional (tidak semua akun butuh email).
 * - Kolom `role` bersifat FIXED ENUM sesuai Role & Permission v2.0. Jangan menambah
 *   role baru di sini tanpa change request — fitur baru ditempel ke role existing.
 * - `is_active` dipakai AUTH-003 (Anggota A) untuk menolak login akun nonaktif.
 * - Tidak menyentuh logika Sanctum/token; itu scope AUTH-001.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
            $table->enum('role', [
                'super_admin',
                'kepala_sekolah',
                'wali_kelas',
                'siswa',
            ])->after('username');
            $table->boolean('is_active')->default(true)->after('role');
        });

        // ->change() butuh package doctrine/dbal supaya bisa jalan di semua
        // driver (SQLite untuk testing, Postgres untuk production Supabase).
        // Jalankan: composer require doctrine/dbal
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'role', 'is_active']);
        });
    }
};
