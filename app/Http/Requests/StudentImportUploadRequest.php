<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StudentImportUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Defense-in-depth dengan middleware role.not:wali_kelas,siswa di
        // routes/api.php: import siswa cuma boleh Super Admin & Kepala
        // Sekolah. Sebelumnya method ini selalu return true dan hanya
        // mengandalkan middleware route sebagai satu-satunya lapisan —
        // sekarang dicek juga di sini supaya ada 2 lapisan proteksi.
        return in_array($this->user()->role, [
            User::ROLE_SUPER_ADMIN,
            User::ROLE_KEPALA_SEKOLAH,
        ], true);
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