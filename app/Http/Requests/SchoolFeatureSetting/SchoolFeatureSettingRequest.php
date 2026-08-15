<?php

namespace App\Http\Requests\SchoolFeatureSetting;

use Illuminate\Foundation\Http\FormRequest;

class SchoolFeatureSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi lewat SchoolFeatureSettingPolicy di controller.
    }

    public function rules(): array
    {
        return [
            'ranking_class_enabled' => ['sometimes', 'boolean'],
            'ranking_cohort_enabled' => ['sometimes', 'boolean'],
        ];
    }
}