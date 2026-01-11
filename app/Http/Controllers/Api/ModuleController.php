<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\RoleModuleDefault;
use App\Services\Module\ModuleService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ModuleController extends Controller
{
    public function __construct(
        protected ModuleService $service
    ) {}

    /**
     * List all active modules
     */
    public function index(): JsonResponse
    {
        $modules = $this->service->getAllActive();

        return response()->json([
            'success' => true,
            'message' => 'Modules retrieved successfully',
            'data'    => $modules,
        ]);
    }

    /**
     * Store new module
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'        => 'required|string|unique:modules,code',
            'name'        => 'required|string',
            'description' => 'nullable|string',
            'is_active'   => 'nullable|boolean',
        ]);

        $module = $this->service->create($data);

        return response()->json([
            'success' => true,
            'message' => 'Module created successfully',
            'data'    => $module,
        ], 201);
    }

    /**
     * Update module
     */
    public function update(Request $request, Module $module): JsonResponse
    {
        $data = $request->validate([
            'code'        => 'sometimes|string|unique:modules,code,' . $module->id,
            'name'        => 'sometimes|string',
            'description' => 'nullable|string',
            'is_active'   => 'nullable|boolean',
        ]);

        $updatedModule = $this->service->update($module, $data);

        return response()->json([
            'success' => true,
            'message' => 'Module updated successfully',
            'data'    => $updatedModule,
        ]);
    }

    /**
     * Deactivate a module
     */
    public function destroy(Module $module): JsonResponse
    {
        $this->service->deactivate($module);

        return response()->json([
            'success' => true,
            'message' => 'Module deactivated successfully',
            'data'    => null,
        ]);
    }

    /**
     * Assign default module access for a role
     */
    public function assignDefaultAccess(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role_code'      => 'required|string',
            'module_code'    => 'required|string|exists:modules,code',
            'default_access' => 'required|boolean',
        ]);

        $roleModule = RoleModuleDefault::updateOrCreate(
            [
                'role_code'   => $data['role_code'],
                'module_code' => $data['module_code'],
            ],
            [
                'default_access' => $data['default_access'],
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Default module access updated successfully',
            'data'    => $roleModule,
        ], 201);
    }

    /**
     * List all role-module defaults
     */
    public function roleModuleDefaults(): JsonResponse
    {
        $defaults = RoleModuleDefault::with('module')->get();

        return response()->json([
            'success' => true,
            'message' => 'Role module defaults retrieved successfully',
            'data'    => $defaults,
        ]);
    }
}
