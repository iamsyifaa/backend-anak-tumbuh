<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * student_profiles menampung profil dasar untuk role siswa.
 *
 * `method` (DIGITAL/MANUAL) dimasukkan di sini karena itu atribut inheren
 * siswa sesuai Requirement Inti & koreksi terbaru (Sheet Baseline & Changes):
 * "Manual tetap siswa sistem, tidak ada input/rekap jawaban buku oleh guru."
 *
 * Kolom enrollment/rombel SENGAJA tidak dimasukkan di sini — itu scope
 * MASTER-001 (Student Foundation, hari ke-3) karena butuh tabel rombels
 * yang baru dibuat Anggota A hari itu juga.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('full_name');
            $table->enum('method', ['digital', 'manual'])->default('digital');
            $table->date('birth_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
