<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Reusable audit recorder — dipakai untuk SEMUA perubahan konfigurasi
 * administratif (bukan cuma point_config), supaya tidak ada service audit
 * terpisah-pisah per domain. Domain lain (mis. habit_config di AUTH-004
 * yang BELUM diaudit, atau award/badge config nanti) tinggal panggil ini.
 */
class AuditLogService
{
    /**
     * @param  array<string,mixed>|null  $metadata  snapshot before/after, atau data relevan lain.
     *                                               JANGAN pernah masukkan password/token di sini.
     */
    public function record(?User $actor, string $action, Model $entity, ?array $metadata = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => $actor?->id,
            'action' => $action,
            'entity_type' => get_class($entity),
            'entity_id' => $entity->getKey(),
            'metadata' => $metadata,
        ]);
    }
}