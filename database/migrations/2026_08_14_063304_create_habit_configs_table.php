<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * habit_configs — versi konfigurasi 7 Kebiasaan PER SEKOLAH (mana yang
     * diizinkan/aktif untuk sekolah tsb). Ini yang dimaksud "Kepala Sekolah
     * dapat menyesuaikan konfigurasi 7 Kebiasaan yang diizinkan" (Requirement
     * Bagian 22) — BUKAN mengubah struktur global habits/indicators/options.
     *
     * Berversi (version + effective_date) supaya histori submission lama tidak
     * berubah kalau konfigurasi baru di-publish (immutable history principle,
     * sama seperti point_configs di MASTER-005/SEC-005).
     */
    public function up(): void
    {
        Schema::create('habit_configs', function (Blueprint $table) {
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
        Schema::dropIfExists('habit_configs');
    }
};