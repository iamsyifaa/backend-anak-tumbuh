<?php

namespace App\Services\AnswerEngine;

use App\Models\IndicatorCondition;
use App\Models\IndicatorOption;

class AnswerValidationService
{
    /**
<<<<<<< HEAD
     * Validasi satu set jawaban yang diajukan siswa, SEBELUM disimpan.
     *
     * @param  array<int,int>  $answers  [indicator_id => indicator_option_id]
     * @return array<int,string> [indicator_id => pesan error] — kosong berarti semua valid
=======
     * @param array<int,int> $answers  [indicator_id => indicator_option_id]
     * @return array<int,string>  [indicator_id => pesan error] — kosong berarti semua valid
>>>>>>> cb098fc05fe2e5fe3c595c5bf9149ab3ac01a6a0
     */
    public function validate(array $answers): array
    {
        $errors = [];
        $indicatorIds = array_keys($answers);

        // PERFORMANCE FIX: dulu IndicatorCondition di-query PER INDIKATOR di
        // dalam loop (N query untuk N jawaban). Sekarang 1 query untuk semua
        // indikator sekaligus, dikelompokkan di memori.
        $allConditions = IndicatorCondition::whereIn('indicator_id', $indicatorIds)
            ->get()
            ->groupBy('indicator_id');

        // Sama, semua opsi yang relevan diambil sekali (opsi yang dijawab +
        // opsi milik induk conditional) — bukan find() satu-satu di dalam loop.
        $optionIds = array_values($answers);
        $allOptions = IndicatorOption::whereIn('id', $optionIds)->get()->keyBy('id');

        foreach ($answers as $indicatorId => $optionId) {
            $option = $allOptions->get($optionId);

            if (! $option || (int) $option->indicator_id !== (int) $indicatorId) {
                $errors[$indicatorId] = 'Opsi jawaban tidak valid untuk indikator ini.';

                continue;
            }

            $conditions = $allConditions->get($indicatorId, collect());

            if ($conditions->isEmpty()) {
                continue;
            }

            $conditionMet = $conditions->contains(function (IndicatorCondition $condition) use ($answers, $allOptions) {
                $parentOptionId = $answers[$condition->parent_indicator_id] ?? null;

                if ($parentOptionId === null) {
                    return false;
                }

                // Opsi induk mungkin TIDAK ada di $allOptions (karena tadinya
                // cuma load opsi yang DIJAWAB) — fallback query tunggal kalau
                // perlu, tapi cek cache dulu untuk hindari query berulang.
                $parentOption = $allOptions->get($parentOptionId) ?? IndicatorOption::find($parentOptionId);

                return $parentOption && $parentOption->value === $condition->required_option_value;
            });

            if (! $conditionMet) {
                $errors[$indicatorId] = 'Indikator ini tidak berlaku karena syarat kondisinya tidak terpenuhi.';
            }
        }

        return $errors;
    }

    public function isValid(array $answers): bool
    {
        return empty($this->validate($answers));
    }
}
