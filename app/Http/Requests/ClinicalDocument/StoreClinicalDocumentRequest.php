<?php

namespace App\Http\Requests\ClinicalDocument;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use App\Models\ClinicalDocument;
use Illuminate\Support\Facades\Auth;

class StoreClinicalDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled by policy
        return $this->user()->can('create', ClinicalDocument::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Required fields
            'patient_id' => 'required|integer|exists:patients,id',
            'facility_id' => 'required|integer|exists:facilities,id',
            'document_type' => 'required|string|in:' . implode(',', ClinicalDocument::getValidDocumentTypes()),
            'document_name' => 'required|string|max:300',
            
            // File field (handled separately)
            'document_file' => 'required|file|max:51200', // 50MB max
            
            // Optional fields
            'visit_id' => 'nullable|integer|exists:visits,id',
            'document_description' => 'nullable|string',
            'document_date' => 'nullable|date',
            'authored_by_staff_id' => 'nullable|integer|exists:staff,id',
            'external_author' => 'nullable|string|max:200',
            'metadata' => 'nullable|array',
            'uploaded_by_staff_id' => 'nullable|integer|exists:staff,id',
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
            'patient_id.required' => 'Patient ID is required',
            'patient_id.exists' => 'The selected patient does not exist',
            'facility_id.required' => 'Facility ID is required',
            'facility_id.exists' => 'The selected facility does not exist',
            'document_type.required' => 'Document type is required',
            'document_type.in' => 'Invalid document type selected',
            'document_name.required' => 'Document name is required',
            'document_file.required' => 'Document file is required',
            'document_file.file' => 'The uploaded file is not valid',
            'document_file.max' => 'File size must not exceed 50MB',
            'visit_id.exists' => 'The selected visit does not exist',
            'authored_by_staff_id.exists' => 'The selected author staff does not exist',
            'uploaded_by_staff_id.exists' => 'The selected uploader staff does not exist',
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
            'message' => 'You are not authorized to create clinical documents',
        ], JsonResponse::HTTP_FORBIDDEN);

        throw new HttpResponseException($response);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure uploaded_by_staff_id is set to current user if not provided
        if (!$this->has('uploaded_by_staff_id') && auth::check()) {
            $this->merge([
                'uploaded_by_staff_id' => auth::id(),
            ]);
        }

        // Convert metadata to array if it's a JSON string
        if ($this->has('metadata') && is_string($this->metadata)) {
            $this->merge([
                'metadata' => json_decode($this->metadata, true),
            ]);
        }
    }
}