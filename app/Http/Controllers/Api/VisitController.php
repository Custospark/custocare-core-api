<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Visit\StoreVisitRequest;
use App\Http\Requests\Visit\UpdateVisitRequest;
use App\Http\Resources\PatientSearchResource;
use App\Http\Resources\VisitResource;
use App\Http\Resources\VisitCollection;
use App\Models\FacilityStaffRole;
use App\Models\Staff;
use App\Models\StaffPresence;
use App\Models\Visit;
use App\Services\Contracts\VisitServiceInterface;
use Illuminate\Container\Attributes\Auth as AttributesAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Auth as FacadesAuth;
use Illuminate\Support\Facades\Auth as SupportFacadesAuth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use function Illuminate\Log\log;

/**
 * Visit Controller
 *
 * Handles HTTP requests for Visit operations
 */
class VisitController extends Controller
{
    /**
     * Visit service instance
     *
     * @var VisitServiceInterface
     */
    protected $visitService;

    /**
     * Constructor
     *
     * @param VisitServiceInterface $visitService
     */
    public function __construct(VisitServiceInterface $visitService)
    {
        $this->visitService = $visitService;
    }

    /**
     * Display a listing of visits.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Get filters from request
            $filters = $request->only([
                'facility_id',
                'patient_id',
                'status',
                'visit_type',
                'current_phase',
                'date_from',
                'date_to',
            ]);

            $perPage = $request->get('per_page', 15);

            // Get visits via service
            $result = $this->visitService->getAllVisits($filters, $perPage);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Transform to collection resource
            $visits = $result['data'];
            $transformed = new VisitCollection($visits);

            return response()->json([
                'success' => true,
                'data' => $transformed,
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve visits list', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function myQueue(Request $request): JsonResponse
    {
        try {
            // 1) Facility from header
            $facilityId = (int) $request->header('X-Facility-Id');

            if (!$facilityId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing X-Facility-Id header.',
                    'errors' => ['facility_id' => ['X-Facility-Id header is required.']],
                    'data' => [],
                ], 422);
            }

            // 2) Resolve staff_id from authenticated user
            $userId = Auth::id();
            $staffId = Staff::query()->where('user_id', $userId)->value('id');

            if (!$staffId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Staff profile not found for this user.',
                    'errors' => ['staff' => ['No staff record is linked to this account.']],
                    'data' => [],
                ], 403);
            }

            // 3) Confirm active assignment at this facility (security)
            $assignment = FacilityStaffRole::query()
                ->where('facility_id', $facilityId)
                ->where('staff_id', $staffId)
                ->where('assignment_status', 'active')
                ->first(['id', 'role_code']);
                Log::alert($assignment);

            if (!$assignment) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not assigned to this facility.',
                    'errors' => ['facility' => ['No active facility assignment found.']],
                    'data' => [],
                ], 403);
            }

            // 4) Optional filters
            $filters = $request->validate([
                'current_phase' => 'nullable|in:registration,waiting_triage,triage,waiting_provider,consultation,diagnostic_tests,awaiting_results,treatment,procedures,observation,admission_pending,billing,discharge_pending,discharged,left_without_being_seen,left_against_medical_advice,transferred,admitted,expired',
                'limit' => 'nullable|integer|min:1|max:100',
            ]);

            $phase = $filters['current_phase'] ?? null;
            $limit = (int) ($filters['limit'] ?? 50);

            // 5) My queue = visits assigned to me (VISIT-centric)
            $visits = Visit::query()
                ->where('facility_id', $facilityId)
                ->where('assigned_staff_id', $staffId)
                ->whereIn('status', ['active', 'in_progress'])
                ->when($phase, fn ($q) => $q->where('current_phase', $phase))
                ->with(['patient.user'])
                ->orderBy('acuity_score', 'asc')
                ->orderBy('waiting_since', 'asc')
                ->limit($limit)
                ->get();

            /**
             * Legacy behavior: still return unique patients in `data`
             * (do NOT remove this to avoid breaking existing clients)
             */
            $patients = $visits
                ->map(fn ($v) => $v->patient)
                ->filter()
                ->unique('id')
                ->values();

