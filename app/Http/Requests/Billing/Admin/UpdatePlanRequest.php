<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing\Admin;

use App\Constants\Billing\PlanFeatures;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $planId = $this->route('plan')?->id ?? $this->route('plan');

        return [
            'name'                   => "sometimes|string|max:100|unique:plans,name,{$planId}",
            'slug'                   => "sometimes|string|max:100|unique:plans,slug,{$planId}|regex:/^[a-z0-9\-]+$/",
            'description'            => 'nullable|string|max:2000',
            'price_usd'              => 'sometimes|numeric|min:0',
            'price_ugx'              => 'sometimes|numeric|min:0',
            'onboarding_fee_usd'     => 'nullable|numeric|min:0',
            'onboarding_fee_ugx'     => 'nullable|numeric|min:0',
            'billing_cycle'          => 'sometimes|in:monthly',
            'trial_days'             => 'nullable|integer|min:0|max:90',
            'features'               => 'nullable|array',
            'features.*'             => 'boolean',
            'max_staff'              => 'nullable|integer|min:1',
            'max_departments'        => 'nullable|integer|min:1',
            'max_patients_per_month' => 'nullable|integer|min:1',
            'sort_order'             => 'nullable|integer|min:0',
            'is_popular'             => 'nullable|boolean',
            'is_active'              => 'nullable|boolean',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $features = $this->input('features');
            if (!is_array($features)) {
                return;
            }

            $unknown = array_diff(array_keys($features), PlanFeatures::ALL);
            if (!empty($unknown)) {
                $validator->errors()->add(
                    'features',
                    'Unsupported feature keys: ' . implode(', ', $unknown)
                );
            }
        });
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
