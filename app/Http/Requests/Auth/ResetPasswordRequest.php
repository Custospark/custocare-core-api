<?php
// app/Http/Requests/Auth/ResetPasswordRequest.php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|max:255',
            'code' => 'required|string|min:6|max:64',
            'new_password' => 'required|string|min:8|confirmed',
            'is_token' => 'sometimes|boolean',
        ];
    }
}