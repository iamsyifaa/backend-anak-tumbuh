<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * teacher_rombel_assignments — jejak penugasan Wali Kelas ke rombel,
     * TERPISAH dari kolom rombels.homeroom_teacher_id supaya histori
     * pergantian wali kelas tetap tersimpan (mis. guru pindah/cuti).
     *
     * Requirement eksplisit: "Wali Kelas hanya dapat mengakses siswa dan
     * aktivitas dalam SATU rombel tanggung jawabnya... Guru lain tidak
     * mendapat akses hanya karena mengajar/menggantikan." Artinya scope
     * akses HARUS dari assignment resmi (status='active') di tabel ini,
     * BUKAN dari relasi "mengajar" atau "menggantikan" apapun bentuknya.
     *
     * Enforcement "satu assignment aktif per guru" dan "satu wali kelas
     * aktif per rombel" dilakukan di TeacherAssignmentService (transaksi),
     * BUKAN cuma lewat unique constraint DB — karena "aktif" perlu logic
     * (nonaktifkan yang lama dulu), sama pola dengan AcademicYearService.
     */
    public function up(): void
    {
        Schema::create('teacher_rombel_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('rombel_id')->constrained('rombels')->cascadeOnDelete();
            $table->enum('status', ['active', 'ended'])->default('active');
            $table->timestamp('assigned_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['teacher_id', 'status']);
            $table->index(['rombel_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_rombel_assignments');
    }
};
