<?php

namespace App\Http\Requests\EducationLevel;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEducationLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // otorisasi sesungguhnya dicek via Policy di controller
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'order' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,inactive',
        ];
    }
}