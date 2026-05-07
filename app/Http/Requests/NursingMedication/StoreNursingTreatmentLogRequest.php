<?php

declare(strict_types=1);

namespace App\Http\Requests\NursingMedication;

use Illuminate\Foundation\Http\FormRequest;

class StoreNursingTreatmentLogRequest extends FormRequest
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
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'ward_id' => ['nullable', 'integer', 'exists:wards,id'],
            'performed_at' => ['required', 'date'],
            'category' => ['required', 'in:wound_care,dressing_change,physiotherapy,education,monitoring,comfort_measures,device_care,other'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
