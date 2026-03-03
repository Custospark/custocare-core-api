<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

/** Used for manual suspend / cancel admin actions. */
class ManageSubscriptionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'reason' => 'nullable|string|max:1000',
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
