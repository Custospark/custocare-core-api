<?php

declare(strict_types=1);

namespace App\Http\Requests\NursingMedication;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNursingMedicationDoseRequest extends FormRequest
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
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'scheduled_for' => ['sometimes', 'date'],
            'status' => ['sometimes', 'in:pending,administered,missed,skipped'],
            'ward_id' => ['nullable', 'integer', 'exists:wards,id'],
            'schedule_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
