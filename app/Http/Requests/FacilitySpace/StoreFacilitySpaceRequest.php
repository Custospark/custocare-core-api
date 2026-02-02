<?php

namespace App\Http\Requests\FacilitySpace;

use Illuminate\Foundation\Http\FormRequest;

class StoreFacilitySpaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:consultation,triage,lab,theatre,ward'],
            'floor' => ['nullable', 'string', 'max:50'],
            'building' => ['nullable', 'string', 'max:80'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
