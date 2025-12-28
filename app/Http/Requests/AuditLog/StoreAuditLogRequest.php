<?php

namespace App\Http\Requests\AuditLog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreAuditLogRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Audit logs are typically created by the system, not directly by users
        // This endpoint should be protected and only accessible by authorized services
        return $this->user() && $this->user()->hasAnyRole(['system_admin', 'super_admin']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operation' => 'required|in:create,read,update,delete,access,export,print,share,consent_change,authentication,authorization_failure',
            'entity_type' => 'required|string|max:100',
            'entity_id' => 'nullable|integer',
            'entity_identifier' => 'nullable|string|max:200',
            'previous_values' => 'nullable|array',
            'new_values' => 'nullable|array',
            'changed_fields' => 'nullable|array',
            'performed_by_type' => 'required|in:staff,patient,system,external_api,scheduled_job',
            'performed_by_id' => 'nullable|integer',
            'performed_by_identifier' => 'nullable|string|max:200',
            'performed_by_role' => 'nullable|string|max:100',
            'request_id' => 'required|string|max:100',
            'session_id' => 'nullable|string|max:100',
            'user_ip' => 'nullable|ip',
            'user_agent' => 'nullable|string|max:512',
            'geolocation' => 'nullable|string|max:100',
            'compliance_reason' => 'required|in:treatment,payment,healthcare_operations,billing,audit,research,legal_request,patient_request,emergency_access,break_glass',
            'legal_hold_flag' => 'nullable|boolean',
            'justification' => 'nullable|string',
            'facility_id' => 'nullable|integer',
            'department_id' => 'nullable|integer',
            'patient_id' => 'nullable|integer',
            'phi_accessed' => 'nullable|boolean',
            'phi_fields_accessed' => 'nullable|array',
            'result' => 'required|in:success,failure,partial,denied',
            'failure_reason' => 'nullable|string',
            'error_code' => 'nullable|string|max:50',
            'operation_duration_ms' => 'nullable|integer|min:0',
            'metadata' => 'nullable|array',
            'created_at' => 'nullable|date',
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
            'operation.required' => 'Operation type is required.',
            'operation.in' => 'Invalid operation type.',
            'entity_type.required' => 'Entity type is required.',
            'entity_type.max' => 'Entity type must not exceed 100 characters.',
            'performed_by_type.required' => 'Performer type is required.',
            'performed_by_type.in' => 'Invalid performer type.',
            'request_id.required' => 'Request ID is required for distributed tracing.',
            'compliance_reason.required' => 'Compliance reason is required.',
            'compliance_reason.in' => 'Invalid compliance reason.',
            'result.required' => 'Operation result is required.',
            'result.in' => 'Invalid operation result.',
            'user_ip.ip' => 'Invalid IP address format.',
            'justification.required_if' => 'Justification is required for break glass access.',
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
                'message' => 'You are not authorized to create audit logs.',
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

        if ($this->has('phi_accessed')) {
            $this->merge([
                'phi_accessed' => filter_var($this->phi_accessed, FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        // Sanitize string inputs
        $this->merge([
            'entity_identifier' => $this->entity_identifier ? strip_tags($this->entity_identifier) : null,
            'performed_by_identifier' => $this->performed_by_identifier ? strip_tags($this->performed_by_identifier) : null,
            'performed_by_role' => $this->performed_by_role ? strip_tags($this->performed_by_role) : null,
            'justification' => $this->justification ? strip_tags($this->justification) : null,
            'failure_reason' => $this->failure_reason ? strip_tags($this->failure_reason) : null,
            'geolocation' => $this->geolocation ? strip_tags($this->geolocation) : null,
        ]);
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'operation' => 'operation type',
            'entity_type' => 'entity type',
            'entity_id' => 'entity ID',
            'entity_identifier' => 'entity identifier',
            'performed_by_type' => 'performer type',
            'performed_by_id' => 'performer ID',
            'performed_by_identifier' => 'performer identifier',
            'request_id' => 'request ID',
            'compliance_reason' => 'compliance reason',
            'phi_accessed' => 'PHI accessed',
            'phi_fields_accessed' => 'PHI fields accessed',
            'operation_duration_ms' => 'operation duration',
        ];
    }

    /**
     * Get the validated data from the request.
     * Override to add default values.
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();

        // Set default values for optional fields
        $defaults = [
            'legal_hold_flag' => false,
            'phi_accessed' => false,
        ];

        foreach ($defaults as $field => $value) {
            if (!isset($validated[$field])) {
                $validated[$field] = $value;
            }
        }

        // Generate audit UUID if not provided
        if (!isset($validated['audit_uuid'])) {
            $validated['audit_uuid'] = \Illuminate\Support\Str::uuid()->toString();
        }

        // Set created_at to current time if not provided
        if (!isset($validated['created_at'])) {
            $validated['created_at'] = now();
        }

        // Add request context if available
        if (!isset($validated['user_ip']) && $this->ip()) {
            $validated['user_ip'] = $this->ip();
        }

        if (!isset($validated['user_agent']) && $this->userAgent()) {
            $validated['user_agent'] = $this->userAgent();
        }

        if (!isset($validated['session_id']) && $this->session()->getId()) {
            $validated['session_id'] = $this->session()->getId();
        }

        return $key ? ($validated[$key] ?? $default) : $validated;
    }
}