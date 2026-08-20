<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('level_thresholds', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('level')->unique();
            $table->unsignedInteger('required_exp');
            $table->timestamps();
        });

        // Backfill: isi data awal dari LEVEL_THRESHOLDS placeholder yang sudah
        // ada di LevelService, supaya perilaku sistem tidak berubah saat
        // migration ini jalan. Super Admin bebas ubah setelahnya lewat aplikasi.
        DB::table('level_thresholds')->insert([
            ['level' => 1, 'required_exp' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['level' => 2, 'required_exp' => 100, 'created_at' => now(), 'updated_at' => now()],
            ['level' => 3, 'required_exp' => 250, 'created_at' => now(), 'updated_at' => now()],
            ['level' => 4, 'required_exp' => 450, 'created_at' => now(), 'updated_at' => now()],
            ['level' => 5, 'required_exp' => 700, 'created_at' => now(), 'updated_at' => now()],
            ['level' => 6, 'required_exp' => 1000, 'created_at' => now(), 'updated_at' => now()],
            ['level' => 7, 'required_exp' => 1350, 'created_at' => now(), 'updated_at' => now()],
            ['level' => 8, 'required_exp' => 1750, 'created_at' => now(), 'updated_at' => now()],
            ['level' => 9, 'required_exp' => 2200, 'created_at' => now(), 'updated_at' => now()],
            ['level' => 10, 'required_exp' => 2700, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('level_thresholds');
    }
};