            /**
             * ✅ New: Visit-centric queue that DOES NOT collapse walk-in visits.
             * Frontend should render this list for the queue UI.
             */
            $queueVisits = $visits->map(function ($v) {
                return [
                    'visit_id' => $v->id,
                    'visit_uuid' => $v->visit_uuid,
                    'facility_id' => $v->facility_id,

                    'patient_id' => $v->patient_id,
                    'patient' => $v->patient ? new PatientSearchResource($v->patient) : null,

                    'current_phase' => $v->current_phase,
                    'current_department_id' => $v->current_department_id,

                    'assigned_staff_id' => $v->assigned_staff_id,
                    'assigned_at' => optional($v->assigned_at)->toISOString(),

                    'waiting_since' => optional($v->waiting_since)->toISOString(),
                    'acuity_score' => $v->acuity_score,
                    'arrived_at' => optional($v->arrived_at)->toISOString(),

                    'visit_type' => $v->visit_type,
                    'status' => $v->status,
                    'is_walk_in' => (bool) $v->is_walk_in,
                ];
            })->values();

            // keep your existing meta.queue for legacy consumers if you already use it
            $queue = $visits->map(function ($v) {
                return [
                    'visit_uuid' => $v->visit_uuid,
                    'patient_id' => $v->patient_id,
                    'current_phase' => $v->current_phase,
                    'current_department_id' => $v->current_department_id,
                    'assigned_staff_id' => $v->assigned_staff_id,
                    'assigned_at' => optional($v->assigned_at)->toISOString(),
                    'waiting_since' => optional($v->waiting_since)->toISOString(),
                    'acuity_score' => $v->acuity_score,
                    'arrived_at' => optional($v->arrived_at)->toISOString(),
                    'visit_type' => $v->visit_type,
                    'status' => $v->status,
                ];
            })->values();

