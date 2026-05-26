<?php

declare(strict_types=1);

namespace App\Http\Requests\MessageContact;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserMessageContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'display_name' => 'required|string|max:150',
            'email'        => 'nullable|email|max:255',
            'phone'        => 'nullable|string|max:30',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $email = trim((string) $this->input('email', ''));
            $phone = trim((string) $this->input('phone', ''));
            if ($email === '' && $phone === '') {
                $validator->errors()->add('contact', 'Provide at least an email or a phone number.');
            }
        });
    }
}
