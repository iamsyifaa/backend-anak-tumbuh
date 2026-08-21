<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gap requirement Bagian 8: "Timezone sistem/sekolah harus konsisten."
 *
 * Sebelum ini, semua logic tanggal-sensitif (batas hari submission,
 * ranking bulanan, dsb.) pakai now() server polos. Kalau ada sekolah
 * di luar WIB (WITA/WIT) atau server jalan di UTC, batas "hari ini"/
 * "bulan ini" versi server bisa meleset dari versi sekolah.
 *
 * Kolom ini menyimpan IANA timezone identifier (contoh: 'Asia/Jakarta',
 * 'Asia/Makassar', 'Asia/Jayapura') per sekolah, supaya service yang
 * butuh (DailyPeriodService, RankingService, dst.) bisa panggil
 * now($school->timezone) alih-alih now() polos.
 *
 * Default 'Asia/Jakarta' (WIB) dipasang di level kolom supaya:
 * - Sekolah existing otomatis terisi WIB, tidak ada baris null.
 * - Sekolah baru yang belum diisi field ini eksplisit tetap dapat
 *   default yang aman (bukan timezone server/UTC yang bisa salah).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('timezone')
                ->default('Asia/Jakarta')
                ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
