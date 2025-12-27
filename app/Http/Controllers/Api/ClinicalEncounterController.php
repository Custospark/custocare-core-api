<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClinicalEncounter\StoreClinicalEncounterRequest;
use App\Http\Requests\ClinicalEncounter\UpdateClinicalEncounterRequest;
use App\Http\Resources\ClinicalEncounterResource;
use App\Services\Contracts\ClinicalEncounterServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ClinicalEncounterController extends Controller
{
    /**
     * Clinical encounter service instance
     *
     * @var ClinicalEncounterServiceInterface
     */
    protected ClinicalEncounterServiceInterface $clinicalEncounterService;

    /**
     * Constructor with dependency injection
     *
     * @param ClinicalEncounterServiceInterface $clinicalEncounterService
     */
    public function __construct(ClinicalEncounterServiceInterface $clinicalEncounterService)
    {
        $this->clinicalEncounterService = $clinicalEncounterService;
        
        // Apply middleware
        // $this->middleware('auth:api');
        // $this->middleware('permission:view clinical encounters')->only(['index', 'show']);
        // $this->middleware('permission:create clinical encounters')->only(['store']);
        // $this->middleware('permission:edit clinical encounters')->only(['update']);
        // $this->middleware('permission:delete clinical encounters')->only(['destroy']);
        // $this->middleware('permission:restore clinical encounters')->only(['restore']);
        // $this->middleware('permission:sign clinical encounters')->only(['sign']);
        // $this->middleware('permission:amend clinical encounters')->only(['amend']);
    }

    /**
     * Display a listing of clinical encounters
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'facility_id',
                'encounter_type',
                'department_id',
                'documentation_status',
                'is_billable',
                'start_date',
                'end_date',
                'min_severity',
                'max_severity',
            ]);
            
            $perPage = $request->input('per_page', 15);
            
            $encounters = $this->clinicalEncounterService->getAllEncounters($filters, $perPage);
            
            return response()->json([
                'success' => true,
                'message' => 'Clinical encounters retrieved successfully.',
                'data' => ClinicalEncounterResource::collection($encounters),
                'meta' => [
                    'current_page' => $encounters->currentPage(),
                    'last_page' => $encounters->lastPage(),
                    'per_page' => $encounters->perPage(),
                    'total' => $encounters->total(),
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve clinical encounters', [
                'error' => $e->getMessage(),
                'filters' => $request->all(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve clinical encounters. Please try again later.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created clinical encounter
     *
     * @param StoreClinicalEncounterRequest $request
     * @return JsonResponse
     */
    public function store(StoreClinicalEncounterRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            
            $encounter = $this->clinicalEncounterService->createEncounter($validatedData);
            
            return response()->json([
                'success' => true,
                'message' => 'Clinical encounter created successfully.',
                'data' => new ClinicalEncounterResource($encounter),
            ], Response::HTTP_CREATED);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            Log::error('Failed to create clinical encounter', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create clinical encounter. Please try again later.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified clinical encounter
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            $encounter = $this->clinicalEncounterService->getEncounterByUuid($uuid);
            
            return response()->json([
                'success' => true,
                'message' => 'Clinical encounter retrieved successfully.',
                'data' => new ClinicalEncounterResource($encounter),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve clinical encounter', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve clinical encounter. Please try again later.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified clinical encounter
     *
     * @param UpdateClinicalEncounterRequest $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function update(UpdateClinicalEncounterRequest $request, string $uuid): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            
            $encounter = $this->clinicalEncounterService->updateEncounter($uuid, $validatedData);
            
            return response()->json([
                'success' => true,
                'message' => 'Clinical encounter updated successfully.',
                'data' => new ClinicalEncounterResource($encounter),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            Log::error('Failed to update clinical encounter', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update clinical encounter. Please try again later.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified clinical encounter
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $deleted = $this->clinicalEncounterService->deleteEncounter($uuid);
            
            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Clinical encounter deleted successfully.',
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete clinical encounter.',
            ], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            Log::error('Failed to delete clinical encounter', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete clinical encounter. Please try again later.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Restore a soft-deleted clinical encounter
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function restore(string $uuid): JsonResponse
    {
        try {
            $encounter = $this->clinicalEncounterService->restoreEncounter($uuid);
            
            return response()->json([
                'success' => true,
                'message' => 'Clinical encounter restored successfully.',
                'data' => new ClinicalEncounterResource($encounter),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            Log::error('Failed to restore clinical encounter', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore clinical encounter. Please try again later.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Sign a clinical encounter
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function sign(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'electronic_signature_hash' => 'required|string|max:128',
        ]);
        
        try {
            $signatureHash = $request->input('electronic_signature_hash');
            
            $encounter = $this->clinicalEncounterService->signEncounter($uuid, $signatureHash);
            
            return response()->json([
                'success' => true,
                'message' => 'Clinical encounter signed successfully.',
                'data' => new ClinicalEncounterResource($encounter),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            Log::error('Failed to sign clinical encounter', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to sign clinical encounter. Please try again later.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create an amendment to a clinical encounter
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function amend(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'amendment_data' => 'required|array',
            'amendment_reason' => 'required|string|max:1000',
        ]);
        
        try {
            $amendmentData = $request->input('amendment_data');
            $amendmentReason = $request->input('amendment_reason');
            
            $amendment = $this->clinicalEncounterService->createAmendment(
                $uuid,
                $amendmentData,
                $amendmentReason
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Clinical encounter amendment created successfully.',
                'data' => new ClinicalEncounterResource($amendment),
            ], Response::HTTP_CREATED);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            Log::error('Failed to create amendment', [
                'original_uuid' => $uuid,
                'error' => $e->getMessage(),
                'amendment_data' => $request->input('amendment_data'),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create amendment. Please try again later.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get encounters requiring immediate attention
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function requiringAttention(Request $request): JsonResponse
    {
        $request->validate([
            'facility_id' => 'required|integer|exists:facilities,id',
        ]);
        
        try {
            $facilityId = $request->input('facility_id');
            
            $encounters = $this->clinicalEncounterService->getEncountersRequiringAttention($facilityId);
            
            return response()->json([
                'success' => true,
                'message' => 'Encounters requiring attention retrieved successfully.',
                'data' => ClinicalEncounterResource::collection($encounters),
                'meta' => [
                    'count' => $encounters->count(),
                    'facility_id' => $facilityId,
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve encounters requiring attention', [
                'facility_id' => $request->input('facility_id'),
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve encounters requiring attention. Please try again later.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get incomplete documentation
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function incompleteDocumentation(Request $request): JsonResponse
    {
        $request->validate([
            'facility_id' => 'required|integer|exists:facilities,id',
            'days_threshold' => 'nullable|integer|min:1|max:30',
        ]);
        
        try {
            $facilityId = $request->input('facility_id');
            $daysThreshold = $request->input('days_threshold', 3);
            
            $encounters = $this->clinicalEncounterService->getIncompleteDocumentation($facilityId, $daysThreshold);
            
            return response()->json([
                'success' => true,
                'message' => 'Incomplete documentation retrieved successfully.',
                'data' => ClinicalEncounterResource::collection($encounters),
                'meta' => [
                    'count' => $encounters->count(),
                    'facility_id' => $facilityId,
                    'days_threshold' => $daysThreshold,
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve incomplete documentation', [
                'facility_id' => $request->input('facility_id'),
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve incomplete documentation. Please try again later.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Validate encounter completeness
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function validateCompleteness(string $uuid): JsonResponse
    {
        try {
            $encounter = $this->clinicalEncounterService->getEncounterByUuid($uuid);
            
            $completeness = $this->clinicalEncounterService->validateEncounterCompleteness($encounter);
            
            return response()->json([
                'success' => true,
                'message' => 'Encounter completeness validated.',
                'data' => [
                    'encounter_uuid' => $uuid,
                    'completeness' => $completeness,
                    'can_be_signed' => $completeness['is_complete'] && $encounter->documentation_status === 'completed',
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            Log::error('Failed to validate encounter completeness', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unable to validate encounter completeness. Please try again later.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Generate billing information for encounter
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function billingInformation(string $uuid): JsonResponse
    {
        try {
            $encounter = $this->clinicalEncounterService->getEncounterByUuid($uuid);
            
            $billingInfo = $this->clinicalEncounterService->generateBillingInformation($encounter);
            
            return response()->json([
                'success' => true,
                'message' => 'Billing information generated.',
                'data' => [
                    'encounter_uuid' => $uuid,
                    'billing' => $billingInfo,
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            Log::error('Failed to generate billing information', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unable to generate billing information. Please try again later.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}