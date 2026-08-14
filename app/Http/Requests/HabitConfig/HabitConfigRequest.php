<?php

namespace App\Http\Requests\HabitConfig;

use Illuminate\Foundation\Http\FormRequest;

class HabitConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi lewat Policy di controller (HabitConfigPolicy).
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'effective_date' => ['required', 'date'],
        ];
    }
}