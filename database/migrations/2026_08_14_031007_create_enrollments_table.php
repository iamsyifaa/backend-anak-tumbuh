<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * enrollments — riwayat penempatan siswa per tahun ajaran & rombel.
 * Satu baris = satu periode penempatan. TIDAK di-update/timpa saat
 * siswa pindah kelas/naik tahun ajaran — dibuat baris baru, baris lama
 * tetap ada sebagai histori (requirement: "enrollment menyimpan histori").
 *
 * ⚠️ BLOCKER SEMENTARA: tabel rombel/class_groups belum dibuat oleh tim
 * (dikonfirmasi Anggota A, per 14 Agustus 2026). `rombel_id` dibuat
 * sebagai unsignedBigInteger NULLABLE TANPA foreign key constraint
 * dulu, supaya:
 *   1. Development tidak sepenuhnya berhenti menunggu tabel rombel.
 *   2. Data yang sudah masuk tidak reject saat constraint ditambahkan.
 *
 * TODO WAJIB begitu tabel rombel jadi (lihat migration susulan
 * "add_rombel_foreign_key_to_enrollments_table"):
 *   $table->foreign('rombel_id')->references('id')->on('<nama_tabel_rombel>')
 *         ->nullOnDelete();
 *
 * `academic_year_id` SUDAH constrained ke `academic_years` (Anggota A,
 * ORG-001) karena tabel itu sudah pasti ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();

            // Soft-reference sementara — lihat catatan blocker di atas.
            $table->unsignedBigInteger('rombel_id')->nullable();

            $table->enum('status', ['active', 'ended'])->default('active');
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->string('reason')->nullable(); // e.g. "naik kelas", "pindah rombel", "lulus"
            $table->timestamps();

            $table->index(['student_profile_id', 'status']);
            $table->index('rombel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};