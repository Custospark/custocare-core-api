<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FacilityStaffRole\StoreFacilityStaffRoleRequest;
use App\Http\Requests\FacilityStaffRole\UpdateFacilityStaffRoleRequest;
use App\Http\Resources\FacilityStaffRoleResource;
use App\Http\Resources\FacilityStaffRoleSummaryResource;
use App\Models\Department;
use App\Models\FacilityStaffRole;
use App\Services\Contracts\FacilityStaffRoleServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;


use Symfony\Component\HttpFoundation\Response;

class FacilityStaffRoleController extends Controller
{
    /**
     * Service instance
     *
     * @var FacilityStaffRoleServiceInterface
     */
    protected $service;

    /**
     * Constructor
     *
     * @param FacilityStaffRoleServiceInterface $service
     */
    public function __construct(FacilityStaffRoleServiceInterface $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $filters = $request->only([
                'facility_id',
                'staff_id',
                'role_code',
                'assignment_status',
                'is_primary_facility',
                'shift_type',
                'date',
                'search'
            ]);
            
            $paginatedAssignments = $this->service->getPaginatedAssignments($perPage, $filters);
            
            return FacilityStaffRoleResource::collection($paginatedAssignments)
                ->additional([
                    'success' => true,
                    'message' => 'Role assignments retrieved successfully',
                    'meta' => [
                        'total' => $paginatedAssignments->total(),
                        'per_page' => $paginatedAssignments->perPage(),
                        'current_page' => $paginatedAssignments->currentPage(),
                        'last_page' => $paginatedAssignments->lastPage()
                    ]
                ])
                ->response()
                ->setStatusCode(Response::HTTP_OK);
                
        } catch (\Exception $e) {
          Log::error('Controller: Failed to retrieve role assignments', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve role assignments',
                'errors' => [
                    'server' => ['Internal server error']
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }



    public function facilityStaffRoleSearch(Request $request): JsonResponse
    {
        try {
            $criteria = $request->validate([
                'q' => 'nullable|string|max:120', // search by staff name OR employee_id OR staff_uuid OR facility name/code
                'facility_id' => 'nullable|integer|exists:facilities,id',
                'staff_id' => 'nullable|integer|exists:staff,id',
                'assignment_status' => 'nullable|in:active,on_leave,suspended,terminated',
                'role_code' => 'nullable|string|max:80',
                'is_primary_facility' => 'nullable|boolean',
                'effective_on' => 'nullable|date', // point-in-time filter
                'limit' => 'nullable|integer|min:1|max:50',
            ]);

            $limit = (int) ($criteria['limit'] ?? 20);
            $q = $criteria['q'] ?? null;

            $assignments = FacilityStaffRole::query()
                ->with([
                    'facility:id,facility_uuid,facility_code,facility_name,facility_type,operational_status',
                    'staff:id,staff_uuid,user_id,employee_id,professional_title,global_role_level,employment_status',
                    'staff.user:id,global_user_uuid,first_name,last_name,display_name',
                ])
                ->select([
                    'id',
                    'assignment_uuid',
                    'facility_id',
                    'staff_id',
                    'role_code',
                    'department_ids',
                    'module_code',
                    'shift_type',
                    'hours_per_week',
                    'effective_from',
                    'effective_to',
                    'assignment_status',
                    'is_primary_facility',
                    'created_at',
                ])
                ->when(!empty($criteria['facility_id']), fn ($query) =>
                    $query->where('facility_id', $criteria['facility_id'])
                )
                ->when(!empty($criteria['staff_id']), fn ($query) =>
                    $query->where('staff_id', $criteria['staff_id'])
                )
                ->when(!empty($criteria['assignment_status']), fn ($query) =>
                    $query->where('assignment_status', $criteria['assignment_status'])
                )
                ->when(!empty($criteria['role_code']), fn ($query) =>
                    $query->where('role_code', $criteria['role_code'])
                )
                ->when(isset($criteria['is_primary_facility']), fn ($query) =>
                    $query->where('is_primary_facility', (bool) $criteria['is_primary_facility'])
                )
                ->when(!empty($criteria['effective_on']), function ($query) use ($criteria) {
                    $date = $criteria['effective_on'];
                    $query->whereDate('effective_from', '<=', $date)
                        ->where(function ($q) use ($date) {
                            $q->whereNull('effective_to')
                                ->orWhereDate('effective_to', '>=', $date);
                        });
                })
                ->when($q, function ($query) use ($q) {
                    $query->where(function ($inner) use ($q) {
                        // Facility search
                        $inner->orWhereHas('facility', function ($f) use ($q) {
                            $f->where('facility_name', 'like', "%{$q}%")
                            ->orWhere('facility_code', 'like', "%{$q}%");
                        });

                        // Staff search
                        $inner->orWhereHas('staff', function ($s) use ($q) {
                            $s->where('staff_uuid', 'like', "%{$q}%")
                            ->orWhere('employee_id', 'like', "%{$q}%")
                            ->orWhereHas('user', function ($u) use ($q) {
                                $u->where('first_name', 'like', "%{$q}%")
                                    ->orWhere('last_name', 'like', "%{$q}%")
                                    ->orWhere('display_name', 'like', "%{$q}%");
                            });
                        });

                        // Role code search
                        $inner->orWhere('role_code', 'like', "%{$q}%");
                    });
                })
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();

            /**
             * Load departments referenced by JSON department_ids for each assignment,
             * and attach them as a dynamic relation "departments" for the resource.
             */
            $allDepartmentIds = $assignments
                ->pluck('department_ids')
                ->filter()
                ->flatMap(function ($ids) {
                    if (is_string($ids)) {
                        $decoded = json_decode($ids, true);
                        return is_array($decoded) ? $decoded : [];
                    }
                    return is_array($ids) ? $ids : [];
                })
                ->unique()
                ->values();

            $departmentsById = $allDepartmentIds->isNotEmpty()
                ? Department::query()
                    ->select(['id', 'department_uuid', 'department_code', 'department_name', 'department_type'])
                    ->whereIn('id', $allDepartmentIds)
                    ->get()
                    ->keyBy('id')
                : collect();

            $assignments->each(function ($a) use ($departmentsById) {
                $ids = $a->department_ids;

                if (is_string($ids)) {
                    $ids = json_decode($ids, true);
                }

                $ids = is_array($ids) ? $ids : [];

                $a->setRelation(
                    'departments',
                    collect($ids)->map(fn ($id) => $departmentsById->get($id))->filter()->values()
                );
            });

            return response()->json([
                'success' => true,
                'data' => FacilityStaffRoleSummaryResource::collection($assignments),
                'meta' => [
                    'total' => $assignments->count(),
                    'criteria' => $criteria,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to facilityStaffRoleSearch', [
                'criteria' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to search facility staff role assignments.',
                'data' => [],
            ], 500);
        }
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param StoreFacilityStaffRoleRequest $request
     * @return JsonResponse
     */
    public function store(StoreFacilityStaffRoleRequest $request): JsonResponse
    {
        try {
            $assignment = $this->service->createAssignment($request->validated());

            return (new FacilityStaffRoleResource($assignment))
                ->additional([
                    'success' => true,
                    'message' => 'Role assignment created successfully'
                ])
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);

        } catch (ValidationException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Throwable $e) {

            Log::error('Controller: Failed to create role assignment', [
                'request' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create role assignment',
                'errors' => [
                    'server' => ['Internal server error']
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $result = $this->service->getAssignmentById($id);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => null
                ], Response::HTTP_NOT_FOUND);
            }
            
            return (new FacilityStaffRoleResource($result['data']))
                ->response()
                ->setStatusCode(Response::HTTP_OK);
                
        } catch (\Exception $e) {
          Log::error('Controller: Failed to retrieve role assignment', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve role assignment',
                'errors' => [
                    'server' => ['Internal server error']
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateFacilityStaffRoleRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateFacilityStaffRoleRequest $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            
            $result = $this->service->updateAssignment($id, $validatedData);
            
            if (!$result['success']) {
                $statusCode = isset($result['data']) && $result['data'] === null 
                    ? Response::HTTP_NOT_FOUND 
                    : Response::HTTP_UNPROCESSABLE_ENTITY;
                
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => $result['errors'] ?? []
                ], $statusCode);
            }
            
            return (new FacilityStaffRoleResource($result['data']))
                ->additional([
                    'success' => true,
                    'message' => 'Role assignment updated successfully'
                ])
                ->response()
                ->setStatusCode(Response::HTTP_OK);
                
        } catch (\Exception $e) {
          Log::error('Controller: Failed to update role assignment', [
                'id' => $id,
                'data' => $request->all(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update role assignment',
                'errors' => [
                    'server' => ['Internal server error']
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $success = $this->service->deleteAssignment($id);
            
            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role assignment not found or could not be deleted'
                ], Response::HTTP_NOT_FOUND);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Role assignment deleted successfully'
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
          Log::error('Controller: Failed to delete role assignment', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete role assignment',
                'errors' => [
                    'server' => ['Internal server error']
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get assignments by facility
     *
     * @param Request $request
     * @param int $facilityId
     * @return JsonResponse
     */
    public function byFacility(Request $request, int $facilityId): JsonResponse
    {
        try {
            $filters = $request->only([
                'role_code',
                'assignment_status',
                'shift_type',
                'date'
            ]);
            
            $assignments = $this->service->getAssignmentsByFacility($facilityId, $filters);
            
            return FacilityStaffRoleResource::collection($assignments)
                ->additional([
                    'success' => true,
                    'message' => 'Facility assignments retrieved successfully'
                ])
                ->response()
                ->setStatusCode(Response::HTTP_OK);
                
        } catch (\Exception $e) {
          Log::error('Controller: Failed to retrieve facility assignments', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve facility assignments',
                'errors' => [
                    'server' => ['Internal server error']
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get assignments by staff
     *
     * @param Request $request
     * @param int $staffId
     * @return JsonResponse
     */
    public function byStaff(Request $request, int $staffId): JsonResponse
    {
        try {
            $filters = $request->only([
                'facility_id',
                'role_code',
                'assignment_status',
                'is_primary_facility',
                'date'
            ]);
            
            $assignments = $this->service->getAssignmentsByStaff($staffId, $filters);
            
            return FacilityStaffRoleResource::collection($assignments)
                ->additional([
                    'success' => true,
                    'message' => 'Staff assignments retrieved successfully'
                ])
                ->response()
                ->setStatusCode(Response::HTTP_OK);
                
        } catch (\Exception $e) {
          Log::error('Controller: Failed to retrieve staff assignments', [
                'staff_id' => $staffId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve staff assignments',
                'errors' => [
                    'server' => ['Internal server error']
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update assignment status
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'required|string|in:active,on_leave,suspended,terminated',
                'effective_to' => 'nullable|date|required_if:status,terminated',
                'notes' => 'nullable|string'
            ]);
            
            $additionalData = $request->only(['effective_to', 'notes']);
            
            $result = $this->service->updateAssignmentStatus($id, $request->status, $additionalData);
            
            if (!$result['success']) {
                $statusCode = isset($result['data']) && $result['data'] === null 
                    ? Response::HTTP_NOT_FOUND 
                    : Response::HTTP_UNPROCESSABLE_ENTITY;
                
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => $result['errors'] ?? []
                ], $statusCode);
            }
            
            return (new FacilityStaffRoleResource($result['data']))
                ->additional([
                    'success' => true,
                    'message' => 'Assignment status updated successfully'
                ])
                ->response()
                ->setStatusCode(Response::HTTP_OK);
                
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
            
        } catch (\Exception $e) {
          Log::error('Controller: Failed to update assignment status', [
                'id' => $id,
                'data' => $request->all(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update assignment status',
                'errors' => [
                    'server' => ['Internal server error']
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Check schedule conflicts
     *
     * @param Request $request
     * @param int $staffId
     * @return JsonResponse
     */
    public function checkScheduleConflicts(Request $request, int $staffId): JsonResponse
    {
        try {
            $request->validate([
                'schedule' => 'required|array',
                'exclude_assignment_id' => 'nullable|integer|exists:facility_staff_roles,id'
            ]);
            
            $result = $this->service->checkScheduleConflicts(
                $staffId,
                $request->schedule,
                $request->exclude_assignment_id
            );
            
            return response()->json($result, Response::HTTP_OK);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
            
        } catch (\Exception $e) {
          Log::error('Controller: Failed to check schedule conflicts', [
                'staff_id' => $staffId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to check schedule conflicts',
                'errors' => [
                    'server' => ['Internal server error']
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update credentialing information
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateCredentialing(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'credentialing_completed_at' => 'nullable|date',
                'credentialed_by_staff_id' => 'nullable|integer|exists:staff,id',
                'privileging_approved_at' => 'nullable|date',
                'next_reappointment_date' => 'nullable|date'
            ]);
            
            $result = $this->service->updateCredentialing($id, $request->all());
            
            if (!$result['success']) {
                $statusCode = isset($result['data']) && $result['data'] === null 
                    ? Response::HTTP_NOT_FOUND 
                    : Response::HTTP_UNPROCESSABLE_ENTITY;
                
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => $result['errors'] ?? []
                ], $statusCode);
            }
            
            return (new FacilityStaffRoleResource($result['data']))
                ->additional([
                    'success' => true,
                    'message' => 'Credentialing information updated successfully'
                ])
                ->response()
                ->setStatusCode(Response::HTTP_OK);
                
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
            
        } catch (\Exception $e) {
          Log::error('Controller: Failed to update credentialing', [
                'id' => $id,
                'data' => $request->all(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update credentialing information',
                'errors' => [
                    'server' => ['Internal server error']
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get expiring assignments
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function expiringAssignments(Request $request): JsonResponse
    {
        try {
            $daysAhead = $request->get('days_ahead', 30);
            
            $assignments = $this->service->getExpiringAssignments($daysAhead);
            
            return FacilityStaffRoleResource::collection($assignments)
                ->additional([
                    'success' => true,
                    'message' => 'Expiring assignments retrieved successfully',
                    'meta' => [
                        'days_ahead' => $daysAhead,
                        'count' => $assignments->count()
                    ]
                ])
                ->response()
                ->setStatusCode(Response::HTTP_OK);
                
        } catch (\Exception $e) {
          Log::error('Controller: Failed to retrieve expiring assignments', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve expiring assignments',
                'errors' => [
                    'server' => ['Internal server error']
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}