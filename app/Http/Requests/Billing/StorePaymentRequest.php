<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Enums\Billing\PaymentMethod;
use App\Enums\Billing\PaymentType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rules\Enum;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'                => 'required|numeric|min:1',
            'currency'              => 'required|string|size:3',
            'method'                => ['required', new Enum(PaymentMethod::class)],
            'payment_type'          => ['required', new Enum(PaymentType::class)],
            'transaction_reference' => 'nullable|string|max:255',
            'receipt'               => 'required|file|mimes:jpeg,jpg,png,pdf|max:5120', // 5MB
            'receipt_notes'         => 'nullable|string|max:1000',
            'paid_at'               => 'required|date|before_or_equal:now',
            'quote_intent'          => 'nullable|string|in:first_activation,subscription,renewal,scheduled_change,upgrade_now,trial_activation',
            'target_plan_id'        => 'nullable|integer|exists:plans,id',
        ];
    }

    public function messages(): array
    {
        return [
            'paid_at.before_or_equal' => 'The payment date cannot be in the future.',
            'receipt.max'             => 'Receipt file must not exceed 5MB.',
            'receipt.mimes'           => 'Receipt must be a JPEG, PNG, or PDF file.',
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
