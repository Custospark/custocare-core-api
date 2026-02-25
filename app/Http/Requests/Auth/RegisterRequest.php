<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'national_id' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('users', 'national_id_hash')->whereNull('deleted_at')
            ],
            'national_id_country_code' => 'nullable|string|size:3',
            'email' => 'required|email|unique:users,email_hash',
            'phone' => 'required|string|max:20',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'password' => 'required|string|min:8|confirmed',
            'data_residency_region' => 'nullable|string|in:EU,US,APAC,MEA,SA',
        ];
    }

    public function messages(): array
    {
        return [
            'national_id.unique' => 'This national ID is already registered.',
            'email.required' => 'The email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email is already registered.',
            'phone.required' => 'The phone number is required.',
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least :min characters.',
            'password.confirmed' => 'Password confirmation does not match.',
            'data_residency_region.in' => 'Invalid data residency region.',
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
                'message' => 'You are not authorized to register.'
            ], JsonResponse::HTTP_FORBIDDEN)
        );
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim($this->email)),
            ]);
        }

        if ($this->has('phone')) {
            $this->merge([
                'phone' => preg_replace('/[^0-9+]/', '', $this->phone),
            ]);
        }

        if ($this->has('national_id_country_code')) {
            $this->merge([
                'national_id_country_code' => strtoupper(trim($this->national_id_country_code)),
            ]);
        }
    }
}