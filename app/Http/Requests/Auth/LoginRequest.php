<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required|string|min:8',
            'mfa_code' => 'nullable|string|size:6',
            'remember_me' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email address is required',
            'email.email' => 'Please enter a valid email address',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 8 characters',
            'mfa_code.size' => 'MFA code must be 6 digits',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
                'requires_mfa' => false,
                'user' => null,
                'token' => null,
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY)
        );
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'You are not authorized to login.'
            ], JsonResponse::HTTP_FORBIDDEN)
        );
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim($this->email))
            ]);
        }

        if ($this->has('mfa_code')) {
            $this->merge([
                'mfa_code' => preg_replace('/[^0-9]/', '', $this->mfa_code)
            ]);
        }
    }
}