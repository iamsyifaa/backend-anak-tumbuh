<?php

namespace App\Http\Requests\Submission;

use Illuminate\Foundation\Http\FormRequest;

class SubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi lewat SubmissionPolicy di controller.
    }

    public function rules(): array
    {
        return [
            'activity_date' => ['required', 'date'],
        ];
    }
}