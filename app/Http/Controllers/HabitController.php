<?php

namespace App\Http\Controllers;

use App\Models\Habit;
use App\Models\HabitIndicator;
use App\Models\IndicatorOption;
use App\Models\IndicatorCondition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HabitController extends Controller
{
    // GET /api/habits - Ambil daftar habit + indikator + opsi lengkap
    public function index(): JsonResponse
    {
        $habits = Habit::with(['indicators.options', 'indicators.conditions'])
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $habits,
        ]);
    }

    // POST /api/habits - Tambah habit baru
    public function store(Request $request): JsonResponse
    {
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

    // PUT /api/habits/{habit} - Update habit
    public function update(Request $request, Habit $habit): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'sometimes|required|string|unique:habits,code,' . $habit->id,
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

    // POST /api/habits/{habit}/indicators - Tambah Indikator ke Habit
    public function storeIndicator(Request $request, Habit $habit): JsonResponse
    {
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

        DB::transaction(function () use ($habit, $validated, &$indicator) {
            $indicator = $habit->indicators()->create([
                'code' => $validated['code'],
                'label' => $validated['label'],
                'is_required' => $validated['is_required'] ?? true,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            if (!empty($validated['options'])) {
                foreach ($validated['options'] as $idx => $opt) {
                    $indicator->options()->create([
                        'label' => $opt['label'],
                        'value' => $opt['value'],
                        'point_value' => $opt['point_value'],
                        'sort_order' => $idx + 1,
                    ]);
                }
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Indikator berhasil ditambahkan',
            'data' => $indicator->load('options'),
        ], 201);
    }

    // POST /api/indicators/{indicator}/conditions - Tambah Syarat Ketergantungan (Conditional)
    public function storeCondition(Request $request, HabitIndicator $indicator): JsonResponse
    {
        $validated = $request->validate([
            'parent_indicator_id' => 'required|exists:habit_indicators,id',
            'required_option_value' => 'required|string',
        ]);

        $condition = $indicator->conditions()->create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Kondisi variabel indikator berhasil disimpan',
            'data' => $condition,
        ], 201);
    }
}