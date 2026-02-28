<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Class UpdateUserSecurityRequest
 *
 * Form request for updating a user's security settings.
 * Covers: password change (with current password verification),
 *         requires_password_change flag, and mfa_enabled toggle.
 */
class UpdateUserSecurityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
         return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Password change block — all three required together
            'current_password'        => 'required_with:password|string',
            'password'                => 'sometimes|nullable|string|min:8|confirmed',
            'password_confirmation'   => 'required_with:password|string',

            // Administrative flags
            'requires_password_change' => 'sometimes|boolean',
            'mfa_enabled'              => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required_with'      => 'Current password is required when setting a new password.',
            'password.min'                         => 'New password must be at least 8 characters.',
            'password.confirmed'                   => 'Password confirmation does not match.',
            'password_confirmation.required_with'  => 'Password confirmation is required when setting a new password.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password'         => 'Current Password',
            'password'                 => 'New Password',
            'password_confirmation'    => 'Password Confirmation',
            'requires_password_change' => 'Requires Password Change',
            'mfa_enabled'              => 'MFA Enabled',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(new JsonResponse([
            'success' => false,
            'message' => 'Validation failed',
            'errors'  => $validator->errors()->toArray(),
            'data'    => null,
        ], 422));
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @throws HttpResponseException
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(new JsonResponse([
            'success' => false,
            'message' => 'You are not authorized to update security settings.',
            'errors'  => ['authorization' => ['Unauthorized action.']],
            'data'    => null,
        ], 403));
    }

    /**
     * Prepare data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        foreach (['requires_password_change', 'mfa_enabled'] as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field => filter_var($this->{$field}, FILTER_VALIDATE_BOOLEAN),
                ]);
            }
        }
    }
}
