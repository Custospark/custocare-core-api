<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Facility\UpdateFacilityRequest;
use App\Http\Requests\FacilityRoles\StoreFacilityRoleRequest;
use App\Http\Resources\FacilityRoleResource;
use App\Services\FacilityRole\FacilityRoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FacilityRoleController extends Controller
{
    /**
     * The facility role service instance.
     */
    protected FacilityRoleService $service;

    /**
     * Create a new controller instance.
     */
    public function __construct(FacilityRoleService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     */
     public function index(Request $request): JsonResponse
{
    // Validate request
    $validated = $request->validate([
        'facility_id' => ['required', 'integer', 'min:1'],
        'per_page'    => ['sometimes', 'integer', 'min:1'],
        'search'      => ['sometimes', 'string'],
        'sort_by'     => ['sometimes', 'string'],
        'sort_order'  => ['sometimes', 'in:asc,desc'],
        'is_active'   => ['sometimes', 'boolean'],
    ]);

    try {
        $filters = [
            'facility_id'    => (int) $validated['facility_id'],
            'is_system_role' => true, // 🔒 enforced server-side
            'is_active'      => $validated['is_active'] ?? null,
            'search'         => $validated['search'] ?? null,
            'sort_by'        => $validated['sort_by'] ?? 'name',
            'sort_order'     => $validated['sort_order'] ?? 'asc',
        ];

        $perPage = $validated['per_page'] ?? 20;

        // Get query builder from service
        $rolesQuery = $this->service->getAllRoles($filters);

        // Execute pagination
        $roles = $rolesQuery->paginate($perPage);

        // Wrap in resource
        return $this->successResponse(
            FacilityRoleResource::collection($roles),
            'Facility system roles retrieved successfully.',
            [
                'pagination' => [
                    'total'         => $roles->total(),
                    'count'         => $roles->count(),
                    'per_page'      => $roles->perPage(),
                    'current_page'  => $roles->currentPage(),
                    'total_pages'   => $roles->lastPage(),
                ],
                'filters_applied' => $filters,
            ]
        );
    } catch (\Throwable $e) {
        Log::error('Failed to retrieve facility roles', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return $this->errorResponse(
            'Failed to retrieve facility roles.',
            500,
            ['system' => 'An unexpected error occurred.']
        );
    }
}


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFacilityRoleRequest $request): JsonResponse
    {
             $validated = $request->validate(['facility_id' => ['required', 'integer', 'min:1'],]);
            $facilityId = (int) $validated['facility_id'];
            $validated['facility_id']=$facilityId;

        try {
            $validatedData = $request->validated();
            
            $role = $this->service->createRole($validatedData);
            
            return $this->successResponse(
                new FacilityRoleResource($role),
                'Facility role created successfully.',
                null,
                201
            );
            
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse(
                $e->getMessage(),
                422,
                ['validation' => [$e->getMessage()]]
            );
        } catch (\Exception $e) {
            Log::error('Failed to create facility role', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            return $this->errorResponse(
                $e->getMessage(),
                422,
                ['role' => [$e->getMessage()]]
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $role = $this->service->getRoleById($id);
            
            if (!$role) {
                return $this->errorResponse(
                    'Facility role not found.',
                    404,
                    ['id' => 'The specified facility role does not exist.']
                );
            }
            
            return $this->successResponse(
                new FacilityRoleResource($role),
                'Facility role retrieved successfully.'
            );
            
        } catch (\Exception $e) {
            Log::error('Failed to retrieve facility role', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'Failed to retrieve facility role.',
                500,
                ['system' => 'An unexpected error occurred.']
            );
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFacilityRequest $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            
            $role = $this->service->updateRole($id, $validatedData);
            
            return $this->successResponse(
                new FacilityRoleResource($role),
                'Facility role updated successfully.'
            );
            
        } catch (\Exception $e) {
            Log::error('Failed to update facility role', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            return $this->errorResponse(
                $e->getMessage(),
                422,
                ['role' => [$e->getMessage()]]
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->service->deleteRole($id);
            
            return $this->successResponse(
                null,
                'Facility role deleted successfully.'
            );
            
        } catch (\Exception $e) {
            Log::error('Failed to delete facility role', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                $e->getMessage(),
                422,
                ['role' => [$e->getMessage()]]
            );
        }
    }

    /**
     * Get roles by facility.
     */
    public function getByFacility(int $facilityId, Request $request): JsonResponse
    {
        Log::info($request);
        try {
            $filters = $request->only(['category', 'is_active', 'is_system_role']);
            
            $roles = $this->service->getRolesByFacility($facilityId, $filters);
            
            return $this->successResponse(
                FacilityRoleResource::collection($roles),
                'Facility roles retrieved successfully.',
                [
                    'total' => $roles->count(),
                    'facility_id' => $facilityId
                ]
            );
            
        } catch (\Exception $e) {
            Log::error('Failed to retrieve facility roles by facility', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'Failed to retrieve facility roles.',
                500,
                ['system' => 'An unexpected error occurred.']
            );
        }
    }

    /**
     * Get roles by category.
     */
    public function getByCategory(string $category): JsonResponse
    {
        try {
            $roles = $this->service->getRolesByCategory($category);
            
            return $this->successResponse(
                FacilityRoleResource::collection($roles),
                'Facility roles retrieved successfully.',
                [
                    'total' => $roles->count(),
                    'category' => $category
                ]
            );
            
        } catch (\Exception $e) {
            Log::error('Failed to retrieve facility roles by category', [
                'category' => $category,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'Failed to retrieve facility roles.',
                500,
                ['system' => 'An unexpected error occurred.']
            );
        }
    }

    /**
     * Toggle role active status.
     */
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $role = $this->service->toggleRoleStatus($id);
            
            return $this->successResponse(
                new FacilityRoleResource($role),
                'Facility role status updated successfully.'
            );
            
        } catch (\Exception $e) {
            Log::error('Failed to toggle facility role status', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                $e->getMessage(),
                422,
                ['role' => [$e->getMessage()]]
            );
        }
    }

    /**
     * Assign permissions to role.
     */
    public function assignPermissions(Request $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            
            $role = $this->service->assignPermissions($id, $validatedData['permissions']);
            
            return $this->successResponse(
                new FacilityRoleResource($role),
                'Permissions assigned to facility role successfully.'
            );
            
        } catch (\Exception $e) {
            Log::error('Failed to assign permissions to facility role', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            return $this->errorResponse(
                $e->getMessage(),
                422,
                ['permissions' => [$e->getMessage()]]
            );
        }
    }

    /**
     * Get permissions for role.
     */
    public function getPermissions(int $id): JsonResponse
    {
        try {
            $permissions = $this->service->getRolePermissions($id);
            
            return $this->successResponse(
                $permissions,
                'Role permissions retrieved successfully.',
                [
                    'role_id' => $id,
                    'total_permissions' => count($permissions)
                ]
            );
            
        } catch (\Exception $e) {
            Log::error('Failed to retrieve role permissions', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                $e->getMessage(),
                422,
                ['role' => [$e->getMessage()]]
            );
        }
    }

    /**
     * Get system roles.
     */
    public function getSystemRoles(): JsonResponse
    {
        try {
            $roles = $this->service->getSystemRoles();
            
            return $this->successResponse(
                FacilityRoleResource::collection($roles),
                'System roles retrieved successfully.',
                [
                    'total' => $roles->count(),
                    'type' => 'system'
                ]
            );
            
        } catch (\Exception $e) {
            Log::error('Failed to retrieve system roles', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'Failed to retrieve system roles.',
                500,
                ['system' => 'An unexpected error occurred.']
            );
        }
    }

    /**
     * Get custom roles.
     */
    public function getCustomRoles(): JsonResponse
    {
        try {
            $roles = $this->service->getCustomRoles();
            
            return $this->successResponse(
                FacilityRoleResource::collection($roles),
                'Custom roles retrieved successfully.',
                [
                    'total' => $roles->count(),
                    'type' => 'custom'
                ]
            );
            
        } catch (\Exception $e) {
            Log::error('Failed to retrieve custom roles', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'Failed to retrieve custom roles.',
                500,
                ['system' => 'An unexpected error occurred.']
            );
        }
    }

    /**
     * Search roles.
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $query = $request->input('q', '');
            $filters = $request->only(['facility_id', 'category', 'is_active']);
            
            if (empty($query)) {
                return $this->errorResponse(
                    'Search query is required.',
                    422,
                    ['query' => ['Search query parameter "q" is required.']]
                );
            }
            
            $roles = $this->service->searchRoles($query, $filters);
            
            return $this->successResponse(
                FacilityRoleResource::collection($roles),
                'Facility roles search results.',
                [
                    'total' => $roles->count(),
                    'query' => $query,
                    'filters_applied' => $filters
                ]
            );
            
        } catch (\Exception $e) {
            Log::error('Failed to search facility roles', [
                'query' => $request->input('q'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'Failed to search facility roles.',
                500,
                ['system' => 'An unexpected error occurred.']
            );
        }
    }

    /**
     * Show role by code.
     */
    public function showByCode(string $code): JsonResponse
    {
        try {
            $role = $this->service->getRoleByCode($code);
            
            if (!$role) {
                return $this->errorResponse(
                    'Facility role not found.',
                    404,
                    ['code' => 'The specified facility role does not exist.']
                );
            }
            
            return $this->successResponse(
                new FacilityRoleResource($role),
                'Facility role retrieved successfully.'
            );
            
        } catch (\Exception $e) {
            Log::error('Failed to retrieve facility role by code', [
                'code' => $code,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'Failed to retrieve facility role.',
                500,
                ['system' => 'An unexpected error occurred.']
            );
        }
    }

    /**
     * Helper: Send success response with consistent format.
     */
    protected function successResponse($data = null, string $message = 'Success', $meta = null, int $code = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if ($meta) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $code);
    }

    /**
     * Helper: Send error response with consistent format.
     */
    protected function errorResponse(string $message, int $code = 400, $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
            'data' => null,
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }
}