<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * badges — master achievement digital (GLOBAL, tidak ada school_id).
 *
 * Sesuai requirement:
 * "Badge diberikan ketika siswa mencapai target pencapaian tertentu.
 *  Badge bukan penghargaan berdasarkan streak.
 *  Jenis dan target badge dapat dikembangkan dan dikonfigurasi."
 *
 * `target_type` + `target_value` dibuat generic (bukan hardcode
 * "total_points" doang) supaya "jenis & target badge dapat
 * dikembangkan" tanpa migration baru tiap ada jenis target baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('badges')) {
            Schema::create('badges', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('icon_path')->nullable();

                // Jenis target yang didukung saat ini: 'total_points', 'total_exp'.
                // Nilai lain bisa ditambah di masa depan tanpa migration baru,
                // cukup tambah case baru di BadgeEvaluationService.
                $table->string('target_type');
                $table->unsignedInteger('target_value');

                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('badges');
    }
};
