<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CATATAN PENTING: kolom exp_value ini SUDAH ADA di database (ditambahkan
 * manual, bukan lewat migration, saat requirement Poin & EXP dipisah jadi
 * dua sistem independen — Bagian 9 & 30). Migration ini dibuat SUSULAN
 * untuk mencatat perubahan itu secara resmi, supaya:
 * 1. `migrate:fresh` di environment baru/lokal tim lain menghasilkan
 *    struktur yang SAMA dengan database production/staging sekarang.
 * 2. Migration history konsisten dengan kondisi database yang sebenarnya.
 *
 * Kalau dijalankan di database yang SUDAH punya kolom ini (seperti
 * Supabase project sekarang), migration ini akan gagal karena kolom
 * sudah ada — makanya dibungkus cek `hasColumn()` supaya idempotent,
 * aman dijalankan di database manapun (yang sudah ada kolomnya atau
 * yang belum).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('indicator_options', 'exp_value')) {
            Schema::table('indicator_options', function (Blueprint $table) {
                $table->integer('exp_value')->default(0)->after('point_value');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('indicator_options', function (Blueprint $table) {
            $table->dropColumn('exp_value');
        });
    }
};