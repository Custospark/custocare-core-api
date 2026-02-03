<?php

namespace App\Http\Requests\Ward;

use Illuminate\Foundation\Http\FormRequest;

class StoreWardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],

            'code' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:120'],

            'ward_type' => ['required', 'in:medical,surgical,maternity,pediatric,icu,nicu,psychiatric,isolation,emergency_observation,general'],
            'building' => ['nullable', 'string', 'max:80'],
            'floor' => ['nullable', 'string', 'max:50'],

            'status' => ['nullable', 'in:active,inactive,temporarily_closed'],

            'capacity_declared' => ['nullable', 'integer', 'min:0'],
            'capacity_operational' => ['nullable', 'integer', 'min:0'],

            'sex_restriction' => ['nullable', 'in:mixed,male_only,female_only'],
            'age_group' => ['nullable', 'in:all,adult,pediatric,neonatal'],

            'note' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $declared = $this->input('capacity_declared');
            $operational = $this->input('capacity_operational');

            if ($declared !== null && $operational !== null && (int)$operational > (int)$declared) {
                $validator->errors()->add('capacity_operational', 'Operational capacity cannot exceed declared capacity.');
            }
        });
    }
}
