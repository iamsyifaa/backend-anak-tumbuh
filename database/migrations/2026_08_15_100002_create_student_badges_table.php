<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * student_badges — histori badge yang sudah didapat siswa. INSERT-ONLY
 * (tidak ada updated_at) — sekali didapat, tidak pernah dicabut otomatis,
 * konsisten dengan pola audit-trail yang sudah dipakai tim di
 * point_transactions/exp_transactions.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('student_badges')) {
            Schema::create('student_badges', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
                $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
                $table->timestamp('awarded_at')->useCurrent();

                // 1 siswa cuma bisa dapat 1 badge yang sama sekali (bukan berulang)
                $table->unique(['student_profile_id', 'badge_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_badges');
    }
};