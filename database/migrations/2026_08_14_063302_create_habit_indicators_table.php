<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * habit_indicators — indikator/aspek per kebiasaan (mis. "Jam Bangun" untuk
     * Bangun Pagi). GLOBAL, mengikuti habits. Setiap kebiasaan WAJIB punya
     * indikator Inisiatif (Sadar sendiri/Disuruh) — lihat Requirement Bagian 5.
     */
    public function up(): void
    {
        Schema::create('habit_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('habit_id')->constrained('habits')->cascadeOnDelete();
            $table->string('code'); // contoh: JAM_BANGUN, INISIATIF
            $table->string('label');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['habit_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habit_indicators');
    }
};
