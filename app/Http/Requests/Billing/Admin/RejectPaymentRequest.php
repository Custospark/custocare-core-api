<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class RejectPaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:10|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A rejection reason is required so the facility can take corrective action.',
            'reason.min'      => 'Please provide a more detailed rejection reason (at least 10 characters).',
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
