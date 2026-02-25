<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|max:255|exists:users,email',
            'code' => 'required|string|min:6|max:64',
            'new_password' => 'required|string|min:8|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'The email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.exists' => 'No account found with this email address.',
            'code.required' => 'The verification code is required.',
            'code.min' => 'The verification code must be at least :min characters.',
            'code.max' => 'The verification code must not exceed :max characters.',
            'new_password.required' => 'The new password is required.',
            'new_password.min' => 'The password must be at least :min characters.',
            'new_password.confirmed' => 'The password confirmation does not match.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY)
        );
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'You are not authorized to reset passwords.'
            ], JsonResponse::HTTP_FORBIDDEN)
        );
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('email')) {
            $this->merge([
                'email' => $this->route('email'),
            ]);
        }

        if ($this->route('code')) {
            $this->merge([
                'code' => $this->route('code'),
            ]);
        }

        if ($this->has('code')) {
            $this->merge([
                'code' => trim(preg_replace('/\s+/', '', $this->code)),
            ]);
        }

        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim($this->email)),
            ]);
        }
    }

    public function validationData(): array
    {
        return array_merge(
            $this->all(),
            ['email' => $this->route('email')],
            ['code' => $this->route('code')]
        );
    }
}