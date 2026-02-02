<?php

namespace App\Http\Requests\StaffSpace;

use Illuminate\Foundation\Http\FormRequest;

class AssignStaffSpaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'space_id' => ['required', 'integer', 'exists:facility_spaces,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
