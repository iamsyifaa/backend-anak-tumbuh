<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('point_configs', function (Blueprint $table) {
            $table->unsignedInteger('initiative_bonus_points')->default(0)->after('effective_date');
        });
    }

    public function down(): void
    {
        Schema::table('point_configs', function (Blueprint $table) {
            $table->dropColumn('initiative_bonus_points');
        });
    }
};