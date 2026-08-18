<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->unsignedBigInteger('rombel_id')->nullable();
            $table->enum('status', ['active', 'ended'])->default('active');
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['student_profile_id', 'status']);
            $table->index('rombel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
