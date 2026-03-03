<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing\Gateway;

use App\Enums\Billing\PaymentType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rules\Enum;

/**
 * Validates a request to initiate a gateway payment.
 *
 * phone_number required for push-based gateways (MTN MoMo, Airtel Money)
 * email required for redirect-based gateways (Flutterwave, PesaPal)
 * At least one of phone_number or email must be present.
 */
class InitiateGatewayPaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'subscription_id' => 'required|integer|exists:subscriptions,id',
            'payment_type'    => ['required', new Enum(PaymentType::class)],
            'amount'          => 'required|numeric|min:1',
            'currency'        => 'required|string|size:3',
            'phone_number'    => 'nullable|string|min:9|max:20',
            'email'           => 'nullable|email|max:200',
            'customer_name'   => 'nullable|string|max:200',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (empty($this->phone_number) && empty($this->email)) {
                $v->errors()->add(
                    'contact',
                    'Either phone_number (for mobile money) or email (for card/hosted gateways) is required.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'amount.min'             => 'Payment amount must be greater than zero.',
            'currency.size'          => 'Currency must be a 3-letter ISO code (e.g. UGX, USD).',
            'subscription_id.exists' => 'The referenced subscription does not exist.',
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
