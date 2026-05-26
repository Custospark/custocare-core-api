<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate checked at controller level
    }

    public function rules(): array
    {
        return [
            'plan_id'       => 'required|integer|exists:plans,id',
            'billing_cycle' => 'nullable|string|in:monthly,yearly',
            'notes'         => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'plan_id.exists' => 'The selected subscription plan does not exist.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(new JsonResponse([
            'success' => false,
            'message' => 'Validation failed.',
            'errors'  => $validator->errors()->toArray(),
            'data'    => null,
        ], 422));
    }
}
