<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * student_awards — histori award yang DIBERIKAN (manual) ke siswa.
 * Beda dari student_badges: dicatat `given_by` (siapa yang kasih) dan
 * `note` (alasan/catatan), karena ini keputusan manusia, bukan hasil
 * evaluasi otomatis sistem — perlu bisa dipertanggungjawabkan siapa
 * yang memberi dan kenapa.
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
        if (! Schema::hasTable('student_awards')) {
            Schema::create('student_awards', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
                $table->foreignId('award_id')->constrained()->cascadeOnDelete();
                $table->foreignId('given_by')->constrained('users')->cascadeOnDelete();
                $table->text('note')->nullable();
                $table->timestamp('given_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_awards');
    }
};
