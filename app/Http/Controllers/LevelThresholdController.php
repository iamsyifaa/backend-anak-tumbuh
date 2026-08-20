<?php

namespace App\Http\Controllers;

use App\Models\LevelThreshold;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class LevelThresholdController extends Controller
{
    /**
     * GET /level-thresholds
     * Daftar semua level threshold, urut dari level terkecil.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', LevelThreshold::class);

        $thresholds = LevelThreshold::orderBy('level')->get();

        return response()->json([
            'data' => $thresholds,
        ]);
    }

    /**
     * POST /level-thresholds
     * Tambah level baru (biasanya level tertinggi + 1 — lihat catatan
     * di destroy() soal kenapa urutan level sebaiknya tetap rapat).
     */
    public function store(Request $request)
    {
        $this->authorize('create', LevelThreshold::class);

        $validated = $request->validate([
            'level' => ['required', 'integer', 'min:1', 'unique:level_thresholds,level'],
            'required_exp' => ['required', 'integer', 'min:0'],
        ]);

        $threshold = LevelThreshold::create($validated);

        Cache::forget('level_thresholds');

        return response()->json([
            'message' => 'Level threshold berhasil dibuat.',
            'data' => $threshold,
        ], 201);
    }

    /**
     * PUT/PATCH /level-thresholds/{levelThreshold}
     * Update required_exp (dan/atau level) untuk satu baris.
     */
    public function update(Request $request, LevelThreshold $levelThreshold)
    {
        $this->authorize('update', LevelThreshold::class);

        $validated = $request->validate([
            'level' => [
                'sometimes', 'required', 'integer', 'min:1',
                Rule::unique('level_thresholds', 'level')->ignore($levelThreshold->id),
            ],
            'required_exp' => ['sometimes', 'required', 'integer', 'min:0'],
        ]);

        $levelThreshold->update($validated);

        Cache::forget('level_thresholds');

        return response()->json([
            'message' => 'Level threshold berhasil diperbarui.',
            'data' => $levelThreshold->fresh(),
        ]);
    }

    /**
     * DELETE /level-thresholds/{levelThreshold}
     *
     * CATATAN DESAIN — belum diputuskan, tolong konfirmasi ke tim/diri
     * sendiri sebelum dipakai di production:
     * Menghapus level di TENGAH urutan (misal level 5 dari 1..10) akan
     * membuat lompatan (level 4 -> langsung 6) di LevelService, karena
     * LevelService kemungkinan menentukan level siswa dengan mengurutkan
     * required_exp lalu mengambil baris tertinggi yang <= total EXP siswa.
     * Itu sendiri tidak akan error, tapi urutan levelnya jadi tidak rapat
     * (1,2,3,4,6,7...) yang mungkin membingungkan secara UX/laporan.
     *
     * Guard di bawah ini membatasi delete HANYA untuk level tertinggi,
     * supaya urutan tetap rapat. Hapus guard ini kalau memang mau izinkan
     * hapus level manapun.
     */
    public function destroy(LevelThreshold $levelThreshold)
    {
        $this->authorize('delete', LevelThreshold::class);

        $highestLevel = LevelThreshold::max('level');

        if ($levelThreshold->level !== $highestLevel) {
            return response()->json([
                'message' => 'Hanya level tertinggi yang boleh dihapus, supaya urutan level tetap rapat.',
            ], 422);
        }

        $levelThreshold->delete();

        Cache::forget('level_thresholds');

        return response()->json([
            'message' => 'Level threshold berhasil dihapus.',
        ]);
    }
}