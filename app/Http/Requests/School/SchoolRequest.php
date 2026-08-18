<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi detail (siapa boleh apa) ada di ORG-002 (Policy).
        // Di sini hanya memastikan user login; role gate dasar tetap dicek di controller.
        return true;
    }

    public function rules(): array
    {
        $schoolId = $this->route('school')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('schools', 'code')->ignore($schoolId)],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
