<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * indicator_options — pilihan jawaban per indikator (mis. "Sebelum 04.00",
     * "04.00–04.30", dst untuk indikator Jam Bangun). GLOBAL. point_value dipakai
     * scoring engine (BE-006), tapi TIDAK dihitung ulang dari sini saat histori
     * lama ditampilkan — histori pakai snapshot (lihat MASTER-006).
     */
    public function up(): void
    {
        Schema::create('indicator_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicator_id')->constrained('habit_indicators')->cascadeOnDelete();
            $table->string('label');
            $table->string('value'); // kode internal pilihan, dipakai submission_answers
            $table->integer('point_value')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicator_options');
    }
};
