<?php

namespace App\Http\Requests\InventoryItem;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ImportInventoryItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please choose an Excel file (.xlsx, .xls, or .csv) to upload.',
            'file.mimes' => 'Only Excel files (.xlsx, .xls, or .csv) are supported.',
            'file.max' => 'The file is too large. Maximum size is 20MB.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'The uploaded file is invalid.',
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}
