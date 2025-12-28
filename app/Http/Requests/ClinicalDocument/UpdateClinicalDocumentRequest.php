<?php

namespace App\Http\Requests\ClinicalDocument;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use App\Models\ClinicalDocument;

class UpdateClinicalDocumentRequest extends FormRequest
{
    /**
     * The clinical document instance.
     */
    protected ?ClinicalDocument $clinicalDocument = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $this->clinicalDocument = ClinicalDocument::find($this->route('clinicalDocument'));
        
        if (!$this->clinicalDocument) {
            return false;
        }

        return $this->user()->can('update', $this->clinicalDocument);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Optional updatable fields
            'document_type' => 'sometimes|string|in:' . implode(',', ClinicalDocument::getValidDocumentTypes()),
            'document_name' => 'sometimes|string|max:300',
            'document_description' => 'nullable|string',
            'document_date' => 'nullable|date',
            'authored_by_staff_id' => 'nullable|integer|exists:staff,id',
            'external_author' => 'nullable|string|max:200',
            'status' => 'sometimes|string|in:' . implode(',', ClinicalDocument::getValidStatuses()),
            'metadata' => 'nullable|array',
            
            // Read-only fields (cannot be updated)
            'patient_id' => 'prohibited',
            'facility_id' => 'prohibited',
            'visit_id' => 'prohibited',
            'document_file' => 'prohibited', // Use separate endpoint for file updates
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
            'document_type.in' => 'Invalid document type selected',
            'document_name.max' => 'Document name must not exceed 300 characters',
            'authored_by_staff_id.exists' => 'The selected author staff does not exist',
            'status.in' => 'Invalid status selected',
            'patient_id.prohibited' => 'Patient ID cannot be updated',
            'facility_id.prohibited' => 'Facility ID cannot be updated',
            'visit_id.prohibited' => 'Visit ID cannot be updated',
            'document_file.prohibited' => 'Document file cannot be updated via this endpoint',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        $response = new JsonResponse([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);

        throw new HttpResponseException($response);
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization(): void
    {
        $response = new JsonResponse([
            'success' => false,
            'message' => 'You are not authorized to update this clinical document',
        ], JsonResponse::HTTP_FORBIDDEN);

        throw new HttpResponseException($response);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert metadata to array if it's a JSON string
        if ($this->has('metadata') && is_string($this->metadata)) {
            $this->merge([
                'metadata' => json_decode($this->metadata, true),
            ]);
        }
    }
}