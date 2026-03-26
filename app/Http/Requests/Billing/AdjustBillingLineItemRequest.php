<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

class AdjustBillingLineItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'in:increase,decrease,remove'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'Billing adjustment action is required.',
            'action.in' => 'Action must be increase, decrease, or remove.',
            'quantity.numeric' => 'Quantity must be a valid numeric value.',
            'quantity.min' => 'Quantity cannot be negative.',
            'reason.max' => 'Reason cannot exceed 1000 characters.',
        ];
    }
}
