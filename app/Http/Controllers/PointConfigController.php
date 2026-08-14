<?php

namespace App\Http\Controllers;

use App\Http\Requests\PointConfig\PointConfigRequest;
use App\Models\PointConfig;
use App\Models\School;
use App\Services\AuditLogService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * SEC-005 — scope tugas ini: Policy + AUDIT untuk konfigurasi Poin, bukan
 * fitur lengkap aturan poin per habit/indicator/option (itu MASTER-005,
 * Anggota B, paralel hari yang sama). Controller ini SENGAJA minimal,
 * sama seperti HabitConfigController di AUTH-004 — cukup untuk
 * membuktikan Policy + audit trail tegak di level API.
 *
 * SETIAP mutasi (create/update/publish/delete) WAJIB tercatat di audit_logs
 * — ini bukan opsional, karena "Poin memengaruhi histori; perubahan harus
 * dapat diaudit" adalah acceptance criteria eksplisit SEC-005.
 */
class PointConfigController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function index(Request $request, School $school)
    {
        $this->authorize('viewAny', [PointConfig::class, $school]);

        return $this->success($school->pointConfigs()->orderByDesc('version')->get());
    }

    public function store(PointConfigRequest $request, School $school)
    {
        $this->authorize('create', [PointConfig::class, $school]);

        $config = $school->pointConfigs()->create($request->validated() + ['status' => 'draft']);

        $this->auditLog->record($request->user(), 'point_config.created', $config, [
            'version' => $config->version,
            'effective_date' => $config->effective_date->toDateString(),
        ]);

        return $this->success($config, 'Draft konfigurasi poin berhasil dibuat.', 201);
    }

    public function update(PointConfigRequest $request, School $school, PointConfig $pointConfig)
    {
        $this->ensureBelongsToSchool($school, $pointConfig);
        $this->authorize('update', $pointConfig);

        $before = $pointConfig->only(['version', 'effective_date']);

        $pointConfig->update($request->validated());

        $this->auditLog->record($request->user(), 'point_config.updated', $pointConfig, [
            'before' => $before,
            'after' => $pointConfig->only(['version', 'effective_date']),
        ]);

        return $this->success($pointConfig, 'Draft konfigurasi poin berhasil diperbarui.');
    }

    /**
     * POST /api/schools/{school}/point-configs/{pointConfig}/publish
     * Setelah publish, config immutable — versi lama tidak pernah berubah,
     * histori point_transactions yang merujuk versi ini tetap valid selamanya.
     */
    public function publish(Request $request, School $school, PointConfig $pointConfig)
    {
        $this->ensureBelongsToSchool($school, $pointConfig);
        $this->authorize('publish', $pointConfig);

        $pointConfig->update([
            'status' => 'published',
            'published_at' => now(),
            'published_by' => $request->user()->id,
        ]);

        $this->auditLog->record($request->user(), 'point_config.published', $pointConfig, [
            'version' => $pointConfig->version,
            'effective_date' => $pointConfig->effective_date->toDateString(),
        ]);

        return $this->success($pointConfig, 'Konfigurasi poin berhasil dipublish.');
    }

    public function destroy(Request $request, School $school, PointConfig $pointConfig)
    {
        $this->ensureBelongsToSchool($school, $pointConfig);
        $this->authorize('delete', $pointConfig);

        $snapshot = $pointConfig->only(['version', 'effective_date', 'status']);
        $pointConfigId = $pointConfig->id;

        $pointConfig->delete();

        // Dicatat SETELAH delete tapi pakai snapshot id/data lama — baris audit
        // tetap merujuk entity_id yang sudah tidak ada di point_configs (itu
        // wajar untuk audit trail: menyimpan JEJAK, bukan referensi hidup).
        $deletedEntity = (new PointConfig())->forceFill(['id' => $pointConfigId]);
        $this->auditLog->record($request->user(), 'point_config.deleted', $deletedEntity, $snapshot);

        return $this->success(null, 'Draft konfigurasi poin berhasil dihapus.');
    }

    private function ensureBelongsToSchool(School $school, PointConfig $pointConfig): void
    {
        abort_if($pointConfig->school_id !== $school->id, 404);
    }
}