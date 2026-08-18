<?php

namespace App\Services\AnswerEngine;

use App\Models\IndicatorCondition;
use App\Models\IndicatorOption;

class AnswerValidationService
{
    /**
     * Validasi satu set jawaban yang diajukan siswa, SEBELUM disimpan.
     *
     * @param  array<int,int>  $answers  [indicator_id => indicator_option_id]
     * @return array<int,string> [indicator_id => pesan error] — kosong berarti semua valid
     */
    public function validate(array $answers): array
    {
        $errors = [];

        foreach ($answers as $indicatorId => $optionId) {
            $option = IndicatorOption::find($optionId);

            // Integritas dasar: opsi yang dikirim harus benar-benar milik
            // indikator ini. Mencegah payload dipaksa (kirim option_id dari
            // indikator lain, mis. buat curi point_value lebih besar).
            if (! $option || (int) $option->indicator_id !== (int) $indicatorId) {
                $errors[$indicatorId] = 'Opsi jawaban tidak valid untuk indikator ini.';

                continue;
            }

            $conditions = IndicatorCondition::where('indicator_id', $indicatorId)->get();

            if ($conditions->isEmpty()) {
                continue; // indikator tanpa syarat, selalu boleh dijawab
            }

            $conditionMet = $conditions->contains(function (IndicatorCondition $condition) use ($answers) {
                $parentOptionId = $answers[$condition->parent_indicator_id] ?? null;

                if ($parentOptionId === null) {
                    return false; // indikator induk belum dijawab sama sekali di payload ini
                }

                $parentOption = IndicatorOption::find($parentOptionId);

                return $parentOption && $parentOption->value === $condition->required_option_value;
            });

            if (! $conditionMet) {
                // Ini kasus "forged answer": siswa (atau payload manual)
                // mengirim jawaban untuk indikator yang seharusnya tidak
                // muncul, karena syarat kondisinya tidak terpenuhi.
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
