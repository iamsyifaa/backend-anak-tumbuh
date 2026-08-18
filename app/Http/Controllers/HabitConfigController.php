<?php

namespace App\Http\Controllers;

use App\Http\Requests\HabitConfig\HabitConfigRequest;
use App\Models\HabitConfig;
use App\Models\School;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * AUTH-004 — scope tugas ini HANYA authorization (Policy) untuk habit
 * configuration, bukan fitur lengkap manajemen konten config (memilih
 * habit/indicator/option mana yang aktif per versi, dst — itu MASTER-004,
 * dikerjakan paralel oleh Anggota B di hari yang sama).
 *
 * Controller ini SENGAJA minimal: cukup untuk membuktikan Policy tegak di
 * level API (acceptance criteria AUTH-004: "unauthorized mutation rejected
 * at API"). Anggota B kemungkinan akan MENAMBAH field/endpoint (mis. attach
 * habit/indicator/option ke versi config) di HabitConfigController ini saat
 * MASTER-004 — koordinasikan dulu sebelum override supaya authorize() call
 * yang sudah ada di sini tidak hilang.
 */
class HabitConfigController extends Controller
{
    use ApiResponse;

    public function index(Request $request, School $school)
    {
        $this->authorize('viewAny', [HabitConfig::class, $school]);

        return $this->success($school->habitConfigs()->orderByDesc('version')->get());
    }

    public function store(HabitConfigRequest $request, School $school)
    {
        $this->authorize('create', [HabitConfig::class, $school]);

        $config = $school->habitConfigs()->create($request->validated() + ['status' => 'draft']);

        return $this->success($config, 'Draft konfigurasi kebiasaan berhasil dibuat.', 201);
    }

    public function update(HabitConfigRequest $request, School $school, HabitConfig $habitConfig)
    {
        $this->ensureBelongsToSchool($school, $habitConfig);
        $this->authorize('update', $habitConfig);

        $habitConfig->update($request->validated());

        return $this->success($habitConfig, 'Draft konfigurasi kebiasaan berhasil diperbarui.');
    }

    /**
     * POST /api/schools/{school}/habit-configs/{habitConfig}/publish
     * Setelah publish, config immutable (HabitConfigPolicy::update/delete akan
     * menolak perubahan lebih lanjut pada versi ini).
     */
    public function publish(Request $request, School $school, HabitConfig $habitConfig)
    {
        $this->ensureBelongsToSchool($school, $habitConfig);
        $this->authorize('publish', $habitConfig);

        $habitConfig->update([
            'status' => 'published',
            'published_at' => now(),
            'published_by' => $request->user()->id,
        ]);

        return $this->success($habitConfig, 'Konfigurasi kebiasaan berhasil dipublish.');
    }

    public function destroy(Request $request, School $school, HabitConfig $habitConfig)
    {
        $this->ensureBelongsToSchool($school, $habitConfig);
        $this->authorize('delete', $habitConfig);

        $habitConfig->delete();

        return $this->success(null, 'Draft konfigurasi kebiasaan berhasil dihapus.');
    }

    private function ensureBelongsToSchool(School $school, HabitConfig $habitConfig): void
    {
        abort_if($habitConfig->school_id !== $school->id, 404);
    }
}
