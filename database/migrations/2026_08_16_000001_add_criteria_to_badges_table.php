<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FIX: kolom `criteria` sudah dipakai di $fillable Badge.php (ditambah
 * Anggota A saat SEC-007), tapi migration create_badges_table tidak
 * pernah diupdate ikut nambah kolomnya — insert yang menyertakan
 * `criteria` akan error "no such column". Ditambahkan di sini via
 * migration ALTER terpisah (bukan edit migration Anggota A langsung),
 * supaya tidak menimpa riwayat migration yang sudah di-push.
 *
 * `criteria` (JSON) untuk syarat pencapaian yang lebih kaya dari
 * sekadar target_type/target_value tunggal (mis. kombinasi beberapa
 * syarat) — MASTER-007: "Definitions configurable; badge not based on
 * streak."
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('badges', function (Blueprint $table) {
            if (! Schema::hasColumn('badges', 'criteria')) {
                $table->json('criteria')->nullable()->after('target_value');
            }
        });
    }

    public function down(): void
    {
        Schema::table('badges', function (Blueprint $table) {
            $table->dropColumn('criteria');
        });
    }
};
