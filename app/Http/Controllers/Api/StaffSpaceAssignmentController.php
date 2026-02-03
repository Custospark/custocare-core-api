<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StaffSpace\AssignStaffSpaceRequest;
use App\Http\Resources\StaffSpaceAssignmentResource;
use App\Models\FacilitySpace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\Staff;
use App\Models\FacilityStaffRole;
use App\Services\StaffSpaceAssignmentService\StaffSpaceAssignmentService;

class StaffSpaceAssignmentController extends Controller
{
    public function __construct(private StaffSpaceAssignmentService $service) {}

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
            $staffId = $user->staff->id;
            $facilityId = (int) $request->input('facility_id');
            $spaceId = (int) $request->input('space_id');

            $assignment = $this->service->assignStaffToSpace(
                staffId: $staffId,
                facilityId: $facilityId,
                spaceId: $spaceId,
                byUserId: $user->id,
                note: $request->input('note')
            );

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
            $staffId = $user->staff->id;
            $facilityId = (int) $request->input('facility_id');

            $releasedAssignment = $this->service->releaseStaffSpace($staffId, $facilityId, $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Space released successfully.',
                'data' => $releasedAssignment ? StaffSpaceAssignmentResource::make($releasedAssignment) : null,
                'meta' => [
                    'facility_id' => $facilityId,
                    'staff_id' => $staffId,
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

            $releasedAssignment = $this->service->releaseStaffSpace($staffId, $facilityId, Auth::id());

            return response()->json([
                'success' => true,
                'message' => 'Space released successfully.',
                'data' => $releasedAssignment ? StaffSpaceAssignmentResource::make($releasedAssignment) : null,
                'meta' => [
                    'facility_id' => $facilityId,
                    'staff_id' => $staffId,
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

   public function currentOccupancy(Request $request): JsonResponse
{
    Log::info('Current occupancy request', ['query' => $request->all()]);

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
        
        $filters = $request->only(['space_type', 'floor', 'building', 'search']);

        // Get spaces with their current assignments using the service
        $spaces = $this->service->listCurrentOccupancy($facilityId, $filters, $perPage);

        // Preload staff data for all assignments to optimize queries
        $this->loadStaffResourcesForSpaces($spaces, $facilityId);

        // Check if $spaces is paginated or a collection
        if ($spaces instanceof \Illuminate\Pagination\AbstractPaginator) {
            // It's paginated - use pagination methods
            $meta = [
                'facility_id' => $facilityId,
                'filters_applied' => $filters,
                'current_page' => $spaces->currentPage(),
                'last_page' => $spaces->lastPage(),
                'per_page' => $spaces->perPage(),
                'total' => $spaces->total(),
                'occupied_spaces' => $spaces->where('current_assignment', '!=', null)->count(),
                'available_spaces' => $spaces->where('current_assignment', null)->count(),
            ];
        } else {
            // It's a collection - provide manual counts
            $meta = [
                'facility_id' => $facilityId,
                'filters_applied' => $filters,
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $perPage,
                'total' => $spaces->count(),
                'occupied_spaces' => $spaces->where('current_assignment', '!=', null)->count(),
                'available_spaces' => $spaces->where('current_assignment', null)->count(),
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Current occupancy retrieved successfully.',
            'data' => StaffSpaceAssignmentResource::collection($spaces),
            'meta' => $meta,
        ]);
    } catch (\Throwable $e) {
        Log::error('Error retrieving occupancy list', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'query' => $request->all(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve occupancy list.',
            'data' => [],
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
        ]);

        try {
            $perPage = (int) ($validated['per_page'] ?? 20);
            $search = $validated['search'] ?? null;

            // Get staff with active assignments at the facility
            $staffQuery = Staff::query()
                ->select([
                    'staff.id',
                    'staff.staff_uuid',
                    'staff.employee_id',
                    'users.first_name',
                    'users.last_name',
                    'facility_staff_roles.role_code',
                    'facility_staff_roles.assignment_status'
                ])
                ->join('facility_staff_roles', function ($join) use ($facilityId) {
                    $join->on('facility_staff_roles.staff_id', '=', 'staff.id')
                        ->where('facility_staff_roles.facility_id', $facilityId)
                        ->where('facility_staff_roles.assignment_status', 'active')
                        ->whereNull('facility_staff_roles.deleted_at');
                })
                ->join('users', 'users.id', '=', 'staff.user_id')
                ->whereNull('staff.deleted_at')
                ->whereNull('users.deleted_at')
                ->distinct('staff.id');

            // Apply search if provided
            if ($search) {
                $staffQuery->where(function ($query) use ($search) {
                    $query->where('users.first_name', 'like', "%{$search}%")
                        ->orWhere('users.last_name', 'like', "%{$search}%")
                        ->orWhere('staff.employee_id', 'like', "%{$search}%")
                        ->orWhere('staff.staff_uuid', 'like', "%{$search}%")
                        ->orWhere('facility_staff_roles.role_code', 'like', "%{$search}%");
                });
            }

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
        'facilityStaffRoles.facility',
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