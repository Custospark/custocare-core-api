<?php

namespace App\Http\Requests\StaffPresence;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePresenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // You can tighten this with policies later
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'status' => ['required', 'in:off_duty,on_duty,on_break,busy,unavailable'],
            'note' => ['nullable', 'string', 'max:500'],
            'updated_by' => ['nullable', 'in:system,staff,admin'],
        ];
    }
}
