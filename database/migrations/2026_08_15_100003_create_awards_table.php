<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * awards — master penghargaan (GLOBAL). Beda dari badge: award BISA
 * (opsional) menghasilkan sertifikat — lihat requirement:
 * "Penghargaan tertentu dapat menghasilkan sertifikat."
 *
 * Award TIDAK auto-generated seperti badge (bukan hasil evaluasi target
 * otomatis) — ini biasanya diberikan MANUAL oleh Kepala Sekolah/Wali
 * Kelas untuk pencapaian yang butuh penilaian manusia (mis. "Siswa
 * Teladan Bulan Ini"), bukan sesuatu yang bisa dihitung sistem semata.
 * Kalau asumsi ini keliru, perlu dikoordinasikan ulang dengan tim —
 * dokumen requirement tidak menjelaskan mekanisme pemberian award
 * secara eksplisit (auto vs manual), ini keputusan desain saya sendiri
 * mengikuti pola serupa yang sudah diambil Anggota A untuk hal yang
 * tidak eksplisit di dokumen (dicatat sebagai asumsi, bukan fakta).
 */
return new class extends Migration
{
    /**
     * Matikan DDL transaction agar tidak memicu 'transaction is aborted'
     * jika ada pengecekan tabel di PostgreSQL Supabase.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        if (!Schema::hasTable('awards')) {
            Schema::create('awards', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('generates_certificate')->default(false);
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('awards');
    }
};