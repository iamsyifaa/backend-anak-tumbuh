<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * report_exports — ⚠️ TIDAK ADA di dokumen database resmi, saya buat
     * sendiri karena "report" itu sendiri (hasil kalkulasi BE-012) tidak
     * disimpan, tapi FILE hasil export (Excel/PDF, dibuat MASTER-011) harus
     * punya jejak supaya bisa diotorisasi saat di-download — tanpa tabel
     * ini, tidak ada cara membedakan "file A boleh diunduh siapa" selain
     * menebak dari nama file (rawan predictable path / IDOR).
     *
     * scope_type + scope_id = scope laporan ini (siswa/rombel/sekolah),
     * dipakai ReportExportPolicy untuk cek otorisasi saat download —
     * BUKAN sekadar "siapa yang generate", supaya Kepala Sekolah/Wali
     * Kelas lain yang punya scope sama tetap bisa akses laporan yang sama
     * tanpa generate ulang.
     */
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->enum('scope_type', ['student', 'rombel', 'school']);
            $table->unsignedBigInteger('scope_id'); // student_profile_id / rombel_id / school_id, tergantung scope_type.
            $table->string('file_path'); // path di disk PRIVATE (bukan public), lihat ReportExportController.
            $table->string('format'); // 'xlsx' | 'pdf'
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['scope_type', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};