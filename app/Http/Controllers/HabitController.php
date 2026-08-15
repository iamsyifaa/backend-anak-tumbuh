<?php

namespace App\Http\Controllers;

use App\Models\Habit;
use App\Models\HabitIndicator;
use App\Models\IndicatorCondition;
use App\Models\IndicatorOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * FIX (review MASTER-004): controller ini sebelumnya TIDAK punya
 * otorisasi sama sekali — siapa pun yang login (termasuk siswa) bisa
 * ubah struktur global 7 Kebiasaan. Sekarang pakai HabitPolicy yang
 * sudah dibuat Anggota A (AUTH-004) — cukup di-pasang, tidak perlu
 * bikin ulang.
 */
class HabitController extends Controller
{
    // GET /api/habits
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Habit::class);

        $habits = Habit::with(['indicators.options', 'indicators.conditions'])
            ->orderBy('sort_order')
            ->get();

        return response()->json(['status' => 'success', 'data' => $habits]);
    }

    // POST /api/habits
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Habit::class);

        $validated = $request->validate([
            'code' => 'required|string|unique:habits,code',
            'name' => 'required|string',
            'sort_order' => 'nullable|integer',
            'active' => 'nullable|boolean',
        ]);

        $habit = Habit::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Habit berhasil ditambahkan',
            'data' => $habit,
        ], 201);
    }

    // PUT /api/habits/{habit}
    public function update(Request $request, Habit $habit): JsonResponse
    {
        $this->authorize('update', $habit);

        $validated = $request->validate([
            'code' => 'sometimes|required|string|unique:habits,code,'.$habit->id,
            'name' => 'sometimes|required|string',
            'sort_order' => 'nullable|integer',
            'active' => 'nullable|boolean',
        ]);

        $habit->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Habit berhasil diperbarui',
            'data' => $habit,
        ]);
    }

    // DELETE /api/habits/{habit}
    public function destroy(Habit $habit): JsonResponse
    {
        $this->authorize('delete', $habit);

        $habit->delete();

        return response()->json(['status' => 'success', 'message' => 'Habit berhasil dihapus']);
    }

    // POST /api/habits/{habit}/indicators
    public function storeIndicator(Request $request, Habit $habit): JsonResponse
    {
        $this->authorize('update', $habit);

        $validated = $request->validate([
            'code' => 'required|string',
            'label' => 'required|string',
            'is_required' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'options' => 'nullable|array',
            'options.*.label' => 'required|string',
            'options.*.value' => 'required|string',
            'options.*.point_value' => 'required|integer',
        ]);

        $indicator = DB::transaction(function () use ($habit, $validated) {
            $indicator = $habit->indicators()->create([
                'code' => $validated['code'],
                'label' => $validated['label'],
                'is_required' => $validated['is_required'] ?? true,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            foreach ($validated['options'] ?? [] as $idx => $opt) {
                $indicator->options()->create([
                    'label' => $opt['label'],
                    'value' => $opt['value'],
                    'point_value' => $opt['point_value'],
                    'sort_order' => $idx + 1,
                ]);
            }

            return $indicator;
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Indikator berhasil ditambahkan',
            'data' => $indicator->load('options'),
        ], 201);
    }

    // PUT /api/indicators/{indicator}
    public function updateIndicator(Request $request, HabitIndicator $indicator): JsonResponse
    {
        $this->authorize('update', $indicator->habit);

        $validated = $request->validate([
            'code' => ['sometimes', 'required', 'string', Rule::unique('habit_indicators', 'code')
                ->where('habit_id', $indicator->habit_id)->ignore($indicator->id)],
            'label' => 'sometimes|required|string',
            'is_required' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'active' => 'nullable|boolean',
        ]);

        $indicator->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Indikator berhasil diperbarui',
            'data' => $indicator,
        ]);
    }

    // DELETE /api/indicators/{indicator}
    public function destroyIndicator(HabitIndicator $indicator): JsonResponse
    {
        $this->authorize('update', $indicator->habit);

        $indicator->delete();

        return response()->json(['status' => 'success', 'message' => 'Indikator berhasil dihapus']);
    }

    // PUT /api/indicator-options/{option}
    public function updateOption(Request $request, IndicatorOption $option): JsonResponse
    {
        $this->authorize('update', $option->indicator->habit);

        $validated = $request->validate([
            'label' => 'sometimes|required|string',
            'value' => 'sometimes|required|string',
            'point_value' => 'sometimes|required|integer',
            'sort_order' => 'nullable|integer',
            'active' => 'nullable|boolean',
        ]);

        $option->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Opsi berhasil diperbarui',
            'data' => $option,
        ]);
    }

    // DELETE /api/indicator-options/{option}
    public function destroyOption(IndicatorOption $option): JsonResponse
    {
        $this->authorize('update', $option->indicator->habit);

        $option->delete();

        return response()->json(['status' => 'success', 'message' => 'Opsi berhasil dihapus']);
    }

    /**
     * POST /api/indicators/{indicator}/conditions
     *
     * FIX (review): sebelumnya tidak ada validasi:
     * - self-reference (indicator = parent_indicator)
     * - circular dependency (A depend B, B depend A)
     * - parent & child harus 1 habit yang sama
     * - required_option_value harus benar-benar ada di opsi milik parent
     */
    public function storeCondition(Request $request, HabitIndicator $indicator): JsonResponse
    {
        $this->authorize('update', $indicator->habit);

        $validated = $request->validate([
            'parent_indicator_id' => ['required', 'integer', 'exists:habit_indicators,id'],
            'required_option_value' => ['required', 'string'],
        ]);

        // 1. Cegah self-reference
        if ((int) $validated['parent_indicator_id'] === $indicator->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Indikator tidak boleh bergantung pada dirinya sendiri.',
            ], 422);
        }

        $parentIndicator = HabitIndicator::findOrFail($validated['parent_indicator_id']);

        // 2. Parent & child harus 1 habit yang sama
        if ($parentIndicator->habit_id !== $indicator->habit_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Indikator acuan (parent) harus berada dalam Habit yang sama.',
            ], 422);
        }

        // 3. required_option_value harus benar-benar salah satu opsi milik parent
        $validOptionValues = $parentIndicator->options()->pluck('value')->all();
        if (! in_array($validated['required_option_value'], $validOptionValues, true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Nilai opsi tidak ditemukan pada indikator acuan.',
                'valid_values' => $validOptionValues,
            ], 422);
        }

        // 4. Cegah circular dependency (BFS dari parent, cek apakah bisa sampai ke $indicator)
        if ($this->wouldCreateCircularDependency($indicator->id, (int) $validated['parent_indicator_id'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kondisi ini akan membuat ketergantungan melingkar (circular dependency) antar indikator.',
            ], 422);
        }

        $condition = $indicator->conditions()->create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Kondisi variabel indikator berhasil disimpan',
            'data' => $condition,
        ], 201);
    }

    // DELETE /api/indicator-conditions/{condition}
    public function destroyCondition(IndicatorCondition $condition): JsonResponse
    {
        $this->authorize('update', $condition->indicator->habit);

        $condition->delete();

        return response()->json(['status' => 'success', 'message' => 'Kondisi berhasil dihapus']);
    }

    /**
     * Cek apakah menambahkan edge (childId depends on parentId) akan
     * membuat siklus. Caranya: telusuri dari parentId ke atas (ke semua
     * parent-of-parent-nya) — kalau ketemu childId di rantai itu, berarti
     * sudah ada jalur balik = akan jadi lingkaran.
     */
    private function wouldCreateCircularDependency(int $childId, int $parentId): bool
    {
        $visited = [];
        $queue = [$parentId];

        while (! empty($queue)) {
            $current = array_shift($queue);

            if ($current === $childId) {
                return true;
            }

            if (in_array($current, $visited, true)) {
                continue;
            }
            $visited[] = $current;

            $grandParentIds = IndicatorCondition::where('indicator_id', $current)
                ->pluck('parent_indicator_id')
                ->all();

            array_push($queue, ...$grandParentIds);
        }

        return false;
    }
}