<?php

namespace App\Http\Requests\AcademicYear;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Scope/policy detail: ORG-002.
    }

    public function rules(): array
    {
        $academicYearId = $this->route('academic_year')?->id;
        $schoolId = $this->route('school')?->id ?? $this->input('school_id');

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('academic_years', 'name')
                    ->where(fn ($q) => $q->where('school_id', $schoolId))
                    ->ignore($academicYearId),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}