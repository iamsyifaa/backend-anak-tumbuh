<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentImportUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi role (super_admin/kepala_sekolah) dicek via Policy
        // terpisah saat SEC-xxx dikerjakan Anggota A. Sementara true
        // supaya tidak memblokir development; TODO: ganti ke Gate/Policy.
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls,csv',
                'max:5120', // 5MB
            ],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'File harus berformat Excel (.xlsx/.xls) atau CSV.',
            'file.max' => 'Ukuran file maksimal 5MB.',
        ];
    }
}
