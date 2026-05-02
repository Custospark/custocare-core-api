<?php

declare(strict_types=1);

namespace App\Http\Requests\Vital;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class UpdateVitalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
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
            'temperature' => 'nullable|numeric|min:25|max:45',
            'temperature_unit' => 'nullable|in:celsius,fahrenheit',
            'heart_rate' => 'nullable|numeric|min:0|max:300',
            'respiratory_rate' => 'nullable|numeric|min:0|max:100',
            'systolic_bp' => 'nullable|numeric|min:30|max:300',
            'diastolic_bp' => 'nullable|numeric|min:30|max:200',
            'bp_position' => 'nullable|in:sitting,standing,supine,lying',
            'bp_location' => 'nullable|string|max:50',
            'oxygen_saturation' => 'nullable|numeric|min:0|max:100',
            'oxygen_flow_rate' => 'nullable|integer|min:0|max:50',
            'oxygen_delivery_device' => 'nullable|string|max:100',
            'height' => 'nullable|numeric|min:10|max:300',
            'height_unit' => 'nullable|in:cm,inches',
            'weight' => 'nullable|numeric|min:0.1|max:500',
            'weight_unit' => 'nullable|in:kg,lbs',
            'pain_score' => 'nullable|numeric|min:0|max:10',
            'pain_scale_type' => 'nullable|in:numeric,faces,visual_analog',
            'pain_location' => 'nullable|string|max:200',
            'head_circumference' => 'nullable|numeric|min:20|max:100',
            'length' => 'nullable|numeric|min:20|max:150',
            'measured_at' => 'nullable|date',
            'measurement_method' => 'nullable|string|max:100',
            'device_id' => 'nullable|string|max:100',
            'consciousness_level' => 'nullable|in:alert,verbal,pain,unresponsive',
            'general_appearance' => 'nullable|string',
            'custom_fields' => 'nullable|array',
            'percentiles' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'temperature.min' => 'Temperature must be at least 25°',
            'temperature.max' => 'Temperature cannot exceed 45°',
            'heart_rate.min' => 'Heart rate cannot be negative',
            'heart_rate.max' => 'Heart rate cannot exceed 300 bpm',
            'systolic_bp.min' => 'Systolic BP must be at least 30 mmHg',
            'diastolic_bp.min' => 'Diastolic BP must be at least 30 mmHg',
            'oxygen_saturation.min' => 'Oxygen saturation must be at least 0%',
            'oxygen_saturation.max' => 'Oxygen saturation cannot exceed 100%',
            'pain_score.min' => 'Pain score must be at least 0',
            'pain_score.max' => 'Pain score cannot exceed 10',
        ];
    }

    /**
     * Handle a failed validation attempt.
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
}