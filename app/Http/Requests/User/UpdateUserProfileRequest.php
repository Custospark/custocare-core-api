<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Class UpdateUserProfileRequest
 *
 * Form request for updating a user's profile fields.
 * Covers: first_name, last_name, display_name, title, dob, gender,
 *         phone (plain → stored encrypted), address, and profile_photo_path.
 */
class UpdateUserProfileRequest extends FormRequest
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
            'first_name'        => 'sometimes|string|max:100',
            'last_name'         => 'sometimes|string|max:100',
            'display_name'      => 'sometimes|string|max:150',
            'title'             => 'sometimes|nullable|string|max:50',
            'dob'               => 'sometimes|nullable|date|before:today',
            'gender'            => 'sometimes|nullable|in:male,female,other',

            // Submitted as plain text; encrypted + hashed in the service layer
            'phone'             => 'sometimes|nullable|string|max:30',

            'address_line1'     => 'sometimes|nullable|string|max:200',
            'address_line2'     => 'sometimes|nullable|string|max:200',
            'city'              => 'sometimes|nullable|string|max:100',
            'state'             => 'sometimes|nullable|string|max:100',
            'country'           => 'sometimes|nullable|string|max:100',
            'postal_code'       => 'sometimes|nullable|string|max:20',

            // Path/URL to an already-uploaded photo (upload handled separately)
            'profile_photo_path' => 'sometimes|nullable|string|max:512',
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
            'dob.before'          => 'Date of birth must be a past date.',
            'gender.in'           => 'Gender must be one of: male, female, other.',
            'phone.max'           => 'Phone number must not exceed 30 characters.',
            'postal_code.max'     => 'Postal code must not exceed 20 characters.',
            'profile_photo_path.max' => 'Profile photo path must not exceed 512 characters.',
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
            'first_name'         => 'First Name',
            'last_name'          => 'Last Name',
            'display_name'       => 'Display Name',
            'title'              => 'Title',
            'dob'                => 'Date of Birth',
            'gender'             => 'Gender',
            'phone'              => 'Phone Number',
            'address_line1'      => 'Address Line 1',
            'address_line2'      => 'Address Line 2',
            'city'               => 'City',
            'state'              => 'State',
            'country'            => 'Country',
            'postal_code'        => 'Postal Code',
            'profile_photo_path' => 'Profile Photo',
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
            'message' => 'You are not authorized to update this profile.',
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
        // Trim string fields
        $stringFields = [
            'first_name', 'last_name', 'display_name',
            'title', 'phone', 'address_line1', 'address_line2',
            'city', 'state', 'country', 'postal_code',
        ];

        foreach ($stringFields as $field) {
            if ($this->has($field) && is_string($this->{$field})) {
                $this->merge([$field => trim($this->{$field})]);
            }
        }
    }
}
