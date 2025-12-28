<?php

namespace App\Http\Requests\AuditLog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateAuditLogRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Only allow updates to legal hold flag, not other fields
        return $this->user() && $this->user()->can('update', $this->route('audit_log'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Audit logs are immutable, only legal_hold_flag can be updated
        return [
            'legal_hold_flag' => 'required|boolean',
            'legal_hold_reason' => 'required_if:legal_hold_flag,true|string|min:10|max:500',
            'metadata' => 'nullable|array',
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
            'legal_hold_flag.required' => 'Legal hold flag is required.',
            'legal_hold_flag.boolean' => 'Legal hold flag must be true or false.',
            'legal_hold_reason.required_if' => 'Reason is required when placing under legal hold.',
            'legal_hold_reason.min' => 'Reason must be at least 10 characters.',
            'legal_hold_reason.max' => 'Reason must not exceed 500 characters.',
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
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422)
        );
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @return void
     * @throws HttpResponseException
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'You are not authorized to update this audit log.',
                'errors' => ['authorization' => 'Insufficient permissions.'],
            ], 403)
        );
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Ensure boolean fields are properly cast
        if ($this->has('legal_hold_flag')) {
            $this->merge([
                'legal_hold_flag' => filter_var($this->legal_hold_flag, FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        // Sanitize reason
        if ($this->has('legal_hold_reason')) {
            $this->merge([
                'legal_hold_reason' => strip_tags($this->legal_hold_reason),
            ]);
        }
    }

    /**
     * Get the validated data from the request.
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();

        // Only allow legal_hold_flag and metadata to be updated
        $allowedFields = ['legal_hold_flag', 'metadata'];
        
        $filtered = array_filter($validated, function ($key) use ($allowedFields) {
            return in_array($key, $allowedFields);
        }, ARRAY_FILTER_USE_KEY);

        // Add legal hold reason to metadata if provided
        if (isset($validated['legal_hold_reason'])) {
            $filtered['metadata'] = $filtered['metadata'] ?? [];
            $filtered['metadata']['legal_hold_reason'] = $validated['legal_hold_reason'];
        }

        return $key ? ($filtered[$key] ?? $default) : $filtered;
    }
}