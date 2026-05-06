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
use App\Models\Ward;
use App\Models\WardBed;
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
    private const ALLOWED_WARD_BED_ACTIONS = ['admit', 'assign_bed', 'transfer'];
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
                // ->when($phase, fn ($q) => $q->where('current_phase', $phase))
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

    
    try {
        // Get validated data
        $validatedData = $request->validated();

        // Get current staff ID for audit - FIX: extract just the ID
        $staff = Staff::where('user_id', Auth::id())->first();
        
        // Check if staff exists
        if (!$staff) {
            return response()->json([
                'success' => false,
                'message' => 'Staff record not found for authenticated user',
            ], 404);
        }
        
        $staffId = $staff->id; // Extract the integer ID

        // Update visit via service
        $result = $this->visitService->updateVisit($uuid, $validatedData, $staffId);

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
    * Forwarding patient to another staff member within the same facility.
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
                'message' => 'Patient forwarded successfully.',
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
    try {
        $facilityId = (int) $request->header('X-Facility-Id');

        if (!$facilityId) {
            return response()->json([
                'success' => false,
                'message' => 'Missing X-Facility-Id header.',
                'errors' => [
                    'facility_id' => ['X-Facility-Id header is required.'],
                ],
                'data' => [],
            ], 422);
        }

        // Normalize boolean input
        if ($request->has('exclude_current_staff')) {
            $request->merge([
                'exclude_current_staff' => filter_var(
                    $request->input('exclude_current_staff'),
                    FILTER_VALIDATE_BOOLEAN
                ),
            ]);
        }

        $filters = $request->validate([
            'role_code' => 'nullable|string',
            'department_id' => 'nullable|integer',
            'presence_status' => 'nullable|in:on_duty,busy',
            'search' => 'nullable|string|max:100',
            'limit' => 'nullable|integer|min:1|max:100',
            'exclude_current_staff' => 'nullable|boolean',
        ]);

        $excludeCurrentStaff = $filters['exclude_current_staff'] ?? true;
        $limit = $filters['limit'] ?? 100;

        $userId = Auth::id();

        $excludeStaffIds = [];
        if ($excludeCurrentStaff || $userId) {
            $excludeStaffIds = Staff::query()
                ->where('user_id', $userId)
                ->pluck('id')
                ->toArray();
        }

        /**
         * Visit counts per staff.
         * In your system, visit count = patient count for workload.
         */
        $visitCountSubQuery = DB::table('visits as v')
            ->selectRaw('v.assigned_staff_id, COUNT(v.id) as current_patient_count')
            ->where('v.facility_id', $facilityId)
            ->whereNotNull('v.assigned_staff_id')
            ->whereNull('v.deleted_at')
            ->whereNull('v.cancelled_at')
            ->whereIn('v.status', ['active', 'in_progress'])
            ->groupBy('v.assigned_staff_id');

        $staffList = Staff::query()
            ->select([
                'staff.id',
                'staff.staff_uuid',
                'staff.employee_id',
                'staff.professional_title',
                'staff.global_role_level',
                'staff.max_concurrent_patients',
                'staff.total_patients_treated',

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

                DB::raw('COALESCE(vcounts.current_patient_count, 0) as current_patient_count'),
            ])
            ->join('users', 'staff.user_id', '=', 'users.id')
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
            ->leftJoin('staff_presences as sp', function ($join) use ($facilityId) {
                $join->on('staff.id', '=', 'sp.staff_id')
                    ->where('sp.facility_id', $facilityId)
                    ->whereNull('sp.ended_at');
            })
            ->leftJoin('staff_space_assignments as ssa', function ($join) use ($facilityId) {
                $join->on('staff.id', '=', 'ssa.staff_id')
                    ->where('ssa.facility_id', $facilityId)
                    ->whereNull('ssa.released_at');
            })
            ->leftJoin('facility_spaces as fs', 'ssa.space_id', '=', 'fs.id')
            ->leftJoinSub($visitCountSubQuery, 'vcounts', function ($join) {
                $join->on('staff.id', '=', 'vcounts.assigned_staff_id');
            })
            ->when(!empty($excludeStaffIds), function ($query) use ($excludeStaffIds) {
                $query->whereNotIn('staff.id', $excludeStaffIds);
            })
            ->when($filters['role_code'] ?? null, function ($query, $roleCode) {
                $query->where('fsr.role_code', $roleCode);
            })
            ->when($filters['department_id'] ?? null, function ($query, $departmentId) {
                $query->whereJsonContains('fsr.department_ids', (int) $departmentId);
            })
            ->when($filters['presence_status'] ?? null, function ($query, $presenceStatus) {
                $query->where('sp.status', $presenceStatus);
            }, function ($query) {
                $query->whereIn('sp.status', ['on_duty', 'busy']);
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('users.first_name', 'LIKE', "%{$search}%")
                        ->orWhere('users.last_name', 'LIKE', "%{$search}%")
                        ->orWhere('users.display_name', 'LIKE', "%{$search}%")
                        ->orWhere('staff.employee_id', 'LIKE', "%{$search}%");
                });
            })
            ->groupBy([
                'staff.id',
                'staff.staff_uuid',
                'staff.employee_id',
                'staff.professional_title',
                'staff.global_role_level',
                'staff.max_concurrent_patients',
                'staff.total_patients_treated',

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

                'vcounts.current_patient_count',
            ])
            ->orderBy('users.first_name')
            ->orderBy('users.last_name')
            ->limit($limit)
            ->get();

        $transformedStaff = $staffList->map(function ($staff) {
            $currentPatientCount = (int) $staff->current_patient_count;
            $maxConcurrentPatients = (int) $staff->max_concurrent_patients;

            $availability = $this->determineStaffAvailability(
                $staff->presence_status,
                $currentPatientCount,
                $maxConcurrentPatients
            );

            return [
                'staff_id' => $staff->id,
                'staff_uuid' => $staff->staff_uuid,
                'employee_id' => $staff->employee_id,
                'professional_title' => $staff->professional_title,
                'global_role_level' => $staff->global_role_level,

                'first_name' => $staff->first_name,
                'last_name' => $staff->last_name,
                'display_name' => $staff->display_name,
                'full_name' => trim(($staff->first_name ?? '') . ' ' . ($staff->last_name ?? '')),

                'role_code' => $staff->role_code,
                'module_code' => $staff->module_code,
                'department_ids' => $staff->department_ids,

                'presence_status' => $staff->presence_status,
                'presence_started_at' => $staff->presence_started_at,

                'is_available' => $availability['is_available'],
                'can_receive_patients' => $availability['can_receive_patients'],
                'availability_reason' => $availability['reason'],

                'current_space' => $staff->current_space_name ? [
                    'name' => $staff->current_space_name,
                    'type' => $staff->current_space_type,
                    'floor' => $staff->current_space_floor,
                ] : null,

                'max_concurrent_patients' => $maxConcurrentPatients,
                'current_patient_count' => $currentPatientCount,
                'total_patients_treated' => (int) $staff->total_patients_treated,

                'workload_percentage' => $maxConcurrentPatients > 0
                    ? round(($currentPatientCount / $maxConcurrentPatients) * 100, 2)
                    : 0,

                'has_capacity' => $maxConcurrentPatients > 0
                    ? $currentPatientCount < $maxConcurrentPatients
                    : true,

                'remaining_capacity' => $maxConcurrentPatients > 0
                    ? max(0, $maxConcurrentPatients - $currentPatientCount)
                    : 0,
            ];
        });

        $groupedStaff = [
            'available' => $transformedStaff
                ->where('presence_status', 'on_duty')
                ->where('can_receive_patients', true)
                ->values(),

            'busy' => $transformedStaff
                ->where('presence_status', 'busy')
                ->where('can_receive_patients', true)
                ->values(),

            'at_capacity' => $transformedStaff
                ->where('has_capacity', false)
                ->values(),

            'other' => $transformedStaff
                ->filter(function ($staff) {
                    return !$staff['can_receive_patients'] && $staff['has_capacity'];
                })
                ->values(),
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
                    'at_capacity' => $groupedStaff['at_capacity']->count(),
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

    } catch (\Throwable $e) {
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
 * Determine if a staff member is available for patient assignment
 */
private function determineStaffAvailability($presenceStatus, int $currentPatientCount, int $maxConcurrentPatients): array
{
    if (!in_array($presenceStatus, ['on_duty', 'busy'], true)) {
        return [
            'is_available' => false,
            'can_receive_patients' => false,
            'reason' => 'Staff is not currently on duty',
        ];
    }

    if ($maxConcurrentPatients > 0 && $currentPatientCount >= $maxConcurrentPatients) {
        return [
            'is_available' => false,
            'can_receive_patients' => false,
            'reason' => "Staff has reached maximum patient capacity ({$currentPatientCount}/{$maxConcurrentPatients})",
        ];
    }

    if ($presenceStatus === 'busy') {
        return [
            'is_available' => false,
            'can_receive_patients' => true,
            'reason' => 'Staff is currently busy but can accept more patients',
        ];
    }

    return [
        'is_available' => true,
        'can_receive_patients' => true,
        'reason' => 'Staff is available to take new patients',
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
            $staffId = Staff::where('user_id',$request->user()->id)->first()->id;

            // Delete visit via service
            $result = $this->visitService->deleteVisit($uuid, $staffId);

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
            $staffId = Staff::where('user_id',$request->user()->id)->first()->id;

            // Update status via service
            $result = $this->visitService->updateVisitStatus(
                $uuid,
                $request->status,
                $request->additional_data ?? [],
                $staffId
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
            $staffId = Staff::where('user_id',$request->user()->id)->first()->id;

            // Cancel visit via service
            $result = $this->visitService->cancelVisit($uuid, $request->cancellation_reason, $staffId);

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

    /**
     * Return ward/bed options and current visit location context for nursing assignment.
     */
    public function wardBedOptions(Request $request, string $uuid): JsonResponse
    {
        try {
            $facilityId = (int) $request->header('X-Facility-Id');
            if (!$facilityId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing X-Facility-Id header.',
                    'errors' => ['facility_id' => ['X-Facility-Id header is required.']],
                    'data' => [],
                ], 422);
            }

            $visit = Visit::query()
                ->where('visit_uuid', $uuid)
                ->where('facility_id', $facilityId)
                ->first();

            if (!$visit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Visit not found for this facility.',
                    'data' => [],
                ], 404);
            }

            $wards = Ward::query()
                ->where('facility_id', $facilityId)
                ->where('status', 'active')
                ->orderBy('name')
                ->get();

            $activeVisits = Visit::query()
                ->where('facility_id', $facilityId)
                ->whereIn('status', ['active', 'in_progress'])
                ->whereNull('deleted_at')
                ->get(['id', 'visit_uuid', 'metadata']);

            $occupancyByWard = [];
            $occupiedBedIds = [];

            foreach ($activeVisits as $activeVisit) {
                $assignment = data_get($activeVisit->metadata, 'nursing_ward_bed');
                $wardId = (int) data_get($assignment, 'ward_id');
                $bedId = (int) data_get($assignment, 'bed_id');

                if ($wardId <= 0) {
                    continue;
                }

                $occupancyByWard[$wardId] = ($occupancyByWard[$wardId] ?? 0) + 1;

                if ($bedId > 0) {
                    $occupiedBedIds[$bedId] = $activeVisit->visit_uuid;
                }
            }

            $currentAssignment = data_get($visit->metadata, 'nursing_ward_bed');
            $currentWardId = (int) data_get($currentAssignment, 'ward_id');
            $currentBedLabel = trim((string) data_get($currentAssignment, 'bed_label', ''));
            $currentBedId = (int) data_get($currentAssignment, 'bed_id');

            $wardPayload = $wards->map(function (Ward $ward) use (
                $occupancyByWard,
                $currentWardId,
                $visit,
                $facilityId,
                $occupiedBedIds,
                $currentBedId
            ) {
                $wardId = (int) $ward->id;
                $capacityOperational = (int) ($ward->capacity_operational ?? 0);
                $occupied = (int) ($occupancyByWard[$wardId] ?? 0);

                if ($currentWardId === $wardId) {
                    $occupied = max(0, $occupied - 1);
                }

                $availableBeds = max(0, $capacityOperational - $occupied);

                $beds = WardBed::query()
                    ->where('facility_id', $facilityId)
                    ->where('ward_id', $wardId)
                    ->whereIn('status', ['available', 'occupied', 'maintenance'])
                    ->orderBy('bed_label')
                    ->get(['id', 'bed_label', 'status']);

                $occupiedBeds = $beds
                    ->filter(function ($bed) use ($occupiedBedIds, $visit, $currentBedId) {
                        if ($currentBedId === (int) $bed->id) {
                            return false;
                        }
                        return isset($occupiedBedIds[(int) $bed->id]) && $occupiedBedIds[(int) $bed->id] !== $visit->visit_uuid;
                    })
                    ->map(fn ($bed) => ['id' => $bed->id, 'bed_label' => $bed->bed_label])
                    ->values();

                $availableBedList = $beds
                    ->filter(function ($bed) use ($occupiedBedIds, $currentBedId) {
                        if ((int) $bed->id === $currentBedId) {
                            return true;
                        }
                        return !isset($occupiedBedIds[(int) $bed->id]) && $bed->status === 'available';
                    })
                    ->map(fn ($bed) => ['id' => $bed->id, 'bed_label' => $bed->bed_label])
                    ->values();

                return [
                    'id' => $ward->id,
                    'name' => $ward->name,
                    'code' => $ward->code,
                    'ward_type' => $ward->ward_type,
                    'building' => $ward->building,
                    'floor' => $ward->floor,
                    'capacity_operational' => $capacityOperational,
                    'occupied_beds' => $occupied,
                    'available_beds' => $availableBeds,
                    'occupied_bed_labels' => $occupiedBeds,
                    'available_bed_list' => $availableBedList,
                ];
            })
                ->filter(function (array $ward) use ($currentWardId) {
                    if (($ward['id'] ?? null) === $currentWardId) {
                        return true;
                    }
                    return (($ward['available_beds'] ?? 0) > 0);
                })
                ->values();

            $wardById = $wards->keyBy('id');
            $currentWard = $currentWardId > 0 ? $wardById->get($currentWardId) : null;

            return response()->json([
                'success' => true,
                'data' => [
                    'current_location' => [
                        'ward_id' => $currentWardId ?: null,
                        'ward_name' => $currentWard?->name,
                        'bed_id' => $currentBedId ?: null,
                        'bed_label' => $currentBedLabel ?: null,
                        'admission_action' => data_get($currentAssignment, 'admission_action'),
                        'transfer_reason' => data_get($currentAssignment, 'transfer_reason'),
                        'updated_at' => data_get($currentAssignment, 'updated_at'),
                    ],
                    'wards' => $wardPayload,
                ],
                'message' => 'Ward and bed options loaded successfully.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to load ward/bed options for visit.', [
                'visit_uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load ward and bed options.',
            ], 500);
        }
    }

    /**
     * Assign/admit/transfer a visit to a ward and bed (nursing workflow).
     */
    public function assignWardBed(Request $request, string $uuid): JsonResponse
    {
        try {
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
                'ward_id' => ['required', 'integer', 'exists:wards,id'],
                'bed_id' => ['required', 'integer', 'exists:ward_beds,id'],
                'admission_action' => ['required', 'in:admit,assign_bed,transfer'],
                'transfer_reason' => ['nullable', 'string', 'max:500'],
            ]);

            if (
                ($validated['admission_action'] ?? null) === 'transfer'
                && empty(trim((string) ($validated['transfer_reason'] ?? '')))
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transfer reason is required when transferring a patient.',
                    'errors' => ['transfer_reason' => ['Please provide a reason for transfer.']],
                    'data' => [],
                ], 422);
            }

            $visit = Visit::query()
                ->where('visit_uuid', $uuid)
                ->where('facility_id', $facilityId)
                ->first();

            if (!$visit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Visit not found for this facility.',
                    'data' => [],
                ], 404);
            }

            if (in_array($visit->status, ['completed', 'cancelled', 'no_show'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot assign ward/bed for closed visits.',
                    'data' => [],
                ], 409);
            }

            $ward = Ward::query()
                ->where('id', (int) $validated['ward_id'])
                ->where('facility_id', $facilityId)
                ->first();

            if (!$ward) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected ward does not belong to this facility.',
                    'data' => [],
                ], 422);
            }

            $bed = WardBed::query()
                ->where('id', (int) $validated['bed_id'])
                ->where('ward_id', $ward->id)
                ->where('facility_id', $facilityId)
                ->first();

            if (!$bed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected bed does not belong to selected ward/facility.',
                    'data' => [],
                ], 422);
            }

            $occupiedByAnotherVisit = Visit::query()
                ->where('facility_id', $facilityId)
                ->where('visit_uuid', '!=', $uuid)
                ->whereIn('status', ['active', 'in_progress'])
                ->whereNotNull('metadata')
                ->whereRaw("JSON_EXTRACT(metadata, '$.nursing_ward_bed.bed_id') = ?", [(int) $bed->id])
                ->exists();

            if ($occupiedByAnotherVisit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected bed is currently occupied.',
                    'errors' => ['bed_id' => ['Please choose another bed.']],
                    'data' => [],
                ], 409);
            }

            $staffId = Staff::query()->where('user_id', Auth::id())->value('id');

            $metadata = $visit->metadata ?? [];
            $metadata['nursing_ward_bed'] = [
                'ward_id' => $ward->id,
                'ward_name' => $ward->name,
                'bed_id' => $bed->id,
                'bed_label' => $bed->bed_label,
                'admission_action' => $validated['admission_action'],
                'transfer_reason' => $validated['admission_action'] === 'transfer'
                    ? trim((string) ($validated['transfer_reason'] ?? ''))
                    : null,
                'updated_at' => now()->toISOString(),
                'updated_by_staff_id' => $staffId,
            ];

            $phase = $visit->current_phase;
            if ($validated['admission_action'] === 'transfer') {
                $phase = 'transferred';
            } elseif (in_array($validated['admission_action'], ['admit', 'assign_bed'], true)) {
                $phase = 'admitted';
            }

            $visit->update([
                'metadata' => $metadata,
                'current_phase' => $phase,
                'updated_by_staff_id' => $staffId,
            ]);

            return response()->json([
                'success' => true,
                'data' => new VisitResource($visit->fresh()),
                'message' => 'Ward and bed assignment saved successfully.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
                'data' => [],
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Failed to assign ward/bed to visit.', [
                'visit_uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign ward and bed.',
            ], 500);
        }
    }
}