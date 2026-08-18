<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * school_feature_settings — ⚠️ TABEL INI TIDAK ADA di 02_Database_Structure_v2_0
     * secara eksplisit, saya buat sendiri untuk mewadahi "Ranking kelas dapat
     * diaktifkan/nonaktifkan oleh sekolah... Ranking angkatan... dapat
     * diaktifkan/nonaktifkan" (Requirement Bagian 14). Task Board SEC-007
     * menyebut "school feature settings" di kolom Data tanpa nama tabel pasti.
     * TANDAI ke tim: kalau ternyata sudah ada tabel serupa dari task lain
     * (mis. kolom langsung di tabel schools), migration ini perlu direvisi/
     * digabung supaya tidak ada 2 tempat penyimpanan pengaturan yang sama.
     */
    public function up(): void
    {
        Schema::create('school_feature_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->unique()->constrained('schools')->cascadeOnDelete();
            $table->boolean('ranking_class_enabled')->default(false);
            $table->boolean('ranking_cohort_enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_feature_settings');
    }
};
