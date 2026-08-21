<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah education_level_id ke rombels (SEC-009, Anggota A) — FK ke
     * education_levels (dibuat Anggota B, sudah di-merge ke main).
     *
     * Kenapa nullable: rombel LAMA yang sudah ada sebelum kolom ini dibuat
     * otomatis punya nilai null, supaya migration tidak gagal/error saat
     * dijalankan di database yang sudah punya data. Backfill data lama
     * (isi education_level_id untuk rombel existing) dilakukan terpisah,
     * BUKAN bagian dari migration ini — lihat catatan koordinasi di
     * TEAM_LOG.md bagian "Tugas Baru: education_level_id".
     *
     * nullOnDelete(): kalau education_level dihapus, rombel TIDAK ikut
     * terhapus — cuma referensinya jadi null. Ini konsisten dengan prinsip
     * "jangan pernah hapus data siswa/rombel gara-gara master data berubah"
     * yang sudah dipakai di banyak tempat lain (mis. published_by di
     * habit_configs/point_configs pakai nullOnDelete juga).
     */
    public function up(): void
    {
        Schema::table('rombels', function (Blueprint $table) {
            $table->foreignId('education_level_id')
                ->nullable()
                ->after('academic_year_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rombels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('education_level_id');
        });
    }
};