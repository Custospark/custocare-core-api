<?php

namespace App\Http\Requests\Ward;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'name' => ['sometimes', 'string', 'max:120'],

            'ward_type' => ['sometimes', 'in:medical,surgical,maternity,pediatric,icu,nicu,psychiatric,isolation,emergency_observation,general'],
            'building' => ['sometimes', 'nullable', 'string', 'max:80'],
            'floor' => ['sometimes', 'nullable', 'string', 'max:50'],

            'status' => ['sometimes', 'in:active,inactive,temporarily_closed'],

            'capacity_declared' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'capacity_operational' => ['sometimes', 'nullable', 'integer', 'min:0'],

            'sex_restriction' => ['sometimes', 'in:mixed,male_only,female_only'],
            'age_group' => ['sometimes', 'in:all,adult,pediatric,neonatal'],

            'note' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $declared = $this->input('capacity_declared');
            $operational = $this->input('capacity_operational');

            // Only enforce if both are being set in this request
            if ($declared !== null && $operational !== null && (int)$operational > (int)$declared) {
                $validator->errors()->add('capacity_operational', 'Operational capacity cannot exceed declared capacity.');
            }
        });
    }
}
