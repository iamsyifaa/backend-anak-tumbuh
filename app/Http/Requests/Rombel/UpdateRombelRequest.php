<?php

namespace App\Http\Requests\Rombel;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRombelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, [
            User::ROLE_SUPER_ADMIN,
            User::ROLE_KEPALA_SEKOLAH,
        ], true);
    }

    public function rules(): array
    {
        // school_id SENGAJA tidak boleh diubah lewat update — pindah rombel
        // antar sekolah bukan operasi "edit", itu masalah data-integrity
        // (enrollment, assignment, dsb ikut nyangkut). Kalau perlu, itu
        // harus alur terpisah, bukan PATCH biasa.
        $rombel = $this->route('rombel');

        return [
            'academic_year_id' => [
                'sometimes',
                'integer',
                Rule::exists('academic_years', 'id')->where('school_id', $rombel->school_id),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'education_level_id' => [
                'nullable',
                'integer',
                Rule::exists('education_levels', 'id')->where('school_id', $rombel->school_id),
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
