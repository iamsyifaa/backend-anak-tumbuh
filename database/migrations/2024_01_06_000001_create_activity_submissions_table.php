<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * activity_submissions — header submission Digital harian (baseline:
     * 02_Database_Structure_v2_0). Nama tabel ini "activity_submissions" per
     * dokumen database resmi — Task Board SEC-006 menyebutnya "daily_submissions",
     * ini DISKREPANSI ANTAR DOKUMEN, sudah ditandai di TEAM_LOG untuk tim.
     *
     * ⚠️ MASTER-006 (Anggota B, paralel hari sama) akan menambah tabel
     * submission_answers (jawaban per indikator) yang FK ke sini, dan mungkin
     * menambah kolom referensi versi habit_config yang dipakai. Koordinasikan
     * sebelum override migration ini.
     *
     * Unique (student_profile_id, activity_date) — "Satu siswa hanya satu
     * submission per periode harian" (Requirement, MASTER-006 acceptance).
     * FK ke student_profiles (bukan users) — konsisten dengan konvensi yang
     * sudah dipakai di tabel enrollments milik Anggota B.
     */
    public function up(): void
    {
        Schema::create('activity_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->date('activity_date');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->enum('status', ['draft', 'submitted', 'locked'])->default('draft');
            $table->timestamps();

            $table->unique(['student_profile_id', 'activity_date']);
            $table->index(['student_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_submissions');
    }
};
