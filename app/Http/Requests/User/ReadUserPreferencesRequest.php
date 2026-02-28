<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Class ReadUserPreferencesRequest
 *
 * Form request for reading a user's UI/UX preferences.
 * Covers: theme_mode, ui_density, timezone, locale.
 */
class ReadUserPreferencesRequest extends FormRequest
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
        return [];
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
            'message' => 'You are not authorized to view preferences.',
            'errors'  => ['authorization' => ['Unauthorized action.']],
            'data'    => null,
        ], 403));
    }
}
