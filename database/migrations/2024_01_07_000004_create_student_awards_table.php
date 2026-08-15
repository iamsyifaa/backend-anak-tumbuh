<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('award_id')->constrained('awards')->cascadeOnDelete();
            $table->timestamp('awarded_at');
            $table->unsignedBigInteger('certificate_id')->nullable(); // FK ditambahkan MASTER-007/certificates.

            $table->unique(['student_profile_id', 'award_id', 'awarded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_awards');
    }
};