<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;

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

    public function messages(): array
    {
        return [
            'user_id.required' => 'The user ID is required.',
            'user_id.integer' => 'The user ID must be an integer.',
            'user_id.exists' => 'The specified user does not exist.',
            'channel.in' => 'The channel must be email, sms, or both.',
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
                'message' => 'You are not authorized to resend verification.'
            ], JsonResponse::HTTP_FORBIDDEN)
        );
    }

    protected function prepareForValidation(): void
    {
        // Merge route parameters if they exist and not already in request
        if ($this->route('user_id') && !$this->has('user_id')) {
            $this->merge([
                'user_id' => $this->route('user_id'),
            ]);
        }

        // Clean up channel input
        if ($this->has('channel')) {
            $this->merge([
                'channel' => strtolower(trim($this->channel)),
            ]);
        }
    }

    /**
     * Get validated data with proper type casting.
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();
        
        // Cast user_id to int since service expects int
        if (isset($validated['user_id'])) {
            $validated['user_id'] = (int) $validated['user_id'];
        }
        
        // Ensure channel is string or null
        if (isset($validated['channel']) && $validated['channel'] === null) {
            unset($validated['channel']);
        }
        
        return $key ? ($validated[$key] ?? $default) : $validated;
    }

  
}