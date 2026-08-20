<?php
// app/Http/Requests/Report/FilterHabitInitiativeReportRequest.php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class FilterHabitInitiativeReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // scope ditegakkan via Policy di controller
    }

    public function rules(): array
    {
        return [
            'habit_id'      => ['required', 'integer', 'exists:habits,id'],
            'initiatives'   => ['nullable', 'array'],
            'initiatives.*' => ['string', 'in:sadar_sendiri,disuruh'],
            'rombel_id'     => ['required_without:school_id', 'integer', 'exists:rombels,id'],
            'school_id'     => ['required_without:rombel_id', 'integer', 'exists:schools,id'],
            'start_date'    => ['required', 'date'],
            'end_date'      => ['required', 'date', 'after_or_equal:start_date'],
        ];
    }
}

