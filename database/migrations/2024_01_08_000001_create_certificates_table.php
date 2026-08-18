<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * certificates — baseline: 02_Database_Structure_v2_0. Dibuat di sini
     * (SEC-008) minimal, karena acceptance criteria SEC-008 eksplisit
     * menyebut "certificate" sebagai salah satu resource yang harus di-harden
     * dari akses siswa lain. certificate_templates (MASTER-007, Anggota B)
     * TIDAK dibuat di sini — hanya FK placeholder.
     */
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('award_id')->constrained('awards')->cascadeOnDelete();
            $table->unsignedBigInteger('template_id')->nullable(); // FK ke certificate_templates, ditambahkan MASTER-007.
            $table->string('file_path')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
