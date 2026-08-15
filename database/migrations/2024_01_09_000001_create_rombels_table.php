<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * rombels — ⚠️ DISKREPANSI NAMA TABEL: 02_Database_Structure_v2_0 menamai
     * ini "class_groups" (id, class_id, academic_year_id, name,
     * homeroom_teacher_id, status). Saya pakai nama "rombels" karena tabel
     * `enrollments` yang SUDAH ADA di codebase (dibuat Anggota B, MASTER-001)
     * ternyata sudah pakai kolom FK "rombel_id" — mengikuti apa yang SUDAH
     * berjalan di database nyata lebih penting daripada dokumen supaya tidak
     * bentrok migration. WAJIB dikonfirmasi ke Anggota B: apakah tabel
     * "rombels"/"class_groups" ini sudah pernah mereka buat sebelumnya dengan
     * struktur berbeda — kalau iya, migration ini perlu di-drop, bukan dipakai
     * dua-duanya.
     *
     * homeroom_teacher_id TIDAK dipakai untuk enforcement "satu Wali Kelas
     * satu rombel" (itu tugas teacher_rombel_assignments, lihat migration
     * berikutnya) — kolom ini hanya cache/referensi cepat "siapa wali kelas
     * SEKARANG", sumber kebenaran tetap di tabel assignment.
     */
    public function up(): void
    {
        Schema::create('rombels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->string('name'); // contoh: "Kelas 5A"
            $table->foreignId('homeroom_teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rombels');
    }
};