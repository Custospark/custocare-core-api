<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;

class VerifyEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'numeric', 
                'digits:6', 
            ],
            'user_id' => 'required|integer|exists:users,id',
            'is_token' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'The verification code is required.',
            'code.numeric' => 'The verification code must be a number.',
            'code.digits' => 'The verification code must be exactly :digits digits.',
            'user_id.required' => 'The user ID is required.',
            'user_id.exists' => 'The specified user does not exist.',
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

    protected function prepareForValidation(): void
    {
        if ($this->route('user_id') && !$this->has('user_id')) {
            $this->merge([
                'user_id' => $this->route('user_id'),
            ]);
        }

        if ($this->route('code') && !$this->has('code')) {
            $this->merge([
                'code' => $this->route('code'),
            ]);
        }
    }

    /**
     * Cast values after validation passes
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();
        
        // Cast code to int since service expects int
        if (isset($validated['code'])) {
            $validated['code'] = (int) $validated['code'];
        }
        
        return $key ? ($validated[$key] ?? $default) : $validated;
    }
}