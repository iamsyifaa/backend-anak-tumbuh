<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Konsolidasi: tiap user yang punya >1 row (akibat bug lama
        //    key by month/year), simpan HANYA row dengan last_active_date
        //    paling baru. Row lain dihapus.
        $userIds = DB::table('student_streaks')->distinct()->pluck('user_id');

        $keepIds = [];
        foreach ($userIds as $userId) {
            $latest = DB::table('student_streaks')
                ->where('user_id', $userId)
                ->orderByDesc('last_active_date')
                ->orderByDesc('id')
                ->first();

            if ($latest) {
                $keepIds[] = $latest->id;
            }
        }

        if (! empty($keepIds)) {
            DB::table('student_streaks')->whereNotIn('id', $keepIds)->delete();
        }

        // 2) Drop unique constraint lama (month+year jadi bagian key)
        Schema::table('student_streaks', function (Blueprint $table) {
            $table->dropUnique('student_streaks_user_id_month_year_unique');
        });

        // 3) Unique constraint baru: satu row per user, titik.
        Schema::table('student_streaks', function (Blueprint $table) {
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('student_streaks', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->unique(['user_id', 'month', 'year']);
        });
    }
};