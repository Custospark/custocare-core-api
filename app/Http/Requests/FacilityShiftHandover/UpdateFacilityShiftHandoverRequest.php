<?php

declare(strict_types=1);

namespace App\Http\Requests\FacilityShiftHandover;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFacilityShiftHandoverRequest extends FormRequest
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
            'ward_id' => ['sometimes', 'nullable', 'integer', 'exists:wards,id'],
            'shift_date' => ['sometimes', 'date'],
            'shift_slot' => ['sometimes', 'string', 'max:32'],
            'shift_label' => ['sometimes', 'nullable', 'string', 'max:80'],
            'outgoing_summary' => ['sometimes', 'string', 'max:10000'],
            'pending_tasks_highlight' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'incidents_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'equipment_issues' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'staffing_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'handed_over_by_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'handed_over_at' => ['sometimes', 'nullable', 'date'],
            'received_by_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'acknowledged_at' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'in:draft,submitted,acknowledged'],
        ];
    }
}
