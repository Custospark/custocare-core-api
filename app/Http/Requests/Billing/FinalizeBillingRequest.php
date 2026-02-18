<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;

/**
 * Finalize Billing Request
 *
 * Validates and authorizes billing finalization requests
 */
class FinalizeBillingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Check if user has permission to finalize billing
        // return $this->user()->can('finalize', \App\Models\BillingCycle::class);
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'visit_id' => 'required|integer|exists:visits,id',
            'patient_id' => 'required|integer|exists:patients,id',
            
            // Charge items (line items)
            'charge_items' => 'required|array|min:1',
            'charge_items.*.service_key' => 'nullable|string', // Changed from required to nullable
            'charge_items.*.service.id' => 'required|integer',
            'charge_items.*.service.code' => 'required|string|max:50',
            'charge_items.*.service.name' => 'required|string|max:500',
            'charge_items.*.service.unitPrice' => 'required|numeric|min:0',
            'charge_items.*.service.category' => 'required|string|max:100',
            'charge_items.*.quantity' => 'required|numeric|min:1|max:9999',
            'charge_items.*.totalAmount' => 'required|numeric|min:0',
            
            // Discount
            'discount.type' => 'required|in:percentage,fixed',
            'discount.value' => 'required|numeric|min:0',
            'discount.reason' => 'nullable|string|max:200',
            
            // Taxes
            'taxes' => 'required|array',
            'taxes.*.name' => 'required|string|max:100',
            'taxes.*.rate' => 'required|numeric|min:0|max:100',
            'taxes.*.amount' => 'required|numeric|min:0',
            
            // Payment methods
            'payment_methods' => 'required|array|min:1|max:3',
            'payment_methods.*.type' => 'required|in:cash,card,insurance,mobile,mixed',
            'payment_methods.*.amount' => 'required|numeric|min:0',
            'payment_methods.*.reference' => 'nullable|string|max:100',
            'payment_methods.*.details' => 'nullable|string|max:500',
            
            // Financial summary
            'billing_data.subtotal' => 'required|numeric|min:0',
            'billing_data.discountAmount' => 'required|numeric|min:0',
            'billing_data.taxableAmount' => 'required|numeric|min:0',
            'billing_data.taxTotal' => 'required|numeric|min:0',
            'billing_data.grandTotal' => 'required|numeric|min:0',
            'billing_data.totalPaid' => 'required|numeric|min:0',
            'billing_data.balance' => 'required|numeric|min:0|max:0.01',
            
            // Additional data
            'additional_notes' => 'nullable|string|max:2000',
            'status' => 'required|in:draft,ready,settled',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'visit_id.required' => 'Visit ID is required.',
            'visit_id.exists' => 'The selected visit does not exist.',
            'patient_id.required' => 'Patient ID is required.',
            'patient_id.exists' => 'The selected patient does not exist.',
            
            'charge_items.required' => 'At least one charge item is required.',
            'charge_items.min' => 'At least one charge item must be provided.',
            'charge_items.*.service_key.nullable' => 'Service key can be null or a string.', // Added new message
            'charge_items.*.quantity.required' => 'Quantity is required for each charge item.',
            'charge_items.*.quantity.min' => 'Quantity must be at least 1.',
            'charge_items.*.quantity.max' => 'Quantity cannot exceed 9999.',
            
            'discount.type.required' => 'Discount type is required.',
            'discount.type.in' => 'Discount type must be either percentage or fixed.',
            'discount.value.required' => 'Discount value is required.',
            'discount.value.min' => 'Discount value cannot be negative.',
            
            'taxes.required' => 'Tax information is required.',
            'payment_methods.required' => 'At least one payment method is required.',
            'payment_methods.min' => 'At least one payment method must be provided.',
            'payment_methods.max' => 'Maximum 3 payment methods are allowed.',
            'payment_methods.*.type.required' => 'Payment method type is required.',
            'payment_methods.*.type.in' => 'Invalid payment method type.',
            'payment_methods.*.amount.required' => 'Payment amount is required.',
            'payment_methods.*.amount.min' => 'Payment amount cannot be negative.',
            
            'billing_data.balance.max' => 'Balance must be zero to finalize payment.',
            'status.required' => 'Billing status is required.',
            'status.in' => 'Invalid billing status.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @return void
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        $response = new JsonResponse([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 422);

        throw new HttpResponseException($response);
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @return void
     *
     * @throws HttpResponseException
     */
    protected function failedAuthorization(): void
    {
        $response = new JsonResponse([
            'success' => false,
            'message' => 'You are not authorized to finalize billing.',
        ], 403);

        throw new HttpResponseException($response);
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Ensure JSON fields are decoded if they come as strings
        $this->merge([
            'charge_items' => is_string($this->charge_items) 
                ? json_decode($this->charge_items, true) 
                : $this->charge_items,
            'discount' => is_string($this->discount) 
                ? json_decode($this->discount, true) 
                : $this->discount,
            'taxes' => is_string($this->taxes) 
                ? json_decode($this->taxes, true) 
                : $this->taxes,
            'payment_methods' => is_string($this->payment_methods) 
                ? json_decode($this->payment_methods, true) 
                : $this->payment_methods,
            'billing_data' => is_string($this->billing_data) 
                ? json_decode($this->billing_data, true) 
                : $this->billing_data,
        ]);
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'charge_items.*.service_key' => 'service key',
            'charge_items.*.service.id' => 'service ID',
            'charge_items.*.service.code' => 'service code',
            'charge_items.*.service.name' => 'service name',
            'charge_items.*.service.unitPrice' => 'unit price',
            'charge_items.*.service.category' => 'service category',
            'charge_items.*.quantity' => 'quantity',
            'charge_items.*.totalAmount' => 'total amount',
            'discount.type' => 'discount type',
            'discount.value' => 'discount value',
            'discount.reason' => 'discount reason',
            'payment_methods.*.type' => 'payment type',
            'payment_methods.*.amount' => 'payment amount',
            'billing_data.subtotal' => 'subtotal',
            'billing_data.grandTotal' => 'grand total',
            'billing_data.balance' => 'balance',
        ];
    }
}