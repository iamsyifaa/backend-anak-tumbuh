<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indicator_options', function (Blueprint $table) {
            $table->integer('exp_value')->default(0)->after('point_value');
        });

        // Backfill: opsi yang sudah ada diisi exp_value = point_value saat ini
        // sebagai titik awal, bukan permanen terikat — lihat penjelasan di atas.
        DB::table('indicator_options')->update([
            'exp_value' => DB::raw('point_value'),
        ]);
    }

    public function down(): void
    {
        Schema::table('indicator_options', function (Blueprint $table) {
            $table->dropColumn('exp_value');
        });
    }
};