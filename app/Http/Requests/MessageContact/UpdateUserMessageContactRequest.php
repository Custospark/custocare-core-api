<?php

declare(strict_types=1);

namespace App\Http\Requests\MessageContact;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserMessageContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'display_name' => 'sometimes|required|string|max:150',
            'email'        => 'nullable|email|max:255',
            'phone'        => 'nullable|string|max:30',
        ];
    }
}
