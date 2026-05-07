<?php

declare(strict_types=1);

namespace App\Http\Requests\FacilityTask;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFacilityTaskRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'category' => ['sometimes', 'in:patient_care,ward_ops,medication,documentation,clinical_escalation,other'],
            'priority' => ['sometimes', 'in:low,normal,high,urgent'],
            'status' => ['sometimes', 'in:pending,in_progress,completed,cancelled'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'assigned_to_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'ward_id' => ['sometimes', 'nullable', 'integer', 'exists:wards,id'],
            'visit_uuid' => ['sometimes', 'nullable', 'uuid', 'exists:visits,visit_uuid'],
            'cancellation_reason' => ['sometimes', 'nullable', 'string', 'max:500'],
            'completion_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
