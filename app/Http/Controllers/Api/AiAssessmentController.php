<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\AiAssessmentServiceInterface;
use App\Http\Requests\AiAssessment\StoreAiAssessmentRequest;
use App\Http\Requests\AiAssessment\UpdateAiAssessmentRequest;
use App\Http\Resources\AiAssessmentResource;
use App\Http\Resources\AiAssessmentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class AiAssessmentController extends Controller
{
    /**
     * Service instance
     *
     * @var AiAssessmentServiceInterface
     */
    private AiAssessmentServiceInterface $service;

    /**
     * Constructor
     *
     * @param AiAssessmentServiceInterface $service
     */
    public function __construct(AiAssessmentServiceInterface $service)
    {
        $this->service = $service;
        
        // Apply middleware
        // $this->middleware('auth:api');
        // $this->middleware('permission:view ai_assessments')->only(['index', 'show']);
        // $this->middleware('permission:create ai_assessments')->only(['store']);
        // $this->middleware('permission:edit ai_assessments')->only(['update']);
        // $this->middleware('permission:delete ai_assessments')->only(['destroy']);
    }

    /**
     * Display a listing of AI assessments.
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
                'model_type',
                'human_review_status',
                'is_fda_cleared',
                'start_date',
                'end_date',
                'patient_id',
                'clinical_encounter_id'
            ]);
            
            $perPage = $request->get('per_page', 20);
            $perPage = min(max($perPage, 1), 100); // Limit per page between 1 and 100
            
            // Get assessments from service
            $result = $this->service->getAllAssessments($filters, $perPage);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error_code' => $result['error_code'] ?? 'UNKNOWN_ERROR'
                ], Response::HTTP_BAD_REQUEST);
            }
            
            // Transform data using resource collection
            $assessments = $result['data'];
            $resource = new AiAssessmentResource($assessments);
            
            return response()->json([
                'success' => true,
                'data' => $resource,
                'message' => $result['message'],
                'meta' => $result['meta'] ?? []
            ]);
        } catch (\Exception $e) {
           Log::error('Unexpected error in AI assessment index', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving AI assessments.',
                'error_code' => 'INTERNAL_SERVER_ERROR'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created AI assessment.
     *
     * @param StoreAiAssessmentRequest $request
     * @return JsonResponse
     */
    public function store(StoreAiAssessmentRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            
            // Add authenticated user ID if not provided
            if (!isset($validatedData['reviewed_by_staff_id']) && auth::check()) {
                $validatedData['reviewed_by_staff_id'] = auth::id();
            }
            
            // Call service to create assessment
            $result = $this->service->createAssessment($validatedData);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error_code' => $result['error_code']
                ], Response::HTTP_BAD_REQUEST);
            }
            
            // Transform data using resource
            $resource = new AiAssessmentResource($result['data']);
            
            return response()->json([
                'success' => true,
                'data' => $resource,
                'message' => $result['message']
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
           Log::error('Unexpected error in AI assessment store', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while creating the AI assessment.',
                'error_code' => 'INTERNAL_SERVER_ERROR'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified AI assessment.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            $result = $this->service->getAssessmentByUuid($uuid);
            
            if (!$result['success']) {
                $statusCode = $result['error_code'] === 'ASSESSMENT_NOT_FOUND' 
                    ? Response::HTTP_NOT_FOUND 
                    : Response::HTTP_BAD_REQUEST;
                
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error_code' => $result['error_code']
                ], $statusCode);
            }
            
            // Transform data using resource with loaded relationships
            $assessment = $result['data']->load([
                'facility',
                'clinicalEncounter',
                'visit',
                'patient',
                'reviewer'
            ]);
            
            $resource = new AiAssessmentResource($assessment);
            
            return response()->json([
                'success' => true,
                'data' => $resource,
                'message' => $result['message']
            ]);
        } catch (\Exception $e) {
           Log::error('Unexpected error in AI assessment show', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving the AI assessment.',
                'error_code' => 'INTERNAL_SERVER_ERROR'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified AI assessment.
     *
     * @param UpdateAiAssessmentRequest $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function update(UpdateAiAssessmentRequest $request, string $uuid): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            
            // Call service to update assessment
            $result = $this->service->updateAssessment($uuid, $validatedData);
            
            if (!$result['success']) {
                $statusCode = $result['error_code'] === 'ASSESSMENT_NOT_FOUND' 
                    ? Response::HTTP_NOT_FOUND 
                    : Response::HTTP_BAD_REQUEST;
                
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error_code' => $result['error_code']
                ], $statusCode);
            }
            
            // Transform data using resource
            $resource = new AiAssessmentResource($result['data']);
            
            return response()->json([
                'success' => true,
                'data' => $resource,
                'message' => $result['message']
            ]);
        } catch (\Exception $e) {
           Log::error('Unexpected error in AI assessment update', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while updating the AI assessment.',
                'error_code' => 'INTERNAL_SERVER_ERROR'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified AI assessment.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $result = $this->service->deleteAssessment($uuid);
            
            if (!$result['success']) {
                $statusCode = $result['error_code'] === 'ASSESSMENT_NOT_FOUND' 
                    ? Response::HTTP_NOT_FOUND 
                    : Response::HTTP_BAD_REQUEST;
                
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error_code' => $result['error_code']
                ], $statusCode);
            }
            
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => $result['message']
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
           Log::error('Unexpected error in AI assessment destroy', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while deleting the AI assessment.',
                'error_code' => 'INTERNAL_SERVER_ERROR'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Review an AI assessment.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function review(Request $request, string $uuid): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'required|string|in:pending_review,accepted,modified,rejected,overridden,not_applicable',
                'review_notes' => 'nullable|string',
                'modifications_made' => 'nullable|array',
                'rejection_reason' => 'nullable|string|required_if:status,rejected',
            ]);
            
            $reviewData = $request->only([
                'status',
                'review_notes',
                'modifications_made',
                'rejection_reason'
            ]);
            
            $result = $this->service->reviewAssessment($uuid, $reviewData);
            
            if (!$result['success']) {
                $statusCode = $result['error_code'] === 'ASSESSMENT_NOT_FOUND' 
                    ? Response::HTTP_NOT_FOUND 
                    : Response::HTTP_BAD_REQUEST;
                
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error_code' => $result['error_code']
                ], $statusCode);
            }
            
            $resource = new AiAssessmentResource($result['data']);
            
            return response()->json([
                'success' => true,
                'data' => $resource,
                'message' => $result['message']
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'error_code' => 'VALIDATION_FAILED'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
           Log::error('Unexpected error in AI assessment review', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while reviewing the AI assessment.',
                'error_code' => 'INTERNAL_SERVER_ERROR'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Record clinical outcome for an AI assessment.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function recordOutcome(Request $request, string $uuid): JsonResponse
    {
        try {
            $request->validate([
                'outcome' => 'required|array',
                'accuracy' => 'nullable|numeric|min:0|max:1',
                'notes' => 'nullable|string',
            ]);
            
            $outcomeData = $request->only(['outcome', 'accuracy', 'notes']);
            
            $result = $this->service->recordClinicalOutcome($uuid, $outcomeData);
            
            if (!$result['success']) {
                $statusCode = $result['error_code'] === 'ASSESSMENT_NOT_FOUND' 
                    ? Response::HTTP_NOT_FOUND 
                    : Response::HTTP_BAD_REQUEST;
                
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error_code' => $result['error_code']
                ], $statusCode);
            }
            
            $resource = new AiAssessmentResource($result['data']);
            
            return response()->json([
                'success' => true,
                'data' => $resource,
                'message' => $result['message']
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'error_code' => 'VALIDATION_FAILED'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
           Log::error('Unexpected error recording clinical outcome', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while recording the clinical outcome.',
                'error_code' => 'INTERNAL_SERVER_ERROR'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Flag adverse event for an AI assessment.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function flagAdverseEvent(Request $request, string $uuid): JsonResponse
    {
        try {
            $request->validate([
                'description' => 'required|string',
                'severity' => 'nullable|string|in:low,medium,high,critical',
                'alerts' => 'nullable|array',
                'reported_by' => 'nullable|string',
            ]);
            
            $eventData = $request->only(['description', 'severity', 'alerts', 'reported_by']);
            
            $result = $this->service->flagAdverseEvent($uuid, $eventData);
            
            if (!$result['success']) {
                $statusCode = $result['error_code'] === 'ASSESSMENT_NOT_FOUND' 
                    ? Response::HTTP_NOT_FOUND 
                    : Response::HTTP_BAD_REQUEST;
                
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error_code' => $result['error_code']
                ], $statusCode);
            }
            
            $resource = new AiAssessmentResource($result['data']);
            
            return response()->json([
                'success' => true,
                'data' => $resource,
                'message' => $result['message']
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'error_code' => 'VALIDATION_FAILED'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
           Log::error('Unexpected error flagging adverse event', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while flagging the adverse event.',
                'error_code' => 'INTERNAL_SERVER_ERROR'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get AI assessments for a specific patient.
     *
     * @param Request $request
     * @param int $patientId
     * @return JsonResponse
     */
    public function byPatient(Request $request, int $patientId): JsonResponse
    {
        try {
            $filters = $request->only([
                'model_type',
                'human_review_status',
                'start_date',
                'end_date'
            ]);
            
            $result = $this->service->getPatientAssessments($patientId, $filters);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error_code' => $result['error_code']
                ], Response::HTTP_BAD_REQUEST);
            }
            
            $resource = AiAssessmentResource::collection($result['data']);
            
            return response()->json([
                'success' => true,
                'data' => $resource,
                'message' => $result['message'],
                'meta' => $result['meta'] ?? []
            ]);
        } catch (\Exception $e) {
           Log::error('Unexpected error getting patient AI assessments', [
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving patient AI assessments.',
                'error_code' => 'INTERNAL_SERVER_ERROR'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get AI assessments for a specific encounter.
     *
     * @param int $encounterId
     * @return JsonResponse
     */
    public function byEncounter(int $encounterId): JsonResponse
    {
        try {
            $result = $this->service->getEncounterAssessments($encounterId);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error_code' => $result['error_code']
                ], Response::HTTP_BAD_REQUEST);
            }
            
            $resource = AiAssessmentResource::collection($result['data']);
            
            return response()->json([
                'success' => true,
                'data' => $resource,
                'message' => $result['message'],
                'meta' => $result['meta'] ?? []
            ]);
        } catch (\Exception $e) {
           Log::error('Unexpected error getting encounter AI assessments', [
                'encounter_id' => $encounterId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving encounter AI assessments.',
                'error_code' => 'INTERNAL_SERVER_ERROR'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get pending reviews for a facility.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function pendingReviews(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'facility_id' => 'required|integer|exists:facilities,id'
            ]);
            
            $facilityId = $request->input('facility_id');
            $result = $this->service->getPendingReviews($facilityId);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error_code' => $result['error_code']
                ], Response::HTTP_BAD_REQUEST);
            }
            
            $resource = AiAssessmentResource::collection($result['data']);
            
            return response()->json([
                'success' => true,
                'data' => $resource,
                'message' => $result['message'],
                'meta' => $result['meta'] ?? []
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'error_code' => 'VALIDATION_FAILED'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
           Log::error('Unexpected error getting pending reviews', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving pending reviews.',
                'error_code' => 'INTERNAL_SERVER_ERROR'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get model statistics for a facility.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'facility_id' => 'required|integer|exists:facilities,id',
                'time_period' => 'nullable|string|in:today,week,month,quarter,year'
            ]);
            
            $facilityId = $request->input('facility_id');
            $timePeriod = $request->input('time_period');
            
            $result = $this->service->getModelStatistics($facilityId, $timePeriod);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error_code' => $result['error_code']
                ], Response::HTTP_BAD_REQUEST);
            }
            
            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'message' => $result['message'],
                'meta' => $result['meta'] ?? []
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'error_code' => 'VALIDATION_FAILED'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
           Log::error('Unexpected error getting model statistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving model statistics.',
                'error_code' => 'INTERNAL_SERVER_ERROR'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}