<?php

namespace App\Http\Controllers\Api;

use App\Events\SpaceOccupancyChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\StaffSpace\AssignStaffSpaceRequest;
use App\Http\Resources\StaffSpaceAssignmentResource;
use App\Http\Resources\FacilitySpaceResource;
use App\Models\FacilitySpace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\Staff;
use App\Models\StaffSpaceAssignment;
use App\Services\StaffSpaceAssignmentService\StaffSpaceAssignmentService;
use Illuminate\Support\Facades\DB;

class StaffSpaceAssignmentController extends Controller
{
    public function __construct(private StaffSpaceAssignmentService $service) {}

    /**
     * Get current occupancy for a facility (all spaces with their current assignments)
     */
    public function currentOccupancy(Request $request): JsonResponse
    {
        Log::info('Current occupancy request', [
            'query' => $request->all(),
            'auth_user_id' => Auth::id(),
            'has_authorization_header' => $request->hasHeader('Authorization'),
        ]);

        $validated = $request->validate([
            'facility_id' => ['required', 'integer', 'min:1', 'exists:facilities,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'space_type' => ['nullable', 'string'],
            'floor' => ['nullable', 'string'],
            'building' => ['nullable', 'string'],
            'search' => ['nullable', 'string'],
            'status' => ['nullable', 'in:all,occupied,unoccupied'],
        ]);

        try {
            $facilityId = (int) $validated['facility_id'];
            $perPage = (int) ($validated['per_page'] ?? 20);
            
            $filters = [
                'space_type' => $validated['space_type'] ?? null,
                'floor' => $validated['floor'] ?? null,
                'building' => $validated['building'] ?? null,
                'search' => $validated['search'] ?? null,
                'status' => $validated['status'] ?? 'all',
            ];

            // Get paginated spaces with their current assignments
            $spaces = $this->service->listCurrentOccupancy($facilityId, $filters, $perPage);
            Log::info('Current occupancy result summary', [
                'facility_id' => $facilityId,
                'total' => $spaces->total(),
                'returned' => $spaces->count(),
                'current_page' => $spaces->currentPage(),
                'auth_user_id' => Auth::id(),
            ]);
            
            // Load staff resources efficiently for all assignments
            $this->loadStaffResourcesForSpaces($spaces, $facilityId);

            // Transform the spaces data
            $transformedData = $spaces->getCollection()->map(function ($space) use ($facilityId) {
                $currentAssignment = $space->currentAssignment;
                
                // Get staff role code from the pre-loaded relationship
                $roleCode = null;
                if ($currentAssignment && 
                    $currentAssignment->relationLoaded('staff') && 
                    $currentAssignment->staff && 
                    $currentAssignment->staff->relationLoaded('facilityStaffRoles')) {
                    
                    $primaryRole = $currentAssignment->staff->facilityStaffRoles
                        ->where('facility_id', $facilityId)
                        ->where('assignment_status', 'active')
                        ->first();
                    
                    $roleCode = $primaryRole ? $primaryRole->role_code : null;
                }

                // Get department information if available
                $departmentInfo = null;
                if ($currentAssignment && 
                    $currentAssignment->relationLoaded('staff') && 
                    $currentAssignment->staff &&
                    $currentAssignment->staff->relationLoaded('facilityStaffRoles')) {
                    
                    $primaryRole = $currentAssignment->staff->facilityStaffRoles
                        ->where('facility_id', $facilityId)
                        ->where('assignment_status', 'active')
                        ->first();
                    
                    if ($primaryRole && !empty($primaryRole->department_ids)) {
                        $departmentsById = request('departmentsById', []);
                        $deptId = is_array($primaryRole->department_ids) ? 
                            ($primaryRole->department_ids[0] ?? null) : 
                            $primaryRole->department_ids;
                        
                        if ($deptId && isset($departmentsById[$deptId])) {
                            $departmentInfo = $departmentsById[$deptId];
                        }
                    }
                }

                return [
                    'id' => $space->id,
                    'name' => $space->name,
                    'type' => $space->type,
                    'floor' => $space->floor,
                    'building' => $space->building,
                    'is_active' => $space->is_active,
                    'facility_id' => $space->facility_id,
                    'is_occupied' => $space->is_occupied,
                    'occupancy_count' => $space->occupancy_count,
                    'current_assignment' => $currentAssignment ? [
                        'id' => $currentAssignment->id,
                        'staff_id' => $currentAssignment->staff_id,
                        'staff' => $currentAssignment->relationLoaded('staff') && $currentAssignment->staff ? [
                            'staff_id' => $currentAssignment->staff->id,
                            'staff_uuid' => $currentAssignment->staff->staff_uuid,
                            'employee_id' => $currentAssignment->staff->employee_id,
                            'user' => $currentAssignment->staff->relationLoaded('user') && $currentAssignment->staff->user ? [
                                'id' => $currentAssignment->staff->user->id,
                                'first_name' => $currentAssignment->staff->user->first_name,
                                'last_name' => $currentAssignment->staff->user->last_name,
                                'full_name' => trim("{$currentAssignment->staff->user->first_name} {$currentAssignment->staff->user->last_name}"),
                            ] : null,
                            'role_code' => $roleCode,
                            'department' => $departmentInfo,
                        ] : null,
                        'assigned_at' => $currentAssignment->assigned_at?->toISOString(),
                        'released_at' => $currentAssignment->released_at?->toISOString(),
                        'note' => $currentAssignment->note,
                        'is_active' => $currentAssignment->is_active,
                        'duration_minutes' => $currentAssignment->duration_minutes,
                    ] : null,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Current occupancy retrieved successfully.',
                'data' => $transformedData,
                'meta' => [
                    'facility_id' => $facilityId,
                    'current_page' => $spaces->currentPage(),
                    'last_page' => $spaces->lastPage(),
                    'per_page' => $spaces->perPage(),
                    'total' => $spaces->total(),
                    'filters' => $filters,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error retrieving current occupancy', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'query' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve current occupancy.',
                'data' => [],
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get space by currently authenticated user.
     */
    public function myCurrentSpace(Request $request): JsonResponse
    {
        Log::info('Staff current space request', ['query' => $request->all()]);

        $request->validate([
            'facility_id' => ['required', 'integer', 'min:1', 'exists:facilities,id']
        ]);

        try {
            $user = Auth::user();
            $staffId = $user->staff->id;
            $facilityId = (int) $request->query('facility_id');

            $assignment = $this->service->getCurrentSpaceForStaff($staffId, $facilityId);
            
            // Load relationships if assignment exists
            if ($assignment) {
                $assignment->load([
                    'space',
                    'staff.user',
                    'staff.facilityStaffRoles' => function ($q) use ($facilityId) {
                        $q->where('facility_id', $facilityId)
                          ->where('assignment_status', 'active');
                    },
                    'assignedByUser',
                    'releasedByUser',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Current space retrieved successfully.',
                'data' => $assignment ? StaffSpaceAssignmentResource::make($assignment) : null,
                'meta' => [
                    'facility_id' => $facilityId,
                    'staff_id' => $staffId,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error retrieving current space', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'query' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve current space.',
                'data' => null,
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Self space assignment by authenticated user.
     */
    public function assignMySpace(AssignStaffSpaceRequest $request): JsonResponse
    {
        Log::info('Assign staff space request', $request->all());

        try {
            $user = $request->user();
            $staffId = Staff::where('user_id',Auth::id())->value('id');
            $facilityId = (int) $request->input('facility_id');
            $spaceId = (int) $request->input('space_id');

            $assignment = $this->service->assignStaffToSpace(
                staffId: $staffId,
                facilityId: $facilityId,
                spaceId: $spaceId,
                byUserId: $user->id,
                note: $request->input('note')
            );

            SpaceOccupancyChanged::dispatch($facilityId, $spaceId, 'assigned');

            // Load relationships for the resource
            $assignment->load([
                'space',
                'staff.user',
                'staff.facilityStaffRoles' => function ($q) use ($facilityId) {
                    $q->where('facility_id', $facilityId)
                      ->where('assignment_status', 'active');
                },
                'assignedByUser',
                'releasedByUser',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Space assigned successfully.',
                'data' => StaffSpaceAssignmentResource::make($assignment),
                'meta' => [
                    'facility_id' => $facilityId,
                    'staff_id' => $staffId,
                    'space_id' => $spaceId,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error assigning space', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign space: ' . $e->getMessage(),
                'data' => null,
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Admin space assignment for any staff.
     */
    public function assignSpaceByAdmin(AssignStaffSpaceRequest $request): JsonResponse
    {
        Log::info('Admin assign staff space request', $request->all());

        try {
            $staffId = $request->input('staff_id');
            $facilityId = (int) $request->input('facility_id');
            $spaceId = (int) $request->input('space_id');

            $assignment = $this->service->assignStaffToSpace(
                staffId: $staffId,
                facilityId: $facilityId,
                spaceId: $spaceId,
                byUserId: Auth::id(),
                note: $request->input('note')
            );

            SpaceOccupancyChanged::dispatch($facilityId, $spaceId, 'assigned');

            // Load relationships for the resource
            $assignment->load([
                'space',
                'staff.user',
                'staff.facilityStaffRoles' => function ($q) use ($facilityId) {
                    $q->where('facility_id', $facilityId)
                      ->where('assignment_status', 'active');
                },
                'assignedByUser',
                'releasedByUser',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Space assigned successfully.',
                'data' => StaffSpaceAssignmentResource::make($assignment),
                'meta' => [
                    'facility_id' => $facilityId,
                    'staff_id' => $staffId,
                    'space_id' => $spaceId,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error assigning space by admin', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign space: ' . $e->getMessage(),
                'data' => null,
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Release space by authenticated staff member.
     */
    public function releaseMySpace(Request $request): JsonResponse
    {
        Log::info('Release staff space request', $request->all());

        $request->validate([
            'facility_id' => ['required', 'integer', 'min:1', 'exists:facilities,id']
        ]);

        try {
            $user = Auth::user();
            $staffId = Staff::where('user_id',Auth::id())->value('id');
            $facilityId = (int) $request->input('facility_id');

            // Get current assignment before releasing
            $currentAssignment = $this->service->getCurrentSpaceForStaff($staffId, $facilityId);
            $spaceId = $currentAssignment?->space_id;
            
            $this->service->releaseStaffSpace($staffId, $facilityId, $user->id);

            if ($spaceId) {
                SpaceOccupancyChanged::dispatch($facilityId, $spaceId, 'released');
            }
            
            // Get the released assignment
            $releasedAssignment = StaffSpaceAssignment::query()
                ->where('staff_id', $staffId)
                ->where('facility_id', $facilityId)
                ->whereNotNull('released_at')
                ->latest('released_at')
                ->first();
            
            if ($releasedAssignment) {
                // Load relationships for the resource
                $releasedAssignment->load([
                    'space',
                    'staff.user',
                    'staff.facilityStaffRoles' => function ($q) use ($facilityId) {
                        $q->where('facility_id', $facilityId)
                          ->where('assignment_status', 'active');
                    },
                    'assignedByUser',
                    'releasedByUser',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Space released successfully.',
                'data' => $releasedAssignment ? StaffSpaceAssignmentResource::make($releasedAssignment) : null,
                'meta' => [
                    'facility_id' => $facilityId,
                    'staff_id' => $staffId,
                    'had_assignment' => $currentAssignment !== null,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error releasing space', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to release space: ' . $e->getMessage(),
                'data' => null,
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Release space by Admin.
     */
    public function releaseSpaceByAdmin(Request $request): JsonResponse
    {
        Log::info('Admin release staff space request', $request->all());

        $request->validate([
            'facility_id' => ['required', 'integer', 'min:1', 'exists:facilities,id'],
            'staff_id' => ['required', 'integer', 'min:1', 'exists:staff,id']
        ]);

        try {
            $staffId = (int) $request->input('staff_id');
            $facilityId = (int) $request->input('facility_id');

            // Get current assignment before releasing
            $currentAssignment = $this->service->getCurrentSpaceForStaff($staffId, $facilityId);
            $spaceId = $currentAssignment?->space_id;

            $this->service->releaseStaffSpace($staffId, $facilityId, Auth::id());

            if ($spaceId) {
                SpaceOccupancyChanged::dispatch($facilityId, $spaceId, 'released');
            }

            // Get the released assignment
            $releasedAssignment = StaffSpaceAssignment::query()
                ->where('staff_id', $staffId)
                ->where('facility_id', $facilityId)
                ->whereNotNull('released_at')
                ->latest('released_at')
                ->first();
            
            if ($releasedAssignment) {
                // Load relationships for the resource
                $releasedAssignment->load([
                    'space',
                    'staff.user',
                    'staff.facilityStaffRoles' => function ($q) use ($facilityId) {
                        $q->where('facility_id', $facilityId)
                          ->where('assignment_status', 'active');
                    },
                    'assignedByUser',
                    'releasedByUser',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Space released successfully.',
                'data' => $releasedAssignment ? StaffSpaceAssignmentResource::make($releasedAssignment) : null,
                'meta' => [
                    'facility_id' => $facilityId,
                    'staff_id' => $staffId,
                    'had_assignment' => $currentAssignment !== null,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error releasing space by admin', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to release space: ' . $e->getMessage(),
                'data' => null,
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function availableSpaces(Request $request): JsonResponse
    {
        Log::info('Available spaces request', ['query' => $request->all()]);

        $validated = $request->validate([
            'facility_id' => ['required', 'integer', 'min:1', 'exists:facilities,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'space_type' => ['nullable', 'string'],
            'floor' => ['nullable', 'string'],
            'building' => ['nullable', 'string'],
            'search' => ['nullable', 'string'],
        ]);

        try {
            $facilityId = (int) $validated['facility_id'];
            $perPage = (int) ($validated['per_page'] ?? 20);

            $query = FacilitySpace::query()
                ->where('facility_id', $facilityId)
                ->active()
                ->unoccupied()
                ->select([
                    'id',
                    'facility_id',
                    'name',
                    'type',
                    'floor',
                    'building',
                    'is_active',
                ]);

            // Optional filters (kept lightweight)
            if (!empty($validated['space_type'])) {
                $query->where('type', $validated['space_type']);
            }

            if (!empty($validated['floor'])) {
                $query->where('floor', $validated['floor']);
            }

            if (!empty($validated['building'])) {
                $query->where('building', $validated['building']);
            }

            if (!empty($validated['search'])) {
                $term = trim((string) $validated['search']);
                $query->where('name', 'like', "%{$term}%");
            }

            $spaces = $query
                ->orderBy('building')
                ->orderBy('floor')
                ->orderBy('name')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Available spaces retrieved successfully.',
                'data' => $spaces->items(),
                'meta' => [
                    'facility_id' => $facilityId,
                    'current_page' => $spaces->currentPage(),
                    'last_page' => $spaces->lastPage(),
                    'per_page' => $spaces->perPage(),
                    'total' => $spaces->total(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error retrieving available spaces', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available spaces.',
                'data' => [],
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

   
  /**
 * GET /api/facilities/{facilityId}/staff-for-space-assignment
 * Get staff members for space assignment (admin use)
 * DEFAULT: Returns only staff WITHOUT active space assignments
 */
public function getStaffForSpaceAssignment(Request $request, int $facilityId): JsonResponse
{
    Log::info('Get staff for space assignment request', [
        'facility_id' => $facilityId,
        'query' => $request->all()
    ]);

    $validated = $request->validate([
        'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        'search' => ['nullable', 'string'],
        'include_assigned' => ['nullable', 'boolean'], // Changed from exclude_assigned to include_assigned
    ]);

    try {
        $perPage = (int) ($validated['per_page'] ?? 20);
        $search = $validated['search'] ?? null;
        $includeAssigned = $validated['include_assigned'] ?? false; // Default is FALSE (exclude assigned)

        // Get staff with active assignments at the facility
        $staffQuery = Staff::query()
            ->select([
                'staff.id',
                'staff.staff_uuid',
                'staff.employee_id',
                'users.first_name',
                'users.last_name',
                'facility_staff_roles.role_code',
                'facility_staff_roles.assignment_status',
                DB::raw('CASE WHEN staff_space_assignments.id IS NOT NULL THEN true ELSE false END as has_space_assignment'),
                'staff_space_assignments.space_id',
                'facility_spaces.name as space_name',
            ])
            ->join('facility_staff_roles', function ($join) use ($facilityId) {
                $join->on('facility_staff_roles.staff_id', '=', 'staff.id')
                    ->where('facility_staff_roles.facility_id', $facilityId)
                    ->where('facility_staff_roles.assignment_status', 'active')
                    ->whereNull('facility_staff_roles.deleted_at');
            })
            ->join('users', 'users.id', '=', 'staff.user_id')
            // Left join to check for existing space assignments
            ->leftJoin('staff_space_assignments', function ($join) use ($facilityId) {
                $join->on('staff_space_assignments.staff_id', '=', 'staff.id')
                    ->where('staff_space_assignments.facility_id', $facilityId)
                    ->whereNull('staff_space_assignments.released_at');
            })
            // Left join to get space info for assigned staff
            ->leftJoin('facility_spaces', 'facility_spaces.id', '=', 'staff_space_assignments.space_id')
            ->whereNull('staff.deleted_at')
            ->whereNull('users.deleted_at')
            ->distinct('staff.id');

        // DEFAULT: Exclude staff who already have an active space assignment
        if (!$includeAssigned) {
            $staffQuery->whereNull('staff_space_assignments.id');
        }

        // Apply search if provided
        if ($search) {
            $staffQuery->where(function ($query) use ($search) {
                $query->where('users.first_name', 'like', "%{$search}%")
                    ->orWhere('users.last_name', 'like', "%{$search}%")
                    ->orWhere('staff.employee_id', 'like', "%{$search}%")
                    ->orWhere('staff.staff_uuid', 'like', "%{$search}%")
                    ->orWhere('facility_staff_roles.role_code', 'like', "%{$search}%")
                    ->orWhere('facility_spaces.name', 'like', "%{$search}%");
            });
        }

        // Order by whether they have assignment (assigned staff last) and then by name
        $staffQuery->orderBy('has_space_assignment')
                   ->orderBy('users.first_name')
                   ->orderBy('users.last_name');

        $staff = $staffQuery->paginate($perPage);

        // Transform the data to match frontend needs
        $transformedData = $staff->getCollection()->map(function ($staffMember) {
            return [
                'staff_id' => $staffMember->id,
                'staff_uuid' => $staffMember->staff_uuid,
                'employee_id' => $staffMember->employee_id,
                'first_name' => $staffMember->first_name,
                'last_name' => $staffMember->last_name,
                'full_name' => trim("{$staffMember->first_name} {$staffMember->last_name}"),
                'role_code' => $staffMember->role_code,
                'assignment_status' => $staffMember->assignment_status,
                'has_space_assignment' => (bool) $staffMember->has_space_assignment,
                'current_space' => $staffMember->has_space_assignment ? [
                    'space_id' => $staffMember->space_id,
                    'space_name' => $staffMember->space_name,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Staff for space assignment retrieved successfully.',
            'data' => $transformedData,
            'meta' => [
                'facility_id' => $facilityId,
                'current_page' => $staff->currentPage(),
                'last_page' => $staff->lastPage(),
                'per_page' => $staff->perPage(),
                'total' => $staff->total(),
                'search_applied' => $search ? true : false,
                'include_assigned' => $includeAssigned,
                'unassigned_count' => $includeAssigned ? 
                    $transformedData->where('has_space_assignment', false)->count() : 
                    $staff->total(),
                'assigned_count' => $includeAssigned ? 
                    $transformedData->where('has_space_assignment', true)->count() : 
                    0,
            ],
        ]);
    } catch (\Throwable $e) {
        Log::error('Error retrieving staff for space assignment', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'facility_id' => $facilityId,
            'query' => $request->all(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve staff for space assignment.',
            'data' => [],
        ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
    }
}

/**
 * Load staff resources for all assignments in the spaces collection
 */
private function loadStaffResourcesForSpaces($spaces, int $facilityId): void
{
    // Handle both paginator and collection objects
    $spaceItems = method_exists($spaces, 'items') 
        ? $spaces->items()  // If it's a paginator, get the items
        : $spaces;          // If it's already a collection, use as-is
    
    // Collect all staff IDs from current assignments
    $staffIds = collect($spaceItems)
        ->filter(function ($space) {
            // Check if currentAssignment relationship is loaded and has data
            return $space->relationLoaded('currentAssignment') && 
                    $space->currentAssignment && 
                    $space->currentAssignment->staff_id;
        })
        ->pluck('currentAssignment.staff_id')
        ->unique()
        ->values()
        ->toArray();

    if (empty($staffIds)) {
        return;
    }

    // Load staff with their facility roles and user data
    $staffWithRoles = Staff::with([
        'user',
        'facilityStaffRoles' => function ($q) use ($facilityId) {
            $q->where('facility_id', $facilityId)
                ->where('assignment_status', 'active');
        },
    ])
    ->whereIn('id', $staffIds)
    ->get()
    ->keyBy('id');

    // Load department lookup map
    $deptIds = $staffWithRoles->flatMap(function ($staff) {
        return collect($staff->facilityStaffRoles ?? [])
            ->flatMap(fn ($r) => is_array($r->department_ids) ? $r->department_ids : []);
    })
    ->filter()
    ->unique()
    ->values()
    ->toArray();

    $departmentsById = [];
    if (!empty($deptIds)) {
        $departmentsById = \App\Models\Department::query()
            ->where('facility_id', $facilityId)
            ->whereIn('id', $deptIds)
            ->get(['id', 'department_uuid', 'department_code', 'department_name', 'department_type'])
            ->keyBy('id')
            ->map(fn ($d) => [
                'id' => $d->id,
                'department_uuid' => $d->department_uuid,
                'department_code' => $d->department_code,
                'department_name' => $d->department_name,
                'department_type' => $d->department_type,
            ])
            ->toArray();
    }

    // Merge departments into request context for resources
    request()->merge(['departmentsById' => $departmentsById]);

    // Attach staff data to each space's assignment
    foreach ($spaceItems as $space) {
        // Check if the space has a current assignment loaded and if we have the staff data
        if ($space->relationLoaded('currentAssignment') && 
            $space->currentAssignment && 
            $space->currentAssignment->staff_id &&
            isset($staffWithRoles[$space->currentAssignment->staff_id])) {
            
            // Attach the pre-loaded staff to the assignment
            $space->currentAssignment->setRelation('staff', $staffWithRoles[$space->currentAssignment->staff_id]);
        }
    }
}
}