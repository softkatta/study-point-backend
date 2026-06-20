<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Accepts email or student ID (e.g. SP2024001) — sent as `email` from the client
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }
}
