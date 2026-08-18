<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSchoolReportExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // otorisasi scope sekolah dicek di controller via Policy
    }

    public function rules(): array
    {
        return [
            'format' => ['required', 'in:xlsx,pdf'],
            'days' => ['sometimes', 'integer', 'min:1', 'max:90'], // untuk trend, default 30
        ];
    }
}
