<?php

namespace App\Http\Requests\VisitEvent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class UpdateVisitEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $visitEvent = $this->route('visitEvent') ?? $this->route('visit_event');
        
        // Authorization is handled by VisitEventPolicy
        return $visitEvent && $this->user()->can('update', $visitEvent);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Visit events are immutable, but we allow metadata updates for audit purposes
        return [
            'metadata' => 'sometimes|array',
            'client_ip' => 'sometimes|ip',
            'client_user_agent' => 'sometimes|string|max:512',
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
            'metadata.array' => 'Metadata must be a valid JSON object',
            'client_ip.ip' => 'Client IP must be a valid IP address',
            'client_user_agent.max' => 'Client user agent may not be greater than 512 characters',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @return void
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors()->toArray();
        
        $response = new JsonResponse([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $errors,
            'status_code' => 422,
        ], 422);

        throw new HttpResponseException($response);
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @return void
     * @throws HttpResponseException
     */
    protected function failedAuthorization(): void
    {
        $response = new JsonResponse([
            'success' => false,
            'message' => 'You are not authorized to update this visit event',
            'status_code' => 403,
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
        // Ensure metadata is an array if it's JSON string
        if ($this->has('metadata') && is_string($this->metadata)) {
            $this->merge([
                'metadata' => json_decode($this->metadata, true),
            ]);
        }
    }
}