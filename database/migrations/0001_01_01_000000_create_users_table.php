<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * users
     * Akun autentikasi lintas role: super_admin, kepala_sekolah, wali_kelas, siswa.
     * Baseline: 02_Database_Structure_v2_0 (tabel users) + 01_Role_and_Permission_v2_0.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            // school_id nullable HANYA untuk Super Admin (scope global).
            // Kepala Sekolah, Wali Kelas, Siswa wajib punya school_id — divalidasi di service layer, bukan di kolom.

            $table->string('username')->unique();
            $table->string('email')->nullable()->unique();
            $table->string('password'); // password_hash, di-hash via Laravel Hash::make (bcrypt/argon2)

            $table->enum('role', ['super_admin', 'kepala_sekolah', 'wali_kelas', 'siswa']);

            $table->enum('status', ['active', 'inactive'])->default('active');
            // inactive => ditolak saat login (AUTH-001 acceptance criteria).

            $table->boolean('must_change_password')->default(false);
            // dipakai AUTH-003 (password reset flow), disiapkan di sini agar tidak perlu migration tambahan.

            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index(['role', 'status']);
            $table->index(['school_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
