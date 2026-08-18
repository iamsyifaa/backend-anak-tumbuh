<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicator_conditions', function (Blueprint $table) {
            $table->id();
            // Indikator yang kemunculannya tergantung syarat ini
            $table->foreignId('indicator_id')->constrained('habit_indicators')->cascadeOnDelete();
            // Indikator acuan/syarat
            $table->foreignId('parent_indicator_id')->constrained('habit_indicators')->cascadeOnDelete();
            // Nilai/opsi spesifik dari parent_indicator yang harus dipilih
            $table->string('required_option_value');
            $table->timestamps();

            $table->unique(['indicator_id', 'parent_indicator_id', 'required_option_value'], 'ind_cond_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicator_conditions');
    }
};
