<?php

namespace App\Http\Requests\Rombel;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRombelRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Defense-in-depth: cek juga di sini, bukan cuma di controller.
        return in_array($this->user()->role, [
            User::ROLE_SUPER_ADMIN,
            User::ROLE_KEPALA_SEKOLAH,
        ], true);
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'academic_year_id' => [
                'required',
                'integer',
                Rule::exists('academic_years', 'id')->where('school_id', $this->input('school_id')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'education_level_id' => [
                'nullable',
                'integer',
                Rule::exists('education_levels', 'id')->where('school_id', $this->input('school_id')),
            ],
            'homeroom_teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['sometimes', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'academic_year_id.exists' => 'Tahun ajaran tidak ditemukan atau bukan milik sekolah ini.',
            'education_level_id.exists' => 'Jenjang pendidikan tidak ditemukan atau bukan milik sekolah ini.',
        ];
    }
}
