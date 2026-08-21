<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('education_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('order')->default(0);
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('education_levels');
    }
};