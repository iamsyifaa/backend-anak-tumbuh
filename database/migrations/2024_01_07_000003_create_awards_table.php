<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * awards — Master Penghargaan, PER SEKOLAH (baseline: DB Structure).
     * "Fokus penghargaan adalah kebiasaan dan periode... Penghargaan bukan
     * berdasarkan Poin/Level" (Requirement Bagian 13 & 22).
     */
    public function up(): void
    {
        Schema::create('awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name');
            $table->json('criteria')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('awards');
    }
};