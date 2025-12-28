<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Prescription\StorePrescriptionRequest;
use App\Http\Requests\Prescription\UpdatePrescriptionRequest;
use App\Http\Resources\PrescriptionResource;
use App\Services\Contracts\PrescriptionServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class PrescriptionController extends Controller
{
    /**
     * @var PrescriptionServiceInterface
     */
    protected $prescriptionService;

    /**
     * Constructor
     *
     * @param PrescriptionServiceInterface $prescriptionService
     */
    public function __construct(PrescriptionServiceInterface $prescriptionService)
    {
        $this->prescriptionService = $prescriptionService;
        
        // Apply middleware
        // $this->middleware('auth:api');
        // $this->middleware('permission:view prescriptions')->only(['index', 'show']);
        // $this->middleware('permission:create prescriptions')->only(['store']);
        // $this->middleware('permission:edit prescriptions')->only(['update']);
        // $this->middleware('permission:delete prescriptions')->only(['destroy']);
    }

    /**
     * Display a listing of prescriptions.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'patient_id', 'provider_id', 'facility_id', 'status',
                'dispense_status', 'is_high_risk', 'controlled_substance',
                'date_from', 'date_to'
            ]);
            
            $perPage = $request->input('per_page', 20);
            
            $prescriptions = $this->prescriptionService->getAllPrescriptions($filters, $perPage);
            
            return response()->json([
                'success' => true,
                'data' => PrescriptionResource::collection($prescriptions),
                'meta' => [
                    'current_page' => $prescriptions->currentPage(),
                    'total_pages' => $prescriptions->lastPage(),
                    'total_items' => $prescriptions->total(),
                    'per_page' => $prescriptions->perPage(),
                    'has_more_pages' => $prescriptions->hasMorePages(),
                ],
                'links' => [
                    'first' => $prescriptions->url(1),
                    'last' => $prescriptions->url($prescriptions->lastPage()),
                    'prev' => $prescriptions->previousPageUrl(),
                    'next' => $prescriptions->nextPageUrl(),
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to retrieve prescriptions list', [
                'user_id' => auth::id()(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve prescriptions. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created prescription.
     *
     * @param StorePrescriptionRequest $request
     * @return JsonResponse
     */
    public function store(StorePrescriptionRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            
            $prescription = $this->prescriptionService->createPrescription($validatedData);
            
            return response()->json([
                'success' => true,
                'message' => 'Prescription created successfully.',
                'data' => new PrescriptionResource($prescription->load([
                    'patient', 'prescribingProvider', 'inventoryItem', 'visit', 'facility'
                ])),
            ], Response::HTTP_CREATED);
            
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
            
        } catch (\RuntimeException $e) {
            Log::error('Failed to create prescription', [
                'user_id' => auth::id()(),
                'data' => $this->sanitizeRequestData($request->all()),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
            
        } catch (\Exception $e) {
            Log::error('Unexpected error creating prescription', [
                'user_id' => auth::id()(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified prescription.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            $prescription = $this->prescriptionService->getPrescriptionByUuid($uuid);
            
            return response()->json([
                'success' => true,
                'data' => new PrescriptionResource($prescription->load([
                    'patient', 'prescribingProvider', 'inventoryItem', 'visit', 
                    'facility', 'createdBy', 'discontinuedBy'
                ])),
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Prescription not found.',
            ], Response::HTTP_NOT_FOUND);
            
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_FORBIDDEN);
            
        } catch (\Exception $e) {
            Log::error('Failed to retrieve prescription', [
                'uuid' => $uuid,
                'user_id' => auth::id()(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve prescription. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified prescription.
     *
     * @param UpdatePrescriptionRequest $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function update(UpdatePrescriptionRequest $request, string $uuid): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            
            $prescription = $this->prescriptionService->updatePrescription($uuid, $validatedData);
            
            return response()->json([
                'success' => true,
                'message' => 'Prescription updated successfully.',
                'data' => new PrescriptionResource($prescription->load([
                    'patient', 'prescribingProvider', 'inventoryItem'
                ])),
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Prescription not found.',
            ], Response::HTTP_NOT_FOUND);
            
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
            
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_FORBIDDEN);
            
        } catch (\Exception $e) {
            Log::error('Failed to update prescription', [
                'uuid' => $uuid,
                'user_id' => auth::id()(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unable to update prescription. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified prescription (soft delete).
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $deleted = $this->prescriptionService->deletePrescription($uuid);
            
            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Prescription deleted successfully.',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to delete prescription. It may have been transmitted or dispensed.',
                ], Response::HTTP_BAD_REQUEST);
            }
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Prescription not found.',
            ], Response::HTTP_NOT_FOUND);
            
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
            
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_FORBIDDEN);
            
        } catch (\Exception $e) {
            Log::error('Failed to delete prescription', [
                'uuid' => $uuid,
                'user_id' => auth::id()(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unable to delete prescription. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Process prescription refill
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function refill(Request $request, string $uuid): JsonResponse
    {
        try {
            $this->authorize('refill', \App\Models\Prescription::class);
            
            $validated = $request->validate([
                'pharmacy_ncpdp_id' => 'nullable|string|max:20',
                'refill_number' => 'nullable|integer|min:1',
                'notes' => 'nullable|string|max:500',
            ]);
            
            $prescription = $this->prescriptionService->processRefill($uuid, $validated);
            
            return response()->json([
                'success' => true,
                'message' => 'Prescription refill processed successfully.',
                'data' => new PrescriptionResource($prescription->load(['patient', 'prescribingProvider'])),
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Prescription not found.',
            ], Response::HTTP_NOT_FOUND);
            
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
            
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_FORBIDDEN);
            
        } catch (\Exception $e) {
            Log::error('Failed to process prescription refill', [
                'uuid' => $uuid,
                'user_id' => auth::id()(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unable to process refill. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update dispense status
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function updateDispenseStatus(Request $request, string $uuid): JsonResponse
    {
        try {
            $this->authorize('updateDispenseStatus', \App\Models\Prescription::class);
            
            $validated = $request->validate([
                'status' => 'required|in:pending,transmitted,received_by_pharmacy,in_progress,ready_for_pickup,dispensed,not_picked_up,cancelled,discontinued',
                'metadata' => 'nullable|array',
                'notes' => 'nullable|string|max:500',
            ]);
            
            $prescription = $this->prescriptionService->updateDispenseStatus(
                $uuid, 
                $validated['status'], 
                $validated['metadata'] ?? []
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Dispense status updated successfully.',
                'data' => new PrescriptionResource($prescription->load(['patient', 'prescribingProvider'])),
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Prescription not found.',
            ], Response::HTTP_NOT_FOUND);
            
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
            
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_FORBIDDEN);
            
        } catch (\Exception $e) {
            Log::error('Failed to update dispense status', [
                'uuid' => $uuid,
                'status' => $request->input('status'),
                'user_id' => auth::id()(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unable to update dispense status. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Discontinue prescription
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function discontinue(Request $request, string $uuid): JsonResponse
    {
        try {
            $this->authorize('discontinue', \App\Models\Prescription::class);
            
            $validated = $request->validate([
                'reason' => 'required|string|min:5|max:500',
                'discontinued_by_staff_id' => 'nullable|exists:staff,id',
            ]);
            
            $prescription = $this->prescriptionService->discontinuePrescription(
                $uuid,
                $validated['reason'],
                $validated['discontinued_by_staff_id'] ?? null
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Prescription discontinued successfully.',
                'data' => new PrescriptionResource($prescription->load(['patient', 'prescribingProvider', 'discontinuedBy'])),
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Prescription not found.',
            ], Response::HTTP_NOT_FOUND);
            
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
            
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_FORBIDDEN);
            
        } catch (\Exception $e) {
            Log::error('Failed to discontinue prescription', [
                'uuid' => $uuid,
                'user_id' => auth::id()(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unable to discontinue prescription. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Check refill eligibility
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function checkRefillEligibility(string $uuid): JsonResponse
    {
        try {
            $this->authorize('refill', \App\Models\Prescription::class);
            
            $eligibility = $this->prescriptionService->checkRefillEligibility($uuid);
            
            return response()->json([
                'success' => true,
                'data' => $eligibility,
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Prescription not found.',
            ], Response::HTTP_NOT_FOUND);
            
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_FORBIDDEN);
            
        } catch (\Exception $e) {
            Log::error('Failed to check refill eligibility', [
                'uuid' => $uuid,
                'user_id' => auth::id()(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unable to check refill eligibility. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Transmit prescription to pharmacy
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function transmit(Request $request, string $uuid): JsonResponse
    {
        try {
            $this->authorize('transmit', \App\Models\Prescription::class);
            
            $validated = $request->validate([
                'pharmacy_ncpdp_id' => 'required_without:transmit_to_pharmacy|string|max:20',
                'transmit_to_pharmacy' => 'required_without:pharmacy_ncpdp_id|string|max:300',
                'notes' => 'nullable|string|max:500',
            ]);
            
            $prescription = $this->prescriptionService->transmitPrescription($uuid, $validated);
            
            return response()->json([
                'success' => true,
                'message' => 'Prescription transmitted successfully.',
                'data' => new PrescriptionResource($prescription->load(['patient', 'prescribingProvider'])),
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Prescription not found.',
            ], Response::HTTP_NOT_FOUND);
            
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
            
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_FORBIDDEN);
            
        } catch (\Exception $e) {
            Log::error('Failed to transmit prescription', [
                'uuid' => $uuid,
                'user_id' => auth::id()(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unable to transmit prescription. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get prescription statistics
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewStatistics', \App\Models\Prescription::class);
            
            $facilityId = $request->input('facility_id', auth::user()()->facility_id);
            $dateRange = $request->only(['start_date', 'end_date']);
            
            $statistics = $this->prescriptionService->getPrescriptionStatistics($facilityId, $dateRange);
            
            return response()->json([
                'success' => true,
                'data' => $statistics,
            ]);
            
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_FORBIDDEN);
            
        } catch (\Exception $e) {
            Log::error('Failed to get prescription statistics', [
                'facility_id' => $facilityId ?? 'not_provided',
                'user_id' => auth::id()(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve prescription statistics. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get prescriptions needing transmission
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function needsTransmission(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewTransmissions', \App\Models\Prescription::class);
            
            $facilityId = $request->input('facility_id', auth::user()()->facility_id);
            $limit = $request->input('limit', 50);
            
            $prescriptions = $this->prescriptionService->getPrescriptionsNeedingTransmission($facilityId, $limit);
            
            return response()->json([
                'success' => true,
                'data' => PrescriptionResource::collection($prescriptions),
                'meta' => [
                    'count' => $prescriptions->count(),
                    'facility_id' => $facilityId,
                ],
            ]);
            
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_FORBIDDEN);
            
        } catch (\Exception $e) {
            Log::error('Failed to get prescriptions needing transmission', [
                'facility_id' => $facilityId,
                'user_id' => auth::id()(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve prescriptions for transmission. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Sanitize request data for logging
     *
     * @param array $data
     * @return array
     */
    private function sanitizeRequestData(array $data): array
    {
        $sensitiveFields = [
            'prescriber_dea_number',
            'prescriber_dea_number_encrypted',
            'drug_allergy_check_results',
            'drug_interaction_check_results',
            'clinical_indication',
            'status_reason',
            'special_instructions',
        ];
        
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }
        
        return $data;
    }
}