            return response()->json([
                'success' => true,

                // ✅ Keep legacy return (unique patients)
                'data' => PatientSearchResource::collection($patients),

                'meta' => [
                    'facility_id' => $facilityId,
                    'staff_id' => $staffId,
                    'role_code' => $assignment->role_code,
                    'filters' => [
                        'current_phase' => $phase,
                    ],

                    // legacy
                    'queue' => $queue,

                    // ✅ NEW: visit-centric queue (use this for UI)
                    'queue_visits' => $queueVisits,

                    'total_visits' => $visits->count(),
                    'total_patients' => $patients->count(),
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to load my queue', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load queue.',
                'data' => [],
            ], 500);
        }
    }





    /**
     * Store a newly created visit.
     *
     * @param StoreVisitRequest $request
     * @return JsonResponse
     */
    public function store(StoreVisitRequest $request): JsonResponse
    {
        try {
            // Get validated data
            $validatedData = $request->validated();

            // Get current user ID for audit
            $staffId =Staff::where('user_id',Auth::id())->first()->id;

            // Create visit via service
            $result = $this->visitService->createVisit($validatedData, $staffId);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Transform to resource
            $visit = $result['data'];
            $transformed = new VisitResource($visit);

            return response()->json([
                'success' => true,
                'data' => $transformed,
                'message' => $result['message'],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create visit', [
                'data' => $request->all(),
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Display the specified visit.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            // Get visit via service
            $result = $this->visitService->getVisitByUuid($uuid);

            if (!$result['success']) {
                return response()->json($result, 404);
            }

            // Transform to resource
            $visit = $result['data'];
            $transformed = new VisitResource($visit->load([
                'facility',
                'patient',
                'currentDepartment',
                'referringFacility',
                'referringProvider',
                'dischargedBy',
                'followupProvider',
                'createdBy',
                'updatedBy',
            ]));

            return response()->json([
                'success' => true,
                'data' => $transformed,
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve visit', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Update the specified visit.
     *
     * @param UpdateVisitRequest $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function update(UpdateVisitRequest $request, string $uuid): JsonResponse
    {
        Log::info($request);
        
        try {
            // Get validated data
            $validatedData = $request->validated();

            // Get current user ID for audit
            $userId = Auth::id();

            // Update visit via service
            $result = $this->visitService->updateVisit($uuid, $validatedData, $userId);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Transform to resource
            $visit = $result['data'];
            $transformed = new VisitResource($visit);

            return response()->json([
                'success' => true,
                'data' => $transformed,
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update visit', [
                'uuid' => $uuid,
                'data' => $request->all(),
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
    
   
   /**
    * Forwarding patient to anoother staff member within the same facility.
    */

    public function assignStaffToVisit(Request $request): JsonResponse
    {
        try {
            // ✅ Logical flow:
            // 0) Resolve facility (header) + referring staff (auth user -> staff)
            // 1) Validate input
            // 2) Ensure visit exists and belongs to same facility
            // 3) Ensure assigned staff exists
            // 4) Ensure BOTH referring staff and assigned staff have ACTIVE FacilityStaffRole in SAME facility
            // 5) Ensure assigned staff presence status is allowed
            // 6) Update visit (assigned_staff_id, assigned_at, referring_provider_staff_id) safely

           
            $facilityId = (int) $request->header('X-Facility-Id');
            if (!$facilityId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing X-Facility-Id header.',
                    'errors' => ['facility_id' => ['X-Facility-Id header is required.']],
                    'data' => [],
                ], 422);
            }

            $validated = $request->validate([
                'visit_id' => 'required|integer',
                'assigned_staff_id' => 'required|integer',
            ]);

            $visitId = (int) $validated['visit_id'];
            $assignedStaffId = (int) $validated['assigned_staff_id'];

            // Resolve referring staff from authenticated user
            $referringStaffId = Staff::query()->where('user_id', Auth::id())->value('id');
            if (!$referringStaffId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Staff profile not found for this user.',
                    'errors' => ['staff' => ['No staff record is linked to this account.']],
                    'data' => [],
                ], 403);
            }

            // 3) Check assigned staff exists
            $staffExists = Staff::query()->whereKey($assignedStaffId)->exists();
            if (!$staffExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assigned staff not found.',
                    'errors' => ['assigned_staff_id' => ['No staff record exists for the provided assigned staff.']],
                    'data' => [],
                ], 404);
            }

            // 4) Ensure BOTH staff are ACTIVE in the SAME facility
            $referringActive = FacilityStaffRole::query()
                ->where('facility_id', $facilityId)
                ->where('staff_id', $referringStaffId)
                ->where('assignment_status', 'active')
                ->whereDate('effective_from', '<=', now()->toDateString())
                ->where(function ($q) {
                    $q->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', now()->toDateString());
                })
                ->exists();

            if (!$referringActive) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not assigned to this facility.',
                    'errors' => ['facility' => ['No active facility assignment found for the referring staff.']],
                    'data' => [],
                ], 403);
            }

            $assignedActive = FacilityStaffRole::query()
                ->where('facility_id', $facilityId)
                ->where('staff_id', $assignedStaffId)
                ->where('assignment_status', 'active')
                ->whereDate('effective_from', '<=', now()->toDateString())
                ->where(function ($q) {
                    $q->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', now()->toDateString());
                })
                ->exists();

            if (!$assignedActive) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assigned staff is not active in this facility.',
                    'errors' => ['assigned_staff_id' => ['Assigned staff has no active assignment in this facility.']],
                    'data' => [],
                ], 403);
            }

            // 5) Check assigned staff presence status is allowed
            $presence = StaffPresence::query()
                ->where('staff_id', $assignedStaffId)
                ->orderByDesc('updated_at')
                ->first();

            if (!$presence || !in_array($presence->status, ['busy', 'on_duty'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Staff is not available for assignment.',
                    'errors' => ['staff_presence' => ['Staff must be in status busy or on_duty to be assigned.']],
                    'data' => [],
                ], 422);
            }

            // 6) Update visit assignment (lock to avoid race conditions)
            $visit = DB::transaction(function () use ($visitId, $facilityId, $assignedStaffId, $referringStaffId) {
                $visit = Visit::query()->lockForUpdate()->find($visitId);

                if (!$visit) {
                    return null;
                }

                // Ensure visit belongs to the same facility
                if ((int) $visit->facility_id !== (int) $facilityId) {
                    // return a sentinel value to handle outside transaction cleanly
                    return 'FACILITY_MISMATCH';
                }

                $visit->update([
                    'assigned_staff_id' => $assignedStaffId,
                    'assigned_at' => now(),
                    'referring_provider_staff_id' => $referringStaffId,
                ]);

                return $visit->fresh();
            });

            if ($visit === 'FACILITY_MISMATCH') {
                return response()->json([
                    'success' => false,
                    'message' => 'Visit does not belong to this facility.',
                    'errors' => ['visit_id' => ['The provided visit_id is not under the current facility scope.']],
                    'data' => [],
                ], 403);
            }

            if (!$visit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Visit not found.',
                    'errors' => ['visit_id' => ['No visit record exists for the provided visit_id.']],
                    'data' => [],
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new VisitResource($visit),
                'message' => 'Visit assigned successfully.',
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
                'data' => [],
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to assign staff to visit', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
    /**
 * Get available staff for patient forwarding within the facility.
 * 
 * This method returns staff members who are:
 * 1. Assigned to the current facility (active FacilityStaffRole)
 * 2. Currently on duty or busy (from StaffPresence)
 * 3. Not overloaded (optional future enhancement)
 * 
 * @param Request $request
 * @return JsonResponse
 */
public function getStaffForPatientForwarding(Request $request): JsonResponse
{
    Log::error($request);
    try {
        // 1) Facility from header
        $facilityId = (int) $request->header('X-Facility-Id');
        
        if (!$facilityId) {
            return response()->json([
                'success' => false,
                'message' => 'Missing X-Facility-Id header.',
                'errors' => ['facility_id' => ['X-Facility-Id header is required.']],
                'data' => [],
            ], 422);
        }

        // Cast exclude_current_staff to boolean before validation
        if ($request->has('exclude_current_staff')) {
            $request->merge([
                'exclude_current_staff' => filter_var($request->input('exclude_current_staff'), FILTER_VALIDATE_BOOLEAN)
            ]);
        }

        // 2) Optional filters from request
        $filters = $request->validate([
            'role_code' => 'nullable',
            'department_id' => 'nullable|integer',
            'presence_status' => 'nullable|in:on_duty,busy',
            'search' => 'nullable|string|max:100',
            'limit' => 'nullable|integer|min:1|max:100',
            'exclude_current_staff' => 'nullable|boolean',
        ]);

        // 3) Get current user ID and exclude this user's staff record(s)
        $userId = Auth::id();
        
        // Get ALL staff IDs that belong to the current user
        $excludeStaffIds = Staff::query()
            ->where('user_id', $userId)
            ->pluck('id')
            ->toArray();

        // 4) Query to get staff available for forwarding
        $staffList = Staff::query()
            ->with(['user:id,first_name,last_name,display_name'])
            ->select([
                'staff.id',
                'staff.staff_uuid',
                'staff.professional_title',
                'staff.global_role_level',
                'staff.employee_id',
                'staff.max_concurrent_patients',
                'staff.total_patients_treated',
            ])
            // Join with users table for names
            ->join('users', 'staff.user_id', '=', 'users.id')
            // Join with facility_staff_roles to filter by current facility and active assignment
            ->join('facility_staff_roles as fsr', function ($join) use ($facilityId) {
                $join->on('staff.id', '=', 'fsr.staff_id')
                    ->where('fsr.facility_id', $facilityId)
                    ->where('fsr.assignment_status', 'active')
                    ->whereDate('fsr.effective_from', '<=', now()->toDateString())
                    ->where(function ($q) {
                        $q->whereNull('fsr.effective_to')
                            ->orWhereDate('fsr.effective_to', '>=', now()->toDateString());
                    });
            })
            // Join with staff_presences to get current status
            ->leftJoin('staff_presences as sp', function ($join) use ($facilityId) {
                $join->on('staff.id', '=', 'sp.staff_id')
                    ->where('sp.facility_id', $facilityId)
                    ->whereNull('sp.ended_at');
            })
            // Optional: Join with staff_space_assignments for current space
            ->leftJoin('staff_space_assignments as ssa', function ($join) use ($facilityId) {
                $join->on('staff.id', '=', 'ssa.staff_id')
                    ->where('ssa.facility_id', $facilityId)
                    ->whereNull('ssa.released_at');
            })
            // Optional: Join with facility_spaces for space details
            ->leftJoin('facility_spaces as fs', 'ssa.space_id', '=', 'fs.id')
            // ALWAYS exclude the current user's staff records
            ->when(!empty($excludeStaffIds), function ($query) use ($excludeStaffIds) {
                return $query->whereNotIn('staff.id', $excludeStaffIds);
            })
            // Also handle the optional exclude_current_staff flag for backward compatibility
            ->when(
                isset($filters['exclude_current_staff']) && $filters['exclude_current_staff'] === true && !empty($excludeStaffIds), 
                function ($query) use ($excludeStaffIds) {
                    // This is redundant now but kept for clarity
                    return $query->whereNotIn('staff.id', $excludeStaffIds);
                }
            )
            // Apply filters
            ->when($filters['role_code'] ?? null, function ($query, $roleCode) {
                return $query->where('fsr.role_code', $roleCode);
            })
            ->when($filters['department_id'] ?? null, function ($query, $departmentId) {
                return $query->whereJsonContains('fsr.department_ids', (int)$departmentId);
            })
            ->when($filters['presence_status'] ?? null, function ($query, $status) {
                return $query->where('sp.status', $status);
            }, function ($query) {
                // Default: only show staff who are on_duty or busy
                return $query->whereIn('sp.status', ['on_duty', 'busy']);
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                return $query->where(function ($q) use ($search) {
                    $q->where('users.first_name', 'LIKE', "%{$search}%")
                        ->orWhere('users.last_name', 'LIKE', "%{$search}%")
                        ->orWhere('users.display_name', 'LIKE', "%{$search}%")
                        ->orWhere('staff.employee_id', 'LIKE', "%{$search}%");
                });
            })
            // Group by staff to avoid duplicates from multiple joins
            ->groupBy([
                'staff.id',
                'staff.staff_uuid',
                'staff.professional_title',
                'staff.global_role_level',
                'staff.employee_id',
                'staff.max_concurrent_patients',
                'staff.total_patients_treated',
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.display_name',
                'fsr.role_code',
                'fsr.module_code',
                'fsr.department_ids',
                'sp.status',
                'sp.started_at',
                'fs.name',
                'fs.type',
                'fs.floor',
            ])
            // Select additional columns after grouping
            ->addSelect([
                'users.first_name',
                'users.last_name',
                'users.display_name',
                'fsr.role_code',
                'fsr.module_code',
                'fsr.department_ids',
                'sp.status as presence_status',
                'sp.started_at as presence_started_at',
                'fs.name as current_space_name',
                'fs.type as current_space_type',
                'fs.floor as current_space_floor',
            ])
            ->orderBy('users.first_name')
            ->orderBy('users.last_name')
            ->limit($filters['limit'] ?? 50)
            ->get();

        // 5) Transform the results
        $transformedStaff = $staffList->map(function ($staff) use ($facilityId) {
            // Calculate current workload (you might want to get actual current patient count)
            $currentPatientCount = 0; // You can implement this based on your data
            
            // Determine availability status
            $availability = $this->determineStaffAvailability(
                $staff->presence_status,
                $currentPatientCount,
                $staff->max_concurrent_patients
            );

            return [
                'staff_id' => $staff->id,
                'staff_uuid' => $staff->staff_uuid,
                'employee_id' => $staff->employee_id,
                'professional_title' => $staff->professional_title,
                'global_role_level' => $staff->global_role_level,
                
                // User information
                'first_name' => $staff->first_name,
                'last_name' => $staff->last_name,
                'display_name' => $staff->display_name,
                'full_name' => trim("{$staff->first_name} {$staff->last_name}"),
                
                // Facility role information
                'role_code' => $staff->role_code,
                'module_code' => $staff->module_code,
                'department_ids' => $staff->department_ids,
                
                // Presence information
                'presence_status' => $staff->presence_status,
                'presence_started_at' => $staff->presence_started_at,
                'is_available' => $availability['is_available'],
                'availability_reason' => $availability['reason'],
                
                // Space information
                'current_space' => $staff->current_space_name ? [
                    'name' => $staff->current_space_name,
                    'type' => $staff->current_space_type,
                    'floor' => $staff->current_space_floor,
                ] : null,
                
                // Workload metrics
                'max_concurrent_patients' => $staff->max_concurrent_patients,
                'current_patient_count' => $currentPatientCount,
                'total_patients_treated' => $staff->total_patients_treated,
                'workload_percentage' => $staff->max_concurrent_patients > 0 
                    ? round(($currentPatientCount / $staff->max_concurrent_patients) * 100, 2)
                    : 0,
            ];
        });

        // 6) Group by availability status for better UI presentation
        $groupedStaff = [
            'available' => $transformedStaff->where('is_available', true)->values(),
            'busy' => $transformedStaff->where('presence_status', 'busy')->where('is_available', false)->values(),
            'other' => $transformedStaff->where('is_available', false)->where('presence_status', '!=', 'busy')->values(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'staff' => $transformedStaff,
                'grouped' => $groupedStaff,
                'summary' => [
                    'total' => $transformedStaff->count(),
                    'available' => $groupedStaff['available']->count(),
                    'busy' => $groupedStaff['busy']->count(),
                    'other' => $groupedStaff['other']->count(),
                ],
            ],
            'meta' => [
                'facility_id' => $facilityId,
                'filters_applied' => $filters,
                'excluded_current_staff_ids' => $excludeStaffIds,
            ],
            'message' => 'Staff list retrieved successfully.',
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $e->errors(),
            'data' => [],
        ], 422);
    } catch (\Exception $e) {
        Log::error('Failed to retrieve staff for patient forwarding', [
            'facility_id' => $facilityId ?? null,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'An unexpected error occurred. Please try again later.',
            'error' => config('app.debug') ? $e->getMessage() : null,
        ], 500);
    }
}

