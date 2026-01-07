<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FacilityRole;
use App\Models\Role;
use App\Services\FaciltyRole\FacilityRoleService;
use App\Services\RoleService;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function __construct(
        protected FacilityRoleService $roleService
    ) {}

    public function index(Request $request)
    {
        return response()->json(
            $this->roleService->list($request->all())
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'slug'           => 'nullable|string|unique:roles,slug',
            'category'       => 'required|string|max:100',
            'description'    => 'nullable|string',
            'is_system_role' => 'boolean',
        ]);

        return response()->json(
            $this->roleService->create($data),
            201
        );
    }

    public function update(Request $request, FacilityRole $role)
    {
        $data = $request->validate([
            'name'           => 'sometimes|string|max:255',
            'category'       => 'sometimes|string|max:100',
            'description'    => 'nullable|string',
            'is_system_role' => 'boolean',
        ]);

        return response()->json(
            $this->roleService->update($role, $data)
        );
    }

    public function destroy(FacilityRole $role)
    {
        $this->roleService->delete($role);
        return response()->noContent();
    }
}
