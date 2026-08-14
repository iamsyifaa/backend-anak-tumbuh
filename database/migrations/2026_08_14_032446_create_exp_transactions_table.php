<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exp_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->integer('amount');

            $table->string('source_type');
            $table->unsignedBigInteger('source_id');

            $table->date('period_date');

            $table->timestamp('created_at')->useCurrent();
            // Sama seperti point_transactions — insert-only, tidak ada updated_at.

            $table->index(['user_id', 'period_date']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exp_transactions');
    }
};