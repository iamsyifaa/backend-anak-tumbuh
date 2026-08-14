<?php

namespace App\Http\Requests\PointConfig;

use Illuminate\Foundation\Http\FormRequest;

class PointConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi lewat PointConfigPolicy di controller.
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'effective_date' => ['required', 'date'],
        ];
    }
}