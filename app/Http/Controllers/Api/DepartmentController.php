<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Department\StoreDepartmentRequest;
use App\Http\Requests\Department\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Services\Contracts\DepartmentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class DepartmentController extends Controller
{
    /**
     * Department service instance.
     *
     * @var DepartmentServiceInterface
     */
    protected DepartmentServiceInterface $departmentService;

    /**
     * Constructor.
     *
     * @param DepartmentServiceInterface $departmentService
     */
    public function __construct(DepartmentServiceInterface $departmentService)
    {
        $this->departmentService = $departmentService;
        
        // Apply middleware
        // $this->middleware('auth:api')->except(['index', 'show']);
        // $this->middleware('can:view-departments')->only(['index', 'show']);
        // $this->middleware('can:create-departments')->only(['store']);
        // $this->middleware('can:update-departments')->only(['update']);
        // $this->middleware('can:delete-departments')->only(['destroy']);
        // $this->middleware('can:restore-departments')->only(['restore']);
    }

    /**
     * Display a listing of departments.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'facility_id', 'department_type', 'status', 'search',
                'sort_by', 'sort_order', 'per_page', 'with_children'
            ]);

            $result = $this->departmentService->getAllDepartments($filters);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => $result['errors'] ?? null,
                ], $result['status'] ?? 500);
            }

            // Transform the data using DepartmentResource
            $departments = DepartmentResource::collection($result['data']);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $departments,
                'meta' => [
                    'current_page' => $result['data']->currentPage(),
                    'last_page' => $result['data']->lastPage(),
                    'per_page' => $result['data']->perPage(),
                    'total' => $result['data']->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Department index error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve departments.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Store a newly created department.
     *
     * @param StoreDepartmentRequest $request
     * @return JsonResponse
     */
    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        try {
            // Authorize the action using Policy
            $this->authorize('create', \App\Models\Department::class);

            $validatedData = $request->validated();

            // Call service to create department
            $result = $this->departmentService->createDepartment($validatedData);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => $result['errors'] ?? null,
                ], $result['status'] ?? 500);
            }

            // Transform the created department using DepartmentResource
            $departmentResource = new DepartmentResource($result['data']);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $departmentResource,
            ], $result['status'] ?? 201);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to create departments.',
            ], 403);
        } catch (\Exception $e) {
            Log::error('Department store error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create department.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Display the specified department.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            // Authorize the action using Policy
            $this->authorize('view', \App\Models\Department::class);

            $result = $this->departmentService->getDepartmentByUuid($uuid);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], $result['status'] ?? 404);
            }

            // Load relationships if requested
            $department = $result['data'];
            $department->load(['facility', 'parentDepartment', 'childDepartments', 'departmentHead']);

            // Transform the department using DepartmentResource
            $departmentResource = new DepartmentResource($department);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $departmentResource,
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view this department.',
            ], 403);
        } catch (\Exception $e) {
            Log::error('Department show error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve department.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Update the specified department.
     *
     * @param UpdateDepartmentRequest $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function update(UpdateDepartmentRequest $request, string $uuid): JsonResponse
    {
        try {
            // First get the department to authorize
            $result = $this->departmentService->getDepartmentByUuid($uuid);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], $result['status'] ?? 404);
            }

            // Authorize the action using Policy
            $this->authorize('update', $result['data']);

            $validatedData = $request->validated();

            // Call service to update department
            $updateResult = $this->departmentService->updateDepartment($uuid, $validatedData);

            if (!$updateResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $updateResult['message'],
                    'errors' => $updateResult['errors'] ?? null,
                ], $updateResult['status'] ?? 500);
            }

            // Transform the updated department using DepartmentResource
            $departmentResource = new DepartmentResource($updateResult['data']);

            return response()->json([
                'success' => true,
                'message' => $updateResult['message'],
                'data' => $departmentResource,
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to update this department.',
            ], 403);
        } catch (\Exception $e) {
            Log::error('Department update error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update department.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Remove the specified department.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            // First get the department to authorize
            $result = $this->departmentService->getDepartmentByUuid($uuid);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], $result['status'] ?? 404);
            }

            // Authorize the action using Policy
            $this->authorize('delete', $result['data']);

            // Call service to delete department
            $deleteResult = $this->departmentService->deleteDepartment($uuid);

            if (!$deleteResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $deleteResult['message'],
                ], $deleteResult['status'] ?? 500);
            }

            return response()->json([
                'success' => true,
                'message' => $deleteResult['message'],
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to delete this department.',
            ], 403);
        } catch (\Exception $e) {
            Log::error('Department destroy error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete department.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted department.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function restore(string $uuid): JsonResponse
    {
        try {
            // First check if department exists (including trashed)
            $department = \App\Models\Department::withTrashed()->where('department_uuid', $uuid)->first();
            
            if (!$department) {
                return response()->json([
                    'success' => false,
                    'message' => 'Department not found.',
                ], 404);
            }

            // Authorize the action using Policy
            $this->authorize('restore', $department);

            // Call service to restore department
            $restoreResult = $this->departmentService->restoreDepartment($uuid);

            if (!$restoreResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $restoreResult['message'],
                ], $restoreResult['status'] ?? 500);
            }

            // Transform the restored department using DepartmentResource
            $departmentResource = new DepartmentResource($restoreResult['data']);

            return response()->json([
                'success' => true,
                'message' => $restoreResult['message'],
                'data' => $departmentResource,
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to restore this department.',
            ], 403);
        } catch (\Exception $e) {
            Log::error('Department restore error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to restore department.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get departments by facility.
     *
     * @param int $facilityId
     * @param Request $request
     * @return JsonResponse
     */
    public function byFacility(int $facilityId, Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', \App\Models\Department::class);

            $filters = $request->only(['department_type', 'status', 'with_children']);
            $result = $this->departmentService->getDepartmentsByFacility($facilityId, $filters);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 500);
            }

            $departments = DepartmentResource::collection($result['data']);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $departments,
                'meta' => [
                    'facility_id' => $facilityId,
                    'count' => $result['data']->count(),
                ],
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view departments.',
            ], 403);
        } catch (\Exception $e) {
            Log::error('Department byFacility error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve departments by facility.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get departments by type.
     *
     * @param string $type
     * @param Request $request
     * @return JsonResponse
     */
    public function byType(string $type, Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', \App\Models\Department::class);

            $filters = $request->only(['facility_id', 'status']);
            $result = $this->departmentService->getDepartmentsByType($type, $filters);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 500);
            }

            $departments = DepartmentResource::collection($result['data']);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $departments,
                'meta' => [
                    'department_type' => $type,
                    'count' => $result['data']->count(),
                ],
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view departments.',
            ], 403);
        } catch (\Exception $e) {
            Log::error('Department byType error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve departments by type.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}