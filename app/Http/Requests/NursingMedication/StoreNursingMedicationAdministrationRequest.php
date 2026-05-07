<?php

declare(strict_types=1);

namespace App\Http\Requests\NursingMedication;

use Illuminate\Foundation\Http\FormRequest;

class StoreNursingMedicationAdministrationRequest extends FormRequest
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
            'prescription_item_id' => ['required', 'integer', 'exists:prescription_items,id'],
            'nursing_medication_dose_id' => ['nullable', 'integer', 'exists:nursing_medication_doses,id'],
            'administered_at' => ['required', 'date'],
            'outcome' => ['required', 'in:given,partial,refused,held,omitted'],
            'quantity_given' => ['nullable', 'numeric', 'min:0'],
            'quantity_unit' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'refusal_or_omission_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
