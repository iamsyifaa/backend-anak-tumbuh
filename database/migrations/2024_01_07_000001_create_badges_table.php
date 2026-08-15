<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * badges — Master badge, GLOBAL (baseline: 02_Database_Structure_v2_0).
     * "Badge diberikan ketika siswa mencapai target pencapaian tertentu...
     * Badge bukan penghargaan berdasarkan streak" (Requirement Bagian 12).
     * criteria disimpan JSON — configurable, TIDAK hardcode di kode
     * (MASTER-007 acceptance: "Definitions configurable").
     */
    public function up(): void
    {
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->json('criteria')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badges');
    }
};