<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FIX: HabitController::storeIndicator() sudah validasi & coba simpan
 * 'is_required', tapi kolomnya tidak pernah dibuat di migration asli
 * dan tidak ada di $fillable HabitIndicator — akibatnya data itu
 * silently didiemin (tidak error, tapi juga tidak pernah tersimpan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('habit_indicators', function (Blueprint $table) {
            $table->boolean('is_required')->default(true)->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('habit_indicators', function (Blueprint $table) {
            $table->dropColumn('is_required');
        });
    }
};
