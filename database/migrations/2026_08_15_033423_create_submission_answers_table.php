<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_submission_id')->constrained('activity_submissions')->cascadeOnDelete();
            $table->foreignId('indicator_id')->constrained('habit_indicators')->cascadeOnDelete();
            $table->foreignId('indicator_option_id')->constrained('indicator_options')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            // Tidak ada updated_at — insert-only, sesuai aturan
            // "jawaban langsung terkunci setelah dikirim" (tidak diedit).

            $table->unique(['activity_submission_id', 'indicator_id']); // 1 jawaban per indikator per submission
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_answers');
    }
};