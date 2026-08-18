<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * point_configs — versi konfigurasi Poin/EXP PER SEKOLAH (baseline:
     * 02_Database_Structure_v2_0). Sama pola dengan habit_configs (AUTH-004):
     * berversi, draft → published, immutable setelah publish — supaya
     * histori point_transactions/exp_transactions siswa lama tidak pernah
     * berubah makna walau konfigurasi terbaru berbeda.
     *
     * ⚠️ Struktur RULE poin (per habit/indicator/option berapa poinnya)
     * dibuat oleh MASTER-005 (Anggota B, paralel) — biasanya tabel terpisah
     * (mis. point_rules) yang FK ke point_configs.id. Tabel itu TIDAK dibuat
     * di sini, koordinasikan dengan Anggota B sebelum dia lanjut supaya
     * tidak bikin ulang tabel point_configs ini.
     */
    public function up(): void
    {
        Schema::create('point_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->date('effective_date');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'version']);
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_configs');
    }
};
