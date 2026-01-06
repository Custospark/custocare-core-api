<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\RoleModuleDefault;
use App\Services\ModuleService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ModuleController extends Controller
{
    protected ModuleService $service;

    public function __construct(ModuleService $service)
    {
        $this->service = $service;
    }

    /**
     * List all active modules
     */
    public function index(): JsonResponse
    {
        $modules = $this->service->getAllActive();
        return response()->json($modules);
    }

    /**
     * Store new module
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|unique:modules,code',
            'name' => 'required|string',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $module = $this->service->create($data);
        return response()->json($module, 201);
    }

    /**
     * Update module
     */
    public function update(Request $request, Module $module): JsonResponse
    {
        $data = $request->validate([
            'code' => 'sometimes|string|unique:modules,code,' . $module->id,
            'name' => 'sometimes|string',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $module = $this->service->update($module, $data);
        return response()->json($module);
    }

    /**
     * Deactivate a module
     */
    public function destroy(Module $module): JsonResponse
    {
        $this->service->deactivate($module);
        return response()->json(['message' => 'Module deactivated']);
    }
    /**
     * Assign default module access for a role
     */
    public function assignDefaultAccess(Request $request)
    {
        $request->validate([
            'role_code' => 'required|string',
            'module_code' => 'required|string|exists:modules,code',
            'default_access' => 'required|boolean',
        ]);

        $roleModule = RoleModuleDefault::updateOrCreate(
            [
                'role_code' => $request->role_code,
                'module_code' => $request->module_code,
            ],
            [
                'default_access' => $request->default_access,
            ]
        );

        return response()->json($roleModule, 201);
    }

    /**
     * List all role-module defaults
     */
    public function roleModuleDefaults()
    {
        return response()->json(RoleModuleDefault::with('module')->get());
    }
}
