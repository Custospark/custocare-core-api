<?php

namespace App\Http\Requests\FacilitySpace;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFacilitySpaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'type' => ['sometimes', 'in:consultation,triage,lab,theatre,ward'],
            'floor' => ['sometimes', 'nullable', 'string', 'max:50'],
            'building' => ['sometimes', 'nullable', 'string', 'max:80'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