/**
 * Helper method to determine staff availability based on presence and workload.
 * 
 * @param string $presenceStatus
 * @param int $currentPatientCount
 * @param int $maxConcurrentPatients
 * @return array
 */
private function determineStaffAvailability(string $presenceStatus, int $currentPatientCount, int $maxConcurrentPatients): array
{
    // If staff is not on duty or busy, they're not available
    if (!in_array($presenceStatus, ['on_duty', 'busy'])) {
        return [
            'is_available' => false,
            'reason' => 'Staff is not on duty',
        ];
    }

    // If staff is busy, they might still be available for high-priority cases
    if ($presenceStatus === 'busy') {
        return [
            'is_available' => true, // Busy staff can still accept patients in emergency
            'reason' => 'Staff is busy but can accept urgent cases',
        ];
    }

    // Check workload capacity
    if ($maxConcurrentPatients > 0 && $currentPatientCount >= $maxConcurrentPatients) {
        return [
            'is_available' => false,
            'reason' => 'Staff has reached maximum patient capacity',
        ];
    }

    // Calculate workload percentage
    $workloadPercentage = $maxConcurrentPatients > 0 
        ? ($currentPatientCount / $maxConcurrentPatients) * 100 
        : 0;

    // If workload is high (over 80%), mark as less available
    if ($workloadPercentage > 80) {
        return [
            'is_available' => true,
            'reason' => 'Staff has high workload',
        ];
    }

    // Default: available
    return [
        'is_available' => true,
        'reason' => 'Available for assignment',
    ];
}


    /**
     * Remove the specified visit.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        try {
            // Authorize deletion
            // $this->authorize('delete', \App\Models\Visit::class);

            // Get current user ID
            $userId = Auth::id();

            // Delete visit via service
            $result = $this->visitService->deleteVisit($uuid, $userId);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete visit', [
                'uuid' => $uuid,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted visit.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function restore(Request $request, string $uuid): JsonResponse
    {
        try {
            // Authorize restoration
            $this->authorize('restore', \App\Models\Visit::class);

            // Get current user ID
            $userId = $request->user()->id;

            // Restore visit via service
            $result = $this->visitService->restoreVisit($uuid, $userId);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Transform to resource
            $visit = $result['data'];
            $transformed = new VisitResource($visit);

            return response()->json([
                'success' => true,
                'data' => $transformed,
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to restore visit', [
                'uuid' => $uuid,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get visits by facility.
     *
     * @param Request $request
     * @param int $facilityId
     * @return JsonResponse
     */
    public function byFacility(Request $request, int $facilityId): JsonResponse
    {
        try {
            // Get filters from request
            $filters = $request->only([
                'status',
                'visit_type',
                'date_from',
                'date_to',
            ]);

            $perPage = $request->get('per_page', 15);

            // Get visits via service
            $result = $this->visitService->getVisitsByFacility($facilityId, $filters, $perPage);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Transform to collection resource
            $visits = $result['data'];
            $transformed = new VisitCollection($visits);

            return response()->json([
                'success' => true,
                'data' => $transformed,
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve visits by facility', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get visits by patient.
     *
     * @param Request $request
     * @param int $patientId
     * @return JsonResponse
     */
    public function byPatient(Request $request, int $patientId): JsonResponse
    {
        try {
            // Get filters from request
            $filters = $request->only([
                'status',
                'visit_type',
                'date_from',
                'date_to',
            ]);

            $perPage = $request->get('per_page', 15);

            // Get visits via service
            $result = $this->visitService->getVisitsByPatient($patientId, $filters, $perPage);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Transform to collection resource
            $visits = $result['data'];
            $transformed = new VisitCollection($visits);

            return response()->json([
                'success' => true,
                'data' => $transformed,
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve visits by patient', [
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Update visit phase.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function updatePhase(Request $request, string $uuid): JsonResponse
    {
        try {
            // Validate request
            $request->validate([
                'phase' => 'required|string|in:registration,waiting_triage,triage,waiting_provider,consultation,diagnostic_tests,awaiting_results,treatment,procedures,observation,admission_pending,billing,discharge_pending,discharged,left_without_being_seen,left_against_medical_advice,transferred,admitted,expired',
                'additional_data' => 'nullable|array',
            ]);

            // Get current user ID
            $userId = $request->user()->id;

            // Update phase via service
            $result = $this->visitService->updateVisitPhase(
                $uuid,
                $request->phase,
                $request->additional_data ?? [],
                $userId
            );

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Transform to resource
            $visit = $result['data'];
            $transformed = new VisitResource($visit);

            return response()->json([
                'success' => true,
                'data' => $transformed,
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update visit phase', [
                'uuid' => $uuid,
                'phase' => $request->phase,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Update visit status.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function updateStatus(Request $request, string $uuid): JsonResponse
    {
        
        try {
            // Validate request
            $request->validate([
                'status' => 'required|string|in:active,completed,cancelled,no_show,in_progress',
                'additional_data' => 'nullable|array',
            ]);

            // Get current user ID
            $userId = $request->user()->id;

            // Update status via service
            $result = $this->visitService->updateVisitStatus(
                $uuid,
                $request->status,
                $request->additional_data ?? [],
                $userId
            );

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Transform to resource
            $visit = $result['data'];
            $transformed = new VisitResource($visit);

            return response()->json([
                'success' => true,
                'data' => $transformed,
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update visit status', [
                'uuid' => $uuid,
                'status' => $request->status,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Discharge a visit.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function discharge(Request $request, string $uuid): JsonResponse
    {
        try {
            // Validate request
            $request->validate([
                'discharge_disposition' => 'required|string|in:home,admitted_to_hospital,transferred_to_facility,left_ama,left_without_seen,expired,hospice,skilled_nursing_facility,rehabilitation_facility,psychiatric_facility,law_enforcement_custody',
                'discharge_instructions' => 'nullable|string|max:5000',
                'discharge_medications' => 'nullable|array',
                'followup_scheduled_at' => 'nullable|date',
                'followup_provider_staff_id' => 'nullable|integer|exists:staff,id',
                'discharged_at' => 'nullable|date',
            ]);

            // Get current user ID
            $userId = $request->user()->id;

            // Prepare discharge data
            $dischargeData = $request->only([
                'discharge_disposition',
                'discharge_instructions',
                'discharge_medications',
                'followup_scheduled_at',
                'followup_provider_staff_id',
                'discharged_at',
                'discharged_by_staff_id',
            ]);

            // Set discharged by
            $dischargeData['discharged_by_staff_id'] = $userId;

            // Discharge visit via service
            $result = $this->visitService->dischargeVisit($uuid, $dischargeData, $userId);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Transform to resource
            $visit = $result['data'];
            $transformed = new VisitResource($visit);

            return response()->json([
                'success' => true,
                'data' => $transformed,
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to discharge visit', [
                'uuid' => $uuid,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get long waiting visits.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function longWaiting(Request $request): JsonResponse
    {
        try {
            // Validate request
            $request->validate([
                'minutes_threshold' => 'required|integer|min:1',
                'facility_id' => 'nullable|integer|exists:facilities,id',
            ]);

            // Get long waiting visits via service
            $result = $this->visitService->getLongWaitingVisits(
                $request->minutes_threshold,
                $request->facility_id
            );

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve long waiting visits', [
                'threshold' => $request->minutes_threshold,
                'facility_id' => $request->facility_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get visit statistics.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            // Validate request
            $request->validate([
                'facility_id' => 'nullable|integer|exists:facilities,id',
                'date_range' => 'nullable|string',
            ]);

            // Get statistics via service
            $result = $this->visitService->getVisitStatistics(
                $request->facility_id,
                $request->date_range
            );

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve visit statistics', [
                'facility_id' => $request->facility_id,
                'date_range' => $request->date_range,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Start clinical care for a visit.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function startClinicalCare(Request $request, string $uuid): JsonResponse
    {
        try {
            // Get current user ID
            $userId = $request->user()->id;

            // Start clinical care via service
            $result = $this->visitService->startClinicalCare($uuid, $userId);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Transform to resource
            $visit = $result['data'];
            $transformed = new VisitResource($visit);

            return response()->json([
                'success' => true,
                'data' => $transformed,
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to start clinical care', [
                'uuid' => $uuid,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * End clinical care for a visit.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function endClinicalCare(Request $request, string $uuid): JsonResponse
    {
        try {
            // Get current user ID
            $userId = $request->user()->id;

            // End clinical care via service
            $result = $this->visitService->endClinicalCare($uuid, $userId);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Transform to resource
            $visit = $result['data'];
            $transformed = new VisitResource($visit);

            return response()->json([
                'success' => true,
                'data' => $transformed,
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to end clinical care', [
                'uuid' => $uuid,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Cancel a visit.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function cancel(Request $request, string $uuid): JsonResponse
    {
        try {
            // Validate request
            $request->validate([
                'cancellation_reason' => 'required|string|max:1000',
            ]);

            // Get current user ID
            $userId = $request->user()->id;

            // Cancel visit via service
            $result = $this->visitService->cancelVisit($uuid, $request->cancellation_reason, $userId);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Transform to resource
            $visit = $result['data'];
            $transformed = new VisitResource($visit);

            return response()->json([
                'success' => true,
                'data' => $transformed,
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to cancel visit', [
                'uuid' => $uuid,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Register a visit.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function register(Request $request, string $uuid): JsonResponse
    {
        try {
            // Validate request
            $request->validate([
                'registered_at' => 'nullable|date',
                'mode_of_arrival' => 'nullable|string|in:walk_in,ambulance,private_vehicle,police_transport,air_ambulance,wheelchair_transport,transfer_from_facility',
                'accompanying_person' => 'nullable|string|max:200',
                'insurance_preauth_id' => 'nullable|string|max:100',
            ]);

            // Get current user ID
            $userId = $request->user()->id;

            // Prepare registration data
            $registrationData = $request->only([
                'registered_at',
                'mode_of_arrival',
                'accompanying_person',
                'insurance_preauth_id',
            ]);

            // Register visit via service
            $result = $this->visitService->registerVisit($uuid, $registrationData, $userId);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Transform to resource
            $visit = $result['data'];
            $transformed = new VisitResource($visit);

            return response()->json([
                'success' => true,
                'data' => $transformed,
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to register visit', [
                'uuid' => $uuid,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}