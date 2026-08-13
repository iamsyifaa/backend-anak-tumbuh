<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Login endpoint is public; authorization happens inside the auth flow itself.
    }

    public function rules(): array
    {
        return [
            // login bisa berupa username ATAU email; resolusi field mana yang dipakai terjadi di service/controller.
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'Username atau email wajib diisi.',
            'password.required' => 'Password wajib diisi.',
        ];
    }
}
