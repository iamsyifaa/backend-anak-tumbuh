<?php

namespace App\Http\Controllers;

use App\Http\Requests\PointConfig\PointConfigRequest;
use App\Models\PointConfig;
use App\Models\School;
use App\Services\AuditLogService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class PointConfigController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AuditLogService $auditLog) {}

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

        // Proteksi Imutabilitas: Tolak 403 jika status bukan draft, terlepas dari Gate SuperAdmin
        abort_if(! $this->isDraft($pointConfig), 403, 'Konfigurasi yang sudah dipublish bersifat immutable.');

        $this->authorize('update', [$school, $pointConfig]);

        $before = $pointConfig->only(['version', 'effective_date']);

        $pointConfig->update($request->validated());

        $this->auditLog->record($request->user(), 'point_config.updated', $pointConfig, [
            'before' => $before,
            'after' => $pointConfig->only(['version', 'effective_date']),
        ]);

        return $this->success($pointConfig, 'Draft konfigurasi poin berhasil diperbarui.');
    }

    public function publish(Request $request, School $school, PointConfig $pointConfig)
    {
        $this->ensureBelongsToSchool($school, $pointConfig);

        // Proteksi Imutabilitas
        abort_if(! $this->isDraft($pointConfig), 403, 'Konfigurasi yang sudah dipublish tidak dapat dipublish ulang.');

        $this->authorize('publish', [$school, $pointConfig]);

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

        // Proteksi Imutabilitas
        abort_if(! $this->isDraft($pointConfig), 403, 'Konfigurasi yang sudah dipublish tidak dapat dihapus.');

        $this->authorize('delete', [$school, $pointConfig]);

        $snapshot = $pointConfig->only(['version', 'effective_date', 'status']);
        $pointConfigId = $pointConfig->id;

        $pointConfig->delete();

        $deletedEntity = (new PointConfig)->forceFill(['id' => $pointConfigId]);
        $this->auditLog->record($request->user(), 'point_config.deleted', $deletedEntity, $snapshot);

        return $this->success(null, 'Draft konfigurasi poin berhasil dihapus.');
    }

    private function ensureBelongsToSchool(School $school, PointConfig $pointConfig): void
    {
        abort_if($pointConfig->school_id !== $school->id, 404);
    }

    private function isDraft(PointConfig $config): bool
    {
        if (isset($config->status)) {
            return strtolower((string) $config->status) === 'draft';
        }

        if (isset($config->is_published)) {
            return ! (bool) $config->is_published;
        }

        if (array_key_exists('published_at', $config->getAttributes())) {
            return is_null($config->published_at);
        }

        return true;
    }
}
