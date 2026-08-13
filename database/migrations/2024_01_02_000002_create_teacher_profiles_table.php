<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * teacher_profiles menampung profil untuk role kepala_sekolah dan wali_kelas
 * (keduanya adalah "guru" di struktur sekolah pada ERD).
 *
 * Sengaja TIDAK menambahkan kolom school_id / rombel_id di sini.
 * Itu scope MASTER-001 (School Structure) hari ke-3, biar tidak
 * duluan mengunci struktur sebelum ERD school/rombel final di-migrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('full_name');
            $table->string('phone')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_profiles');
    }
};
