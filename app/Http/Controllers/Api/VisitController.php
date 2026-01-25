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
use App\Models\Visit;
use App\Services\Contracts\VisitServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
                ->whereDate('effective_from', '<=', now()->toDateString())
                ->where(function ($q) {
                    $q->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', now()->toDateString());
                })
                ->first(['id', 'role_code']);

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
            $userId = $request->user()->id;

            // Create visit via service
            $result = $this->visitService->createVisit($validatedData, $userId);

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

            // Get current user ID for audit
            $userId = $request->user()->id;

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
            $this->authorize('delete', \App\Models\Visit::class);

            // Get current user ID
            $userId = $request->user()->id;

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