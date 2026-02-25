<?php
// app/Http/Requests/Auth/ResendVerificationRequest.php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResendVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:users,id',
            'channel' => 'sometimes|in:email,sms,both',
        ];
    }
}