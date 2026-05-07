<?php

declare(strict_types=1);

namespace App\Http\Requests\FacilityTask;

use Illuminate\Foundation\Http\FormRequest;

class StoreFacilityTaskRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'in:patient_care,ward_ops,medication,documentation,clinical_escalation,other'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'status' => ['nullable', 'in:pending,in_progress,completed,cancelled'],
            'due_at' => ['nullable', 'date'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'ward_id' => ['nullable', 'integer', 'exists:wards,id'],
            'visit_uuid' => ['nullable', 'uuid', 'exists:visits,visit_uuid'],
        ];
    }
}
