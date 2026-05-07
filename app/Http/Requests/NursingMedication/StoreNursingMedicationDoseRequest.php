<?php

declare(strict_types=1);

namespace App\Http\Requests\NursingMedication;

use Illuminate\Foundation\Http\FormRequest;

class StoreNursingMedicationDoseRequest extends FormRequest
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
            'visit_id' => ['required', 'integer', 'exists:visits,id'],
            'prescription_id' => ['required', 'integer', 'exists:prescriptions,id'],
            'prescription_item_id' => ['required', 'integer', 'exists:prescription_items,id'],
            'scheduled_for' => ['required', 'date'],
            'status' => ['nullable', 'in:pending,administered,missed,skipped'],
            'ward_id' => ['nullable', 'integer', 'exists:wards,id'],
            'schedule_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
