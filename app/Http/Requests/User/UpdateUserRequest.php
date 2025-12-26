<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $user = User::find($this->route('user'));
        return $this->user()->can('update', $user);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->route('user');

        return [
            'national_id_country_code' => 'sometimes|string|size:3',
            'email' => [
                'sometimes',
                'email',
                Rule::unique('users', 'email_hash')->ignore($userId)->whereNull('deleted_at')
            ],
            'phone' => 'nullable|string|max:20',
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'title' => 'nullable|string|max:50',
            'display_name' => 'nullable|string|max:100',
            'dob' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'data_residency_region' => 'sometimes|string|in:EU,US,APAC,MEA,SA',
            'allowed_processing_regions' => 'nullable|array',
            'allowed_processing_regions.*' => 'string|in:EU,US,APAC,MEA,SA',
            'metadata' => 'nullable|array',
            'identity_state' => 'sometimes|in:pending,verified,suspended,archived',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => strtolower($this->email)]);
        }
    }
}