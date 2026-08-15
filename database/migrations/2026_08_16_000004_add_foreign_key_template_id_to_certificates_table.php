<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FIX: pasang FK constraint certificates.template_id -> certificate_templates.id
 * yang sengaja ditunda Anggota A (SEC-008) sampai tabel certificate_templates
 * ada (dibuat di migration MASTER-007 sebelumnya).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->foreign('template_id')
                ->references('id')->on('certificate_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
        });
    }
};
