<?php

namespace App\Http\Requests\FacilityRoles;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFacilityRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roleId = $this->route('role')?->id;

        return [
            'name'           => 'sometimes|string|max:255',
            'code'           => 'sometimes|string|max:100|unique:facility_roles,code,' . $roleId,
            'category'       => 'sometimes|string|max:100',
            'description'    => 'nullable|string',
            'is_active'      => 'sometimes|boolean',
        ];
    }
}
