<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * audit_logs — jejak audit administratif (baseline: 02_Database_Structure_v2_0).
     * APPEND-ONLY: tidak ada updated_at (baris tidak pernah diubah setelah dibuat),
     * tidak ada endpoint update/delete untuk tabel ini di manapun di aplikasi.
     * Dipakai lintas domain (bukan cuma point_config) — audit.view di permission
     * matrix (HANYA Super Admin) yang membaca tabel ini.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // contoh: 'point_config.published', 'point_config.updated'
            $table->string('entity_type'); // contoh: 'App\Models\PointConfig'
            $table->unsignedBigInteger('entity_id');
            $table->json('metadata')->nullable(); // snapshot before/after, IP, dsb.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};