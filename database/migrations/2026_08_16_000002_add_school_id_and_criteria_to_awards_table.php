<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FIX: kolom `school_id` dan `criteria` sudah dipakai di $fillable
 * Award.php (Anggota A, SEC-007 — award jadi PER SEKOLAH, bukan
 * global lagi), tapi migration create_awards_table tidak pernah
 * diupdate ikut menambah kolomnya.
 *
 * `school_id` dibuat NULLABLE (bukan NOT NULL) supaya:
 * 1. Data yang sudah ada (award global lama) tidak pecah.
 * 2. Konsisten dengan pola `users.school_id` (nullable = scope Super
 *    Admin/global) yang sudah dipakai tim.
 *
 * `criteria` (JSON) untuk syarat pemberian award yang berbasis
 * kebiasaan/periode, BUKAN Poin/Level — sesuai requirement eksplisit
 * MASTER-007: "award not based on point/level."
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('awards', function (Blueprint $table) {
            if (! Schema::hasColumn('awards', 'school_id')) {
                $table->foreignId('school_id')->nullable()->after('id')->constrained('schools')->nullOnDelete();
            }
            if (! Schema::hasColumn('awards', 'criteria')) {
                $table->json('criteria')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('awards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('school_id');
            $table->dropColumn('criteria');
        });
    }
};
