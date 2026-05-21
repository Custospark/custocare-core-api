<?php

declare(strict_types=1);

namespace App\Http\Requests\FacilityShiftHandover;

use Illuminate\Foundation\Http\FormRequest;

class StoreFacilityShiftHandoverRequest extends FormRequest
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
            'ward_id' => ['nullable', 'integer', 'exists:wards,id'],
            'shift_date' => ['nullable', 'date'],
            'shift_slot' => ['nullable', 'string', 'max:32'],
            'shift_label' => ['nullable', 'string', 'max:80'],
            'outgoing_summary' => ['required_without:summary', 'string', 'max:10000'],
            'summary' => ['required_without:outgoing_summary', 'string', 'max:10000'],
            'pending_tasks_highlight' => ['nullable', 'string', 'max:5000'],
            'incidents_notes' => ['nullable', 'string', 'max:5000'],
            'equipment_issues' => ['nullable', 'string', 'max:5000'],
            'staffing_notes' => ['nullable', 'string', 'max:5000'],
            'handed_over_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'handed_over_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'handed_over_at' => ['nullable', 'date'],
            'status' => ['nullable', 'in:draft,submitted,acknowledged'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('summary') && !$this->has('outgoing_summary')) {
            $this->merge(['outgoing_summary' => $this->input('summary')]);
        }
        if ($this->has('handed_over_to_user_id') && !$this->has('received_by_user_id')) {
            $this->merge(['received_by_user_id' => $this->input('handed_over_to_user_id')]);
        }
    }
}
