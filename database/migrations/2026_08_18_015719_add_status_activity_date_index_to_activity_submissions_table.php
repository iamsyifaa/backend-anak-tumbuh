<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index tambahan (status, activity_date) — index yang sudah ada
     * (student_profile_id, status) optimal untuk query 1 siswa, tapi
     * laporan/analytics (ReportService, SchoolAnalyticsService) query
     * LINTAS BANYAK siswa sekaligus dengan filter status+tanggal
     * (WHERE status='locked' AND activity_date BETWEEN ...), tanpa
     * student_profile_id spesifik — index lama tidak optimal untuk pola ini.
     */
    public function up(): void
    {
        Schema::table('activity_submissions', function (Blueprint $table) {
            $table->index(['status', 'activity_date']);
        });
    }

    public function down(): void
    {
        Schema::table('activity_submissions', function (Blueprint $table) {
            $table->dropIndex(['status', 'activity_date']);
        });
    }
};