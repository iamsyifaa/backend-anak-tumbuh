<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * habits — 7 Kebiasaan Anak Indonesia Hebat. GLOBAL (tidak ada school_id),
     * berlaku sama untuk semua sekolah. Baseline: 02_Database_Structure_v2_0.
     * Data-driven — TIDAK hardcode di controller/frontend (Requirement Bagian 5).
     */
    public function up(): void
    {
        Schema::create('habits', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // contoh: BANGUN_PAGI, BERIBADAH, dst.
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habits');
    }
};