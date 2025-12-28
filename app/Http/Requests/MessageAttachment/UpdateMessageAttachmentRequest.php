<?php

namespace App\Http\Requests\MessageAttachment;

use App\Models\MessageAttachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class UpdateMessageAttachmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled by the controller/policy
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message_id' => 'sometimes|integer|exists:messages,id',
            'attachment_type' => [
                'sometimes',
                'string',
                'in:' . implode(',', MessageAttachment::getAttachmentTypes()),
            ],
            'file_name' => 'sometimes|string|max:255',
            'mime_type' => 'sometimes|string|max:100',
            'file_size_bytes' => 'sometimes|integer|min:1|max:10485760',
            'storage_disk' => 'sometimes|string|max:50|in:local,public,s3',
            'storage_path' => 'sometimes|string|max:512',
            'contains_phi' => 'sometimes|boolean',
            'checksum' => 'sometimes|string|size:64',
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
            'message_id.exists' => 'The selected message does not exist.',
            'attachment_type.in' => 'The selected attachment type is invalid.',
            'file_name.max' => 'The file name may not be greater than 255 characters.',
            'mime_type.max' => 'The MIME type may not be greater than 100 characters.',
            'file_size_bytes.min' => 'The file size must be at least 1 byte.',
            'file_size_bytes.max' => 'The file size may not be greater than 10MB.',
            'storage_disk.in' => 'The selected storage disk is invalid.',
            'storage_path.max' => 'The storage path may not be greater than 512 characters.',
            'checksum.size' => 'The checksum must be 64 characters (SHA256).',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'message_id' => 'message ID',
            'attachment_type' => 'attachment type',
            'file_name' => 'file name',
            'mime_type' => 'MIME type',
            'file_size_bytes' => 'file size',
            'storage_disk' => 'storage disk',
            'storage_path' => 'storage path',
            'contains_phi' => 'contains PHI',
            'checksum' => 'checksum',
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
        $response = response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422);

        throw new HttpResponseException($response);
    }
}