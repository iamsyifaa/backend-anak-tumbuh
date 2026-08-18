<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perbaikan: kolom 'type' ditambahkan di migration
 * 2026_08_18_000001_add_type_to_report_exports_table sebagai NOT NULL
 * tanpa default, dari implementasi MASTER-011 yang TIDAK JADI dipakai.
 * Implementasi final (ReportExportService di app/Services/Export/)
 * tidak pernah mengisi kolom ini saat insert, jadi harus dibuat
 * nullable supaya tidak memblokir semua create() ReportExport.
 *
 * Drop + re-add dipakai (bukan ->change()) supaya tidak butuh paket
 * doctrine/dbal yang belum tentu terpasang di project ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_exports', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('report_exports', function (Blueprint $table) {
            $table->string('type')->nullable()->after('scope_id');
        });
    }

    public function down(): void
    {
        Schema::table('report_exports', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('report_exports', function (Blueprint $table) {
            $table->string('type')->after('scope_id');
        });
    }
};
