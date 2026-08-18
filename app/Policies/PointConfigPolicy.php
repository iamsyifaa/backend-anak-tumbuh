<?php

namespace App\Policies;

use App\Models\PointConfig;
use App\Models\School;
use App\Models\User;

/**
 * Policy untuk point_configs (SEC-005)
 * Super Admin & Kepala Sekolah memiliki akses manajemen, tetapi
 * konfigurasi yang sudah PUBLISHED bersifat IMMUTABLE (bahkan untuk Super Admin).
 */
class PointConfigPolicy
{
    /**
     * Intercept semua pengecekan untuk Super Admin, TETAPI tetap kunci imutabilitas.
     */
    public function before(User $user, string $ability, mixed $params): ?bool
    {
        if ($user->isSuperAdmin()) {
            // Jika ability update/delete/publish dan objeknya PointConfig yang SUDAH published, paksa jalankan method policy (jangan return true!)
            $target = is_array($params) ? ($params[1] ?? $params[0] ?? null) : $params;

            if ($target instanceof PointConfig && ! $this->isDraft($target)) {
                return null; // Teruskan ke method update/delete/publish agar melempar false (403)
            }

            return true; // Super Admin lolos untuk aksi lain atau jika statusnya masih draft
        }

        return null;
    }

    public function viewAny(User $user, School $school): bool
    {
        return $user->can('point_config.manage') && $this->inScope($user, $school);
    }

    public function view(User $user, mixed $firstParam, mixed $secondParam = null): bool
    {
        [$school, $config] = $this->resolveParams($firstParam, $secondParam);

        if (! $school) {
            return false;
        }

        return $user->can('point_config.manage') && $this->inScope($user, $school);
    }

    public function create(User $user, School $school): bool
    {
        return $user->can('point_config.manage') && $this->inScope($user, $school);
    }

    public function update(User $user, mixed $firstParam, mixed $secondParam = null): bool
    {
        [$school, $config] = $this->resolveParams($firstParam, $secondParam);

        if (! $config || ! $school) {
            return false;
        }

        // 1. Cek imutabilitas TERLEBIH DAHULU: Jika sudah published, SELALU reject (403)
        if (! $this->isDraft($config)) {
            return false;
        }

        // 2. Cek permission & scope sekolah
        return $user->can('point_config.manage') && $this->inScope($user, $school);
    }

    public function publish(User $user, mixed $firstParam, mixed $secondParam = null): bool
    {
        [$school, $config] = $this->resolveParams($firstParam, $secondParam);

        if (! $config || ! $school) {
            return false;
        }

        // Hanya draft yang boleh dipublish (Imutabilitas)
        if (! $this->isDraft($config)) {
            return false;
        }

        return $user->can('point_config.manage') && $this->inScope($user, $school);
    }

    public function delete(User $user, mixed $firstParam, mixed $secondParam = null): bool
    {
        [$school, $config] = $this->resolveParams($firstParam, $secondParam);

        if (! $config || ! $school) {
            return false;
        }

        // 1. Cek imutabilitas TERLEBIH DAHULU
        if (! $this->isDraft($config)) {
            return false;
        }

        return $user->can('point_config.manage') && $this->inScope($user, $school);
    }

    private function inScope(User $user, School $school): bool
    {
        return $user->isSuperAdmin() || $user->school_id === $school->id;
    }

    private function isDraft(PointConfig $config): bool
    {
        $status = $config->status ?? null;
        if ($status !== null) {
            return strtolower((string) $status) === 'draft';
        }

        if (isset($config->is_published)) {
            return ! (bool) $config->is_published;
        }

        if (array_key_exists('published_at', $config->getAttributes())) {
            return is_null($config->published_at);
        }

        return true;
    }

    private function resolveParams(mixed $firstParam, mixed $secondParam): array
    {
        $school = null;
        $config = null;

        if ($firstParam instanceof PointConfig) {
            $config = $firstParam;
            $school = $secondParam instanceof School ? $secondParam : $config->school;
        } elseif ($firstParam instanceof School) {
            $school = $firstParam;
            if ($secondParam instanceof PointConfig) {
                $config = $secondParam;
            }
        }

        return [$school, $config];
    }
}
