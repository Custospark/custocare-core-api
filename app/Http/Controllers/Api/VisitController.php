<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Visit\StoreDischargeRequest;
use App\Http\Requests\Visit\StoreVisitRequest;
use App\Http\Requests\Visit\UpdateDischargeRequest;
use App\Http\Requests\Visit\UpdateVisitRequest;
use App\Http\Resources\DischargeResource;
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
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

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
    private bool $wardBedsHasRoomLabel = false;

    /**
     * Constructor
     *
     * @param VisitServiceInterface $visitService
     */
    public function __construct(VisitServiceInterface $visitService)
    {
        $this->visitService = $visitService;
        $this->wardBedsHasRoomLabel = Schema::hasColumn('ward_beds', 'room_label');
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

            if (!$assignment) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not assigned to this facility.',
                    'errors' => ['facility' => ['No active facility assignment found.']],
                    'data' => [],
                ], 403);
            }

            // 4) Optional filters (aligned with frontend QueueFilters / my-queue query params)
            $filters = $request->validate([
                'current_phase' => 'nullable|in:registration,waiting_triage,triage,waiting_provider,consultation,diagnostic_tests,awaiting_results,treatment,procedures,observation,admission_pending,billing,discharge_pending,discharged,left_without_being_seen,left_against_medical_advice,transferred,admitted,expired',
                'limit' => 'nullable|integer|min:1|max:100',
                'without_ward_assignment' => 'nullable|boolean',
                'department_id' => 'nullable|integer|exists:departments,id',
                'care_delivery_workflow' => ['nullable', 'string', Rule::in(Visit::CARE_DELIVERY_WORKFLOWS)],
            ]);

            $phase = $filters['current_phase'] ?? null;
            $limit = (int) ($filters['limit'] ?? 50);
            $withoutWardAssignment = (bool) ($filters['without_ward_assignment'] ?? false);
            $departmentId = $filters['department_id'] ?? null;
            $careWorkflow = $filters['care_delivery_workflow'] ?? null;

            // 5) Queue: visits assigned to this staff OR (when filtering) visits in the module workflow bucket
            $queueQuery = Visit::query()
                ->where('facility_id', $facilityId)
                ->whereIn('status', ['active', 'in_progress'])
                ->when($departmentId, fn ($q) => $q->where('current_department_id', $departmentId))
                ->when($withoutWardAssignment, function ($q): void {
                    // Visits with no ward/bed in metadata (nursing intake — exclude assigned inpatients)
                    $q->where(function ($w): void {
                        $w->whereNull('metadata')
                            ->orWhereRaw(
                                'CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.nursing_ward_bed.ward_id")),"0") AS UNSIGNED) = 0'
                            );
                    });
                });

            if ($careWorkflow) {
                /** @var list<string> $workflowMatchValues registration is legacy alias for front-desk / records queue */
                $workflowMatchValues = $careWorkflow === 'medical_records'
                    ? ['medical_records', 'registration']
                    : [$careWorkflow];

                $queueQuery->where(function ($outer) use ($staffId, $workflowMatchValues, $phase): void {
                    $outer->where(function ($mine) use ($staffId, $phase): void {
                        $mine->where('assigned_staff_id', $staffId);
                        if ($phase !== null && $phase !== '') {
                            $mine->where('current_phase', $phase);
                        }
                    })->orWhere(function ($inner) use ($workflowMatchValues): void {
                        $inner->whereIn('care_delivery_workflow', $workflowMatchValues)
                            ->whereNull('assigned_staff_id');
                    });
                });
            } else {
                $queueQuery->where('assigned_staff_id', $staffId);
                if ($phase !== null && $phase !== '') {
                    $queueQuery->where('current_phase', $phase);
                }
            }

            $visits = $queueQuery
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
                    'care_delivery_workflow' => $v->care_delivery_workflow,
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
                    'care_delivery_workflow' => $v->care_delivery_workflow,
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
                        'department_id' => $departmentId,
                        'without_ward_assignment' => $withoutWardAssignment,
                        'care_delivery_workflow' => $careWorkflow,
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
     * Completed encounters for the facility (`status = completed`) in the discharge / end date window.
     * Facility from `X-Facility-Id` (also set on every request in `axiosConfig`). No workflow or staff filter.
     */
    public function myCompletedWork(Request $request): JsonResponse
    {
        try {
            $facilityId = (int) $request->header('X-Facility-Id');

            if (! $facilityId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing X-Facility-Id header.',
                    'errors' => ['facility_id' => ['X-Facility-Id header is required.']],
                    'data' => [],
                ], 422);
            }

            $userId = Auth::id();
            $staffId = Staff::query()->where('user_id', $userId)->value('id');

            if (! $staffId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Staff profile not found for this user.',
                    'errors' => ['staff' => ['No staff record is linked to this account.']],
                    'data' => [],
                ], 403);
            }

            $assignment = FacilityStaffRole::query()
                ->where('facility_id', $facilityId)
                ->where('staff_id', $staffId)
                ->where('assignment_status', 'active')
                ->first(['id', 'role_code']);

            if (! $assignment) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not assigned to this facility.',
                    'errors' => ['facility' => ['No active facility assignment found.']],
                    'data' => [],
                ], 403);
            }

            $filters = $request->validate([
                'date_preset' => 'nullable|in:today,this_week,this_month',
                'limit' => 'nullable|integer|min:1|max:100',
            ]);

            $preset = $filters['date_preset'] ?? 'this_week';
            $limit = (int) ($filters['limit'] ?? 100);

            $now = now();
            if ($preset === 'today') {
                $start = $now->copy()->startOfDay();
                $end = $now->copy()->endOfDay();
            } elseif ($preset === 'this_month') {
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
            } else {
                $start = $now->copy()->startOfWeek();
                $end = $now->copy()->endOfWeek();
            }

            $visits = Visit::query()
                ->where('facility_id', $facilityId)
                ->where('status', 'completed')
                ->whereRaw(
                    'COALESCE(discharged_at, clinical_care_ended_at, updated_at) BETWEEN ? AND ?',
                    [$start->toDateTimeString(), $end->toDateTimeString()]
                )
                ->with(['patient.user'])
                ->orderByRaw('COALESCE(discharged_at, clinical_care_ended_at, updated_at) DESC')
                ->limit($limit)
                ->get();

            $patients = $visits
                ->map(fn ($v) => $v->patient)
                ->filter()
                ->unique('id')
                ->values();

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
                    'care_delivery_workflow' => $v->care_delivery_workflow,
                    'discharged_at' => optional($v->discharged_at)->toISOString(),
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => PatientSearchResource::collection($patients),
                'meta' => [
                    'facility_id' => $facilityId,
                    'staff_id' => $staffId,
                    'role_code' => $assignment->role_code,
                    'filters' => [
                        'date_preset' => $preset,
                        'date_from' => $start->toIso8601String(),
                        'date_to' => $end->toIso8601String(),
                    ],
                    'queue_visits' => $queueVisits,
                    'total_visits' => $visits->count(),
                    'total_patients' => $patients->count(),
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to load completed facility visits', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load completed visits.',
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
                'forwarding_kind' => 'nullable|in:staff,workflow',
                'assigned_staff_id' => 'nullable|integer',
                'care_delivery_workflow' => ['nullable', 'string', Rule::in(Visit::CARE_DELIVERY_WORKFLOWS)],
            ]);

            $forwardingKind = $validated['forwarding_kind'] ?? 'staff';
            if (! in_array($forwardingKind, ['staff', 'workflow'], true)) {
                $forwardingKind = 'staff';
            }

            $visitId = (int) $validated['visit_id'];

            // Resolve referring staff from authenticated user
            $referringStaffId = Staff::query()->where('user_id', Auth::id())->value('id');
            if (! $referringStaffId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Staff profile not found for this user.',
                    'errors' => ['staff' => ['No staff record is linked to this account.']],
                    'data' => [],
                ], 403);
            }

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

            if (! $referringActive) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not assigned to this facility.',
                    'errors' => ['facility' => ['No active facility assignment found for the referring staff.']],
                    'data' => [],
                ], 403);
            }

            if ($forwardingKind === 'workflow') {
                $workflow = $validated['care_delivery_workflow'] ?? null;
                if (! $workflow || ! isset(Visit::CARE_DELIVERY_TARGET_PHASES[$workflow])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed.',
                        'errors' => [
                            'care_delivery_workflow' => ['A valid care_delivery_workflow is required when forwarding_kind is workflow.'],
                        ],
                        'data' => [],
                    ], 422);
                }

                $targetPhase = Visit::CARE_DELIVERY_TARGET_PHASES[$workflow];

                $visit = DB::transaction(function () use ($visitId, $facilityId, $referringStaffId, $workflow, $targetPhase) {
                    $visit = Visit::query()->lockForUpdate()->find($visitId);

                    if (! $visit) {
                        return null;
                    }

                    if ((int) $visit->facility_id !== (int) $facilityId) {
                        return 'FACILITY_MISMATCH';
                    }

                    $visit->update([
                        'assigned_staff_id' => null,
                        'assigned_at' => null,
                        'care_delivery_workflow' => $workflow,
                        'current_phase' => $targetPhase,
                        'referring_provider_staff_id' => $referringStaffId,
                    ]);

                    return $visit->fresh();
                });
            } else {
                $assignedStaffId = isset($validated['assigned_staff_id']) ? (int) $validated['assigned_staff_id'] : 0;
                if ($assignedStaffId < 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed.',
                        'errors' => ['assigned_staff_id' => ['assigned_staff_id is required when forwarding to a specific staff member.']],
                        'data' => [],
                    ], 422);
                }

                $staffExists = Staff::query()->whereKey($assignedStaffId)->exists();
                if (! $staffExists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Assigned staff not found.',
                        'errors' => ['assigned_staff_id' => ['No staff record exists for the provided assigned staff.']],
                        'data' => [],
                    ], 404);
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

                if (! $assignedActive) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Assigned staff is not active in this facility.',
                        'errors' => ['assigned_staff_id' => ['Assigned staff has no active assignment in this facility.']],
                        'data' => [],
                    ], 403);
                }

                $presence = StaffPresence::query()
                    ->where('staff_id', $assignedStaffId)
                    ->orderByDesc('updated_at')
                    ->first();

                if (! $presence || ! in_array($presence->status, ['busy', 'on_duty'], true)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Staff is not available for assignment.',
                        'errors' => ['staff_presence' => ['Staff must be in status busy or on_duty to be assigned.']],
                        'data' => [],
                    ], 422);
                }

                $visit = DB::transaction(function () use ($visitId, $facilityId, $assignedStaffId, $referringStaffId) {
                    $visit = Visit::query()->lockForUpdate()->find($visitId);

                    if (! $visit) {
                        return null;
                    }

                    if ((int) $visit->facility_id !== (int) $facilityId) {
                        return 'FACILITY_MISMATCH';
                    }

                    $visit->update([
                        'assigned_staff_id' => $assignedStaffId,
                        'assigned_at' => now(),
                        'referring_provider_staff_id' => $referringStaffId,
                        'care_delivery_workflow' => null,
                    ]);

                    return $visit->fresh();
                });
            }

            if ($visit === 'FACILITY_MISMATCH') {
                return response()->json([
                    'success' => false,
                    'message' => 'Visit does not belong to this facility.',
                    'errors' => ['visit_id' => ['The provided visit_id is not under the current facility scope.']],
                    'data' => [],
                ], 403);
            }

            if (! $visit) {
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
                'message' => $forwardingKind === 'workflow'
                    ? 'Visit routed to module queue successfully.'
                    : 'Patient forwarded successfully.',
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
     * Bulk reassign all active/in-progress visits from the current staff to another.
     * Used by shift handover to transfer patient caseload.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkReassignStaff(Request $request): JsonResponse
    {
        try {
            $facilityId = (int) $request->header('X-Facility-Id');
            if (!$facilityId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing X-Facility-Id header.',
                ], 422);
            }

            $validated = $request->validate([
                'to_staff_id' => 'required|integer|exists:staff,id',
            ]);

            $toStaffId = (int) $validated['to_staff_id'];
            $userId = (int) Auth::id();

            $fromStaffId = Staff::query()->where('user_id', $userId)->value('id');
            if (!$fromStaffId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Staff profile not found for this user.',
                ], 403);
            }

            if ($fromStaffId === $toStaffId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot reassign visits to yourself.',
                ], 422);
            }

            $toStaffHasRole = \App\Models\FacilityStaffRole::query()
                ->where('facility_id', $facilityId)
                ->where('staff_id', $toStaffId)
                ->where('assignment_status', 'active')
                ->exists();

            if (!$toStaffHasRole) {
                return response()->json([
                    'success' => false,
                    'message' => 'The receiving staff does not have an active role at this facility.',
                ], 422);
            }

            $result = $this->visitService->bulkReassignStaff($fromStaffId, $toStaffId, $facilityId);

            return response()->json($result, $result['success'] ? 200 : 500);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Failed to bulk reassign staff', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred.',
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
            'limit' => 'nullable|integer|min:1|max:150',
            'exclude_current_staff' => 'nullable|boolean',
        ]);

        $excludeCurrentStaff = $filters['exclude_current_staff'] ?? true;
        $limit = $filters['limit'] ?? 100;

        $userId = Auth::id();

        $excludeStaffIds = [];
        if ($excludeCurrentStaff && $userId) {
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

                'users.id as user_id',
                DB::raw('MAX(vcounts.current_patient_count) as current_patient_count'),
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

                'users.id',
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
                'user_id' => (int) $staff->user_id,
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

            $staff = Staff::where('user_id', $request->user()->id)->first();
            if (!$staff) {
                return response()->json([
                    'success' => false,
                    'message' => 'Staff record not found for authenticated user',
                ], 404);
            }
            $staffId = $staff->id;

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
     * @param StoreDischargeRequest $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function discharge(StoreDischargeRequest $request, string $uuid): JsonResponse
    {
        try {
            // Resolve staff record from authenticated user
            $staff = Staff::where('user_id', $request->user()->id)->first();
            if (!$staff) {
                return response()->json([
                    'success' => false,
                    'message' => 'Staff record not found for authenticated user.',
                ], 404);
            }
            $staffId = $staff->id;

            // Get validated data
            $dischargeData = $request->validated();

            // Discharge visit via service
            $result = $this->visitService->dischargeVisit($uuid, $dischargeData, $staffId);

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
     * Get discharge data for a visit.
     *
     * @param Visit $visit
     * @return JsonResponse
     */
    public function getDischarge(Visit $visit): JsonResponse
    {
        try {
            $result = $this->visitService->getDischargeData($visit->id);

            if (!$result['success']) {
                return response()->json($result, 404);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to get discharge data', [
                'visit_id' => $visit->id,
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
     * Update discharge data for an already discharged visit.
     *
     * @param UpdateDischargeRequest $request
     * @param Visit $visit
     * @return JsonResponse
     */
    public function updateDischarge(UpdateDischargeRequest $request, Visit $visit): JsonResponse
    {
        try {
            $staff = Staff::where('user_id', $request->user()->id)->first();
            if (!$staff) {
                return response()->json([
                    'success' => false,
                    'message' => 'Staff record not found for authenticated user.',
                ], 404);
            }

            $data = $request->validated();
            $result = $this->visitService->updateDischargeData($visit->id, $data, $staff->id);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json([
                'success' => true,
                'data' => new DischargeResource($result['data']),
                'message' => 'Discharge data updated successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update discharge data', [
                'visit_id' => $visit->id,
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
            $requestedFacilityId = (int) (
                $request->header('X-Facility-Id')
                ?? $request->query('facility_id')
                ?? $request->input('facility_id')
            );

            // Fallback: derive facility from visit UUID when header/context is absent.
            $baseVisit = Visit::query()->where('visit_uuid', $uuid)->first(['id', 'visit_uuid', 'facility_id']);
            $facilityId = $requestedFacilityId > 0
                ? $requestedFacilityId
                : (int) ($baseVisit?->facility_id ?? 0);
            if (!$facilityId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing facility context.',
                    'errors' => ['facility_id' => ['Provide X-Facility-Id header or facility_id query/body value.']],
                    'data' => [],
                ], 422);
            }

            $visit = Visit::query()
                ->where('visit_uuid', $uuid)
                ->where('facility_id', $facilityId)
                ->with(['patient:id,user_id,patient_uuid', 'patient.user:id,first_name,last_name,display_name'])
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
                ->with(['patient.user:id,first_name,last_name,display_name'])
                ->get(['id', 'visit_uuid', 'patient_id', 'metadata', 'updated_at', 'created_at']);

            $occupancyByWard = [];
            $occupiedBedIds = [];
            $occupiedBedMeta = [];

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
                    $patientName = trim((string) (
                        data_get($activeVisit, 'patient.user.display_name')
                        ?: ((data_get($activeVisit, 'patient.user.first_name', '') . ' ' . data_get($activeVisit, 'patient.user.last_name', '')))
                    ));
                    $occupiedBedMeta[$bedId] = [
                        'visit_uuid' => $activeVisit->visit_uuid,
                        'patient_uuid' => data_get($activeVisit, 'patient.patient_uuid'),
                        'patient_name' => $patientName !== '' ? $patientName : null,
                        'occupied_at' => data_get($assignment, 'updated_at')
                            ?: optional($activeVisit->updated_at)?->toISOString()
                            ?: optional($activeVisit->created_at)?->toISOString(),
                    ];
                }
            }

            $currentAssignment = data_get($visit->metadata, 'nursing_ward_bed');
            $currentWardId = (int) data_get($currentAssignment, 'ward_id');
            $currentBedLabel = trim((string) data_get($currentAssignment, 'bed_label', ''));
            $currentRoomLabel = trim((string) data_get($currentAssignment, 'room_label', ''));
            $currentBedId = (int) data_get($currentAssignment, 'bed_id');

            $wardPayload = $wards->map(function (Ward $ward) use (
                $occupancyByWard,
                $currentWardId,
                $visit,
                $facilityId,
                $occupiedBedIds,
                $currentBedId,
                $occupiedBedMeta
            ) {
                $wardId = (int) $ward->id;
                $capacityOperational = (int) ($ward->capacity_operational ?? 0);

                $bedColumns = ['id', 'bed_label', 'status'];
                if ($this->wardBedsHasRoomLabel) {
                    $bedColumns[] = 'room_label';
                }

                $beds = WardBed::query()
                    ->where('ward_id', $wardId)
                    ->whereIn('status', ['available', 'occupied', 'maintenance'])
                    ->orderBy('bed_label')
                    ->get($bedColumns);

                $occupiedBeds = $beds
                    ->filter(function ($bed) use ($occupiedBedIds, $visit, $currentBedId) {
                        if ($currentBedId === (int) $bed->id) {
                            return false;
                        }
                        return isset($occupiedBedIds[(int) $bed->id]) && $occupiedBedIds[(int) $bed->id] !== $visit->visit_uuid;
                    })
                    ->map(function ($bed) use ($occupiedBedMeta) {
                        $bedId = (int) $bed->id;
                        return [
                            'id' => $bed->id,
                            'room_label' => $this->wardBedsHasRoomLabel ? $bed->room_label : null,
                            'bed_label' => $bed->bed_label,
                            'visit_uuid' => data_get($occupiedBedMeta, "{$bedId}.visit_uuid"),
                            'patient_uuid' => data_get($occupiedBedMeta, "{$bedId}.patient_uuid"),
                            'patient_name' => data_get($occupiedBedMeta, "{$bedId}.patient_name"),
                            'occupied_at' => data_get($occupiedBedMeta, "{$bedId}.occupied_at"),
                        ];
                    })
                    ->values();

                $availableBedList = $beds
                    ->filter(function ($bed) use ($occupiedBedIds, $currentBedId) {
                        if ((int) $bed->id === $currentBedId) {
                            return true;
                        }
                        return !isset($occupiedBedIds[(int) $bed->id]) && $bed->status === 'available';
                    })
                    ->map(fn ($bed) => ['id' => $bed->id, 'room_label' => $this->wardBedsHasRoomLabel ? $bed->room_label : null, 'bed_label' => $bed->bed_label])
                    ->values();

                // Use real bed inventory as source of truth; capacity metadata can be outdated.
                $occupied = (int) $occupiedBeds->count();
                $availableBeds = (int) $availableBedList->count();
                if ($capacityOperational <= 0) {
                    $capacityOperational = (int) $beds->count();
                }

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
            })->values();

            Log::info('wardBedOptions result summary', [
                'visit_uuid' => $uuid,
                'auth_user_id' => Auth::id(),
                'requested_facility_id' => $requestedFacilityId ?: null,
                'resolved_facility_id' => $facilityId,
                'wards_returned' => $wardPayload->count(),
                'has_current_ward' => $currentWardId > 0,
            ]);

            $wardById = $wards->keyBy('id');
            $currentWard = $currentWardId > 0 ? $wardById->get($currentWardId) : null;

            $currentPatientName = trim((string) (
                data_get($visit, 'patient.user.display_name')
                ?: ((data_get($visit, 'patient.user.first_name', '') . ' ' . data_get($visit, 'patient.user.last_name', '')))
            ));
            $currentPatientIdentifier = $visit->patient?->patient_uuid;

            return response()->json([
                'success' => true,
                'data' => [
                    'current_location' => [
                        'ward_id' => $currentWardId ?: null,
                        'ward_name' => $currentWard?->name,
                        'bed_id' => $currentBedId ?: null,
                        'room_label' => $currentRoomLabel ?: null,
                        'bed_label' => $currentBedLabel ?: null,
                        'patient_name' => $currentPatientName !== '' ? $currentPatientName : null,
                        'patient_uuid' => $currentPatientIdentifier,
                        'admission_action' => data_get($currentAssignment, 'admission_action'),
                        'transfer_level' => data_get($currentAssignment, 'transfer_level'),
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
            $facilityId = (int) (
                $request->header('X-Facility-Id')
                ?? $request->query('facility_id')
                ?? $request->input('facility_id')
            );
            if (!$facilityId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing facility context.',
                    'errors' => ['facility_id' => ['Provide X-Facility-Id header or facility_id query/body value.']],
                    'data' => [],
                ], 422);
            }

            $validated = $request->validate([
                'ward_id' => ['required', 'integer', 'exists:wards,id'],
                'bed_id' => ['required', 'integer', 'exists:ward_beds,id'],
                'admission_action' => ['required', 'in:admit,assign_bed,transfer'],
                'transfer_level' => ['nullable', 'in:ward,room,bed'],
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
            if (($validated['admission_action'] ?? null) === 'transfer' && empty($validated['transfer_level'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transfer level is required for transfers.',
                    'errors' => ['transfer_level' => ['Choose transfer level: ward, room, or bed.']],
                    'data' => [],
                ], 422);
            }

            $staffId = Staff::query()->where('user_id', Auth::id())->value('id');
            $wardId = (int) $validated['ward_id'];
            $bedId = (int) $validated['bed_id'];
            $action = (string) $validated['admission_action'];

            $visit = DB::transaction(function () use ($facilityId, $uuid, $wardId, $bedId, $action, $validated, $staffId) {
                $visit = Visit::query()
                    ->where('visit_uuid', $uuid)
                    ->where('facility_id', $facilityId)
                    ->lockForUpdate()
                    ->first();

                if (!$visit) {
                    return ['error' => response()->json([
                        'success' => false,
                        'message' => 'Visit not found for this facility.',
                        'data' => [],
                    ], 404)];
                }

                if (in_array($visit->status, ['completed', 'cancelled', 'no_show'], true)) {
                    return ['error' => response()->json([
                        'success' => false,
                        'message' => 'Cannot assign ward/bed for closed visits.',
                        'data' => [],
                    ], 409)];
                }

                $ward = Ward::query()
                    ->where('id', $wardId)
                    ->where('facility_id', $facilityId)
                    ->first();
                if (!$ward) {
                    return ['error' => response()->json([
                        'success' => false,
                        'message' => 'Selected ward does not belong to this facility.',
                        'data' => [],
                    ], 422)];
                }

                $bed = WardBed::query()
                    ->where('id', $bedId)
                    ->where('ward_id', $ward->id)
                    ->where('facility_id', $facilityId)
                    ->lockForUpdate()
                    ->first();
                if (!$bed) {
                    return ['error' => response()->json([
                        'success' => false,
                        'message' => 'Selected bed does not belong to selected ward/facility.',
                        'data' => [],
                    ], 422)];
                }

                if (in_array($bed->status, ['maintenance', 'inactive'], true)) {
                    return ['error' => response()->json([
                        'success' => false,
                        'message' => 'Selected bed is not available for assignment.',
                        'errors' => ['bed_id' => ['Bed is under maintenance or inactive.']],
                        'data' => [],
                    ], 409)];
                }

                $occupiedByAnotherVisit = Visit::query()
                    ->where('facility_id', $facilityId)
                    ->where('visit_uuid', '!=', $uuid)
                    ->whereIn('status', ['active', 'in_progress'])
                    ->whereNotNull('metadata')
                    ->whereRaw("JSON_EXTRACT(metadata, '$.nursing_ward_bed.bed_id') = ?", [$bedId])
                    ->exists();
                if ($occupiedByAnotherVisit) {
                    return ['error' => response()->json([
                        'success' => false,
                        'message' => 'Selected bed is currently occupied.',
                        'errors' => ['bed_id' => ['Please choose another bed.']],
                        'data' => [],
                    ], 409)];
                }

                $metadata = $visit->metadata;
                if (is_string($metadata)) {
                    $decoded = json_decode($metadata, true);
                    $metadata = is_array($decoded) ? $decoded : [];
                } elseif (!is_array($metadata)) {
                    $metadata = [];
                }

                $currentAssignment = data_get($metadata, 'nursing_ward_bed', []);
                $currentBedId = (int) data_get($currentAssignment, 'bed_id');
                $currentWardId = (int) data_get($currentAssignment, 'ward_id');
                $currentRoomLabel = trim((string) data_get($currentAssignment, 'room_label', ''));
                $nextRoomLabel = trim((string) ($this->wardBedsHasRoomLabel ? ($bed->room_label ?? '') : ''));
                $transferLevel = (string) ($validated['transfer_level'] ?? '');
                $effectiveAction = $action;

                if ($currentBedId > 0 && ($currentWardId !== $wardId || $currentBedId !== $bedId)) {
                    $effectiveAction = 'transfer';
                    if ($transferLevel === '') {
                        if ($currentWardId !== $wardId) {
                            $transferLevel = 'ward';
                        } elseif ($currentRoomLabel !== '' && $nextRoomLabel !== '' && $currentRoomLabel !== $nextRoomLabel) {
                            $transferLevel = 'room';
                        } else {
                            $transferLevel = 'bed';
                        }
                    }
                }

                if ($effectiveAction === 'transfer' && $currentBedId <= 0) {
                    return ['error' => response()->json([
                        'success' => false,
                        'message' => 'Cannot transfer before initial ward/bed assignment.',
                        'errors' => ['admission_action' => ['Assign/admit bed first before transfer.']],
                        'data' => [],
                    ], 422)];
                }

                if ($effectiveAction === 'transfer' && $currentWardId === $wardId && $currentBedId === $bedId) {
                    return ['error' => response()->json([
                        'success' => false,
                        'message' => 'Transfer target must be a different ward or bed.',
                        'errors' => ['bed_id' => ['Select a different destination bed for transfer.']],
                        'data' => [],
                    ], 422)];
                }
                if ($effectiveAction === 'transfer') {
                    if ($transferLevel === 'ward' && $currentWardId === $wardId) {
                        return ['error' => response()->json([
                            'success' => false,
                            'message' => 'Ward-level transfer requires selecting a different ward.',
                            'errors' => ['transfer_level' => ['Select a bed from a different ward.']],
                            'data' => [],
                        ], 422)];
                    }
                    if ($transferLevel === 'room') {
                        if ($currentWardId !== $wardId) {
                            return ['error' => response()->json([
                                'success' => false,
                                'message' => 'Room-level transfer must stay within the same ward.',
                                'errors' => ['transfer_level' => ['Select a bed in the same ward for room transfer.']],
                                'data' => [],
                            ], 422)];
                        }
                        if ($currentRoomLabel === '' || $nextRoomLabel === '' || $currentRoomLabel === $nextRoomLabel) {
                            return ['error' => response()->json([
                                'success' => false,
                                'message' => 'Room-level transfer requires a different room.',
                                'errors' => ['transfer_level' => ['Select a bed in a different room.']],
                                'data' => [],
                            ], 422)];
                        }
                    }
                    if ($transferLevel === 'bed' && $currentBedId === $bedId) {
                        return ['error' => response()->json([
                            'success' => false,
                            'message' => 'Bed-level transfer requires a different bed.',
                            'errors' => ['transfer_level' => ['Select a different destination bed.']],
                            'data' => [],
                        ], 422)];
                    }
                }

                if ($currentBedId > 0 && $currentBedId !== $bedId) {
                    $previousBed = WardBed::query()
                        ->whereKey($currentBedId)
                        ->whereHas('ward', function ($query) use ($facilityId) {
                            $query->where('facility_id', $facilityId);
                        })
                        ->lockForUpdate()
                        ->first();
                    if ($previousBed && !in_array($previousBed->status, ['maintenance', 'inactive'], true)) {
                        $previousBed->update([
                            'status' => 'available',
                            'updated_by_user_id' => Auth::id(),
                        ]);
                    }
                }

                $bed->update([
                    'status' => 'occupied',
                    'updated_by_user_id' => Auth::id(),
                ]);

                $metadata['nursing_ward_bed'] = [
                    'ward_id' => $ward->id,
                    'ward_name' => $ward->name,
                    'bed_id' => $bed->id,
                    'room_label' => $this->wardBedsHasRoomLabel ? $bed->room_label : null,
                    'bed_label' => $bed->bed_label,
                    'admission_action' => $effectiveAction,
                    'transfer_level' => $effectiveAction === 'transfer' ? $transferLevel : null,
                    'transfer_reason' => $effectiveAction === 'transfer'
                        ? trim((string) ($validated['transfer_reason'] ?? '')) ?: 'Ward/bed assignment updated.'
                        : null,
                    'updated_at' => now()->toISOString(),
                    'updated_by_staff_id' => $staffId,
                ];

                $phase = $visit->current_phase;
                if ($effectiveAction === 'transfer') {
                    $phase = 'transferred';
                } elseif (in_array($effectiveAction, ['admit', 'assign_bed'], true)) {
                    $phase = 'admitted';
                }

                $visit->update([
                    'metadata' => $metadata,
                    'current_phase' => $phase,
                    'updated_by_staff_id' => $staffId,
                ]);

                return ['visit' => $visit->fresh()];
            });

            if (isset($visit['error'])) {
                return $visit['error'];
            }

            return response()->json([
                'success' => true,
                'data' => new VisitResource($visit['visit']),
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

    /**
     * Release current visit from its assigned ward/bed.
     */
    public function releaseWardBed(Request $request, string $uuid): JsonResponse
    {
        try {
            $facilityId = (int) (
                $request->header('X-Facility-Id')
                ?? $request->query('facility_id')
                ?? $request->input('facility_id')
            );
            if (!$facilityId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing facility context.',
                    'errors' => ['facility_id' => ['Provide X-Facility-Id header or facility_id query/body value.']],
                    'data' => [],
                ], 422);
            }

            $validated = $request->validate([
                'bed_id' => ['nullable', 'integer', 'exists:ward_beds,id'],
            ]);

            $staffId = Staff::query()->where('user_id', Auth::id())->value('id');

            $result = DB::transaction(function () use ($uuid, $facilityId, $validated, $staffId) {
                $visit = Visit::query()
                    ->where('visit_uuid', $uuid)
                    ->where('facility_id', $facilityId)
                    ->lockForUpdate()
                    ->first();

                if (!$visit) {
                    return ['error' => response()->json([
                        'success' => false,
                        'message' => 'Visit not found for this facility.',
                        'data' => [],
                    ], 404)];
                }

                $metadata = $visit->metadata;
                if (is_string($metadata)) {
                    $decoded = json_decode($metadata, true);
                    $metadata = is_array($decoded) ? $decoded : [];
                } elseif (!is_array($metadata)) {
                    $metadata = [];
                }

                $assignment = data_get($metadata, 'nursing_ward_bed', []);
                $assignedBedId = (int) data_get($assignment, 'bed_id');
                if ($assignedBedId <= 0) {
                    return ['error' => response()->json([
                        'success' => false,
                        'message' => 'No current ward/bed assignment to release.',
                        'data' => [],
                    ], 409)];
                }

                $requestedBedId = (int) ($validated['bed_id'] ?? 0);
                if ($requestedBedId > 0 && $requestedBedId !== $assignedBedId) {
                    return ['error' => response()->json([
                        'success' => false,
                        'message' => 'Selected bed is not the current assigned bed for this visit.',
                        'errors' => ['bed_id' => ['Select the currently assigned occupied bed to release.']],
                        'data' => [],
                    ], 422)];
                }

                $assignedBed = WardBed::query()
                    ->whereKey($assignedBedId)
                    ->whereHas('ward', function ($query) use ($facilityId) {
                        $query->where('facility_id', $facilityId);
                    })
                    ->lockForUpdate()
                    ->first();
                if (!$assignedBed) {
                    return ['error' => response()->json([
                        'success' => false,
                        'message' => 'Assigned bed was not found for this facility.',
                        'errors' => ['bed_id' => ['Bed record is missing or not in this facility.']],
                        'data' => [],
                    ], 422)];
                }
                if (!in_array($assignedBed->status, ['maintenance', 'inactive'], true)) {
                    $assignedBed->update([
                        'status' => 'available',
                        'updated_by_user_id' => Auth::id(),
                    ]);
                }

                unset($metadata['nursing_ward_bed']);

                $visit->update([
                    'metadata' => $metadata,
                    'updated_by_staff_id' => $staffId,
                ]);

                return ['visit' => $visit->fresh()];
            });

            if (isset($result['error'])) {
                return $result['error'];
            }

            return response()->json([
                'success' => true,
                'data' => new VisitResource($result['visit']),
                'message' => 'Ward/bed released successfully.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
                'data' => [],
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Failed to release ward/bed for visit.', [
                'visit_uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to release ward and bed.',
            ], 500);
        }
    }
}