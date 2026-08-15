<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * certificate_templates — master template sertifikat. GLOBAL (bisa
 * dipakai lintas sekolah), sesuai requirement "sertifikat dapat
 * memakai beberapa template" — jadi 1 template bisa dipakai berkali-
 * kali untuk award yang berbeda, bukan 1 template per award.
 *
 * `layout_config` (JSON) menyimpan definisi tata letak (posisi teks
 * nama siswa, nama award, tanggal, dst di atas gambar template) —
 * dibuat generic supaya template baru bisa ditambah tanpa migration/
 * kode baru, murni data konfigurasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('background_image_path')->nullable();
            $table->json('layout_config')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_templates');
    }
};
