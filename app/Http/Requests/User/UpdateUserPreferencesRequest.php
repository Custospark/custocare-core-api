<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Class UpdateUserPreferencesRequest
 *
 * Form request for updating a user's UI/UX preferences.
 * Covers: theme_mode, ui_density, timezone, locale.
 */
class UpdateUserPreferencesRequest extends FormRequest
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
            'theme_mode'  => 'sometimes|string|in:light,dark,system',
            'ui_density'  => 'sometimes|string|in:compact,comfortable,spacious',
            'timezone'    => 'sometimes|string|max:50|timezone',
            'locale'      => 'sometimes|string|max:10',
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
            'theme_mode.in'   => 'Theme mode must be one of: light, dark, system.',
            'ui_density.in'   => 'UI density must be one of: compact, comfortable, spacious.',
            'timezone.timezone' => 'Please provide a valid timezone identifier.',
            'locale.max'      => 'Locale code must not exceed 10 characters.',
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
            'theme_mode' => 'Theme Mode',
            'ui_density' => 'UI Density',
            'timezone'   => 'Timezone',
            'locale'     => 'Locale',
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
            'message' => 'You are not authorized to update preferences.',
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
        if ($this->has('locale') && is_string($this->locale)) {
            // Normalize locale to lowercase (e.g. en_US → en_us)
            $this->merge(['locale' => strtolower(trim($this->locale))]);
        }
    }
}
