<?php

namespace App\Http\Controllers\Api\Lab;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\StoreLabResultRequest;
use App\Http\Requests\Lab\UpdateLabResultRequest;
use App\Http\Requests\Lab\BulkCreateLabResultsRequest;
use App\Http\Resources\Lab\LabResultResource;
use App\Http\Resources\Lab\LabResultCollection;
use App\Services\Lab\Contracts\LabResultServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LabResultController extends Controller
{
    /**
     * @var LabResultServiceInterface
     */
    protected LabResultServiceInterface $resultService;

    /**
     * Constructor.
     *
     * @param LabResultServiceInterface $resultService
     */
    public function __construct(LabResultServiceInterface $resultService)
    {
        $this->resultService = $resultService;
    }

    /**
     * Display a listing of lab results.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'lab_request_item_id', 'template_field_id', 'flag', 'is_abnormal_flagged',
                'is_verified', 'date_from', 'date_to', 'order_by', 'order_direction', 'per_page'
            ]);
            
            $perPage = $request->get('per_page', 20);
            $result = $this->resultService->getAllResults($filters, $perPage);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $results = new LabResultCollection($result['data']['results']);
            $result['data']['results'] = $results;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve lab results', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve lab results',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created lab result.
     *
     * @param StoreLabResultRequest $request
     * @return JsonResponse
     */
    public function store(StoreLabResultRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $result = $this->resultService->createResult($validatedData);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_CREATED 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $result['data']['result'] = new LabResultResource($result['data']['result']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to create lab result', [
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create lab result',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified lab result.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            $result = $this->resultService->getResultByUuid($uuid);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Lab result not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($result, $statusCode);
            }
            
            $result['data']['result'] = new LabResultResource($result['data']['result']);
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve lab result', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve lab result',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified lab result.
     *
     * @param UpdateLabResultRequest $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function update(UpdateLabResultRequest $request, string $uuid): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $result = $this->resultService->updateResult($uuid, $validatedData);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : ($result['message'] === 'Lab result not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST);
            
            if ($result['success']) {
                $result['data']['result'] = new LabResultResource($result['data']['result']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to update lab result', [
                'uuid' => $uuid,
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update lab result',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified lab result.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $result = $this->resultService->deleteResult($uuid);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : ($result['message'] === 'Lab result not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST);
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to delete lab result', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete lab result',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Verify a lab result.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function verify(Request $request, string $uuid): JsonResponse
    {
        try {
            $request->validate([
                'verified_by_staff_id' => 'required|exists:staff,id'
            ]);
            
            $result = $this->resultService->verifyResult($uuid, $request->verified_by_staff_id);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $result['data']['result'] = new LabResultResource($result['data']['result']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'data' => []
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to verify lab result', [
                'uuid' => $uuid,
                'verified_by_staff_id' => $request->verified_by_staff_id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify result',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get results by lab request item.
     *
     * @param string $itemUuid
     * @return JsonResponse
     */
    public function byLabRequestItem(string $itemUuid): JsonResponse
    {
        try {
            $result = $this->resultService->getResultsByLabRequestItem($itemUuid);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Lab request item not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($result, $statusCode);
            }
            
            $results = LabResultResource::collection($result['data']['results']);
            $result['data']['results'] = $results;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve results by lab request item', [
                'item_uuid' => $itemUuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve results',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get results by template field.
     *
     * @param Request $request
     * @param string $fieldUuid
     * @return JsonResponse
     */
    public function byTemplateField(Request $request, string $fieldUuid): JsonResponse
    {
        try {
            $filters = $request->only(['flag']);
            $result = $this->resultService->getResultsByTemplateField($fieldUuid, $filters);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Template field not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($result, $statusCode);
            }
            
            $results = LabResultResource::collection($result['data']['results']);
            $result['data']['results'] = $results;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve results by template field', [
                'field_uuid' => $fieldUuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve results',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get results by flag.
     *
     * @param Request $request
     * @param string $flag
     * @return JsonResponse
     */
    public function byFlag(Request $request, string $flag): JsonResponse
    {
        try {
            $facilityId = $request->get('facility_id');
            $result = $this->resultService->getResultsByFlag($flag, $facilityId);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $results = LabResultResource::collection($result['data']['results']);
            $result['data']['results'] = $results;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve results by flag', [
                'flag' => $flag,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve results',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get abnormal results.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function abnormal(Request $request): JsonResponse
    {
        try {
            $facilityId = $request->get('facility_id');
            $result = $this->resultService->getAbnormalResults($facilityId);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $results = LabResultResource::collection($result['data']['results']);
            $result['data']['results'] = $results;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve abnormal results', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve abnormal results',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get critical results.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function critical(Request $request): JsonResponse
    {
        try {
            $facilityId = $request->get('facility_id');
            $result = $this->resultService->getCriticalResults($facilityId);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $results = LabResultResource::collection($result['data']['results']);
            $result['data']['results'] = $results;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve critical results', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve critical results',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get critical results requiring attention.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function criticalRequiringAttention(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'facility_id' => 'required|exists:facilities,id'
            ]);
            
            $result = $this->resultService->getCriticalResultsRequiringAttention($request->facility_id);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $results = LabResultResource::collection($result['data']['results']);
            $result['data']['results'] = $results;
            
            return response()->json($result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'data' => []
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve critical results requiring attention', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve critical results',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get unverified results.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function unverified(Request $request): JsonResponse
    {
        try {
            $facilityId = $request->get('facility_id');
            $result = $this->resultService->getUnverifiedResults($facilityId);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $results = LabResultResource::collection($result['data']['results']);
            $result['data']['results'] = $results;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve unverified results', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve unverified results',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get results by patient.
     *
     * @param Request $request
     * @param int $patientId
     * @return JsonResponse
     */
    public function byPatient(Request $request, int $patientId): JsonResponse
    {
        try {
            $filters = $request->only([
                'flag', 'date_from', 'date_to', 'order_by', 'order_direction'
            ]);
            
            $perPage = $request->get('per_page', 20);
            $result = $this->resultService->getResultsByPatient($patientId, $filters, $perPage);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $results = new LabResultCollection($result['data']['results']);
            $result['data']['results'] = $results;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve results by patient', [
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve patient results',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Bulk create results for an item.
     *
     * @param BulkCreateLabResultsRequest $request
     * @param string $itemUuid
     * @return JsonResponse
     */
    public function bulkStore(BulkCreateLabResultsRequest $request, string $itemUuid): JsonResponse
    {
        try {
            $result = $this->resultService->bulkCreateResults($itemUuid, $request->results);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_CREATED 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $results = LabResultResource::collection($result['data']['results']);
                $result['data']['results'] = $results;
            }
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to bulk create results', [
                'item_uuid' => $itemUuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create results',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get result with relationships.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function withRelations(string $uuid): JsonResponse
    {
        try {
            $result = $this->resultService->getResultWithRelations($uuid);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Lab result not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($result, $statusCode);
            }
            
            $result['data']['result'] = new LabResultResource($result['data']['result']);
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve result with relations', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve result details',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get result statistics.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'facility_id' => 'required|exists:facilities,id',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date'
            ]);
            
            $result = $this->resultService->getResultStatistics(
                $request->facility_id,
                $request->start_date,
                $request->end_date
            );
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            return response()->json($result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'data' => []
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve result statistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get result trends for a test.
     *
     * @param Request $request
     * @param string $testUuid
     * @return JsonResponse
     */
    public function trends(Request $request, string $testUuid): JsonResponse
    {
        try {
            $request->validate([
                'patient_id' => 'required|exists:patients,id',
                'limit' => 'nullable|integer|min:1|max:50'
            ]);
            
            $limit = $request->get('limit', 10);
            $result = $this->resultService->getResultTrends($testUuid, $request->patient_id, $limit);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Lab test not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($result, $statusCode);
            }
            
            $trends = LabResultResource::collection($result['data']['trends']);
            $result['data']['trends'] = $trends;
            
            return response()->json($result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'data' => []
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve result trends', [
                'test_uuid' => $testUuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve trends',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Mark critical alert as sent.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function markCriticalAlertSent(string $uuid): JsonResponse
    {
        try {
            $result = $this->resultService->markCriticalAlertSent($uuid);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $result['data']['result'] = new LabResultResource($result['data']['result']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to mark critical alert as sent', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark critical alert',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Recalculate result flag.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function recalculateFlag(string $uuid): JsonResponse
    {
        try {
            $result = $this->resultService->recalculateResultFlag($uuid);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $result['data']['result'] = new LabResultResource($result['data']['result']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to recalculate result flag', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to recalculate flag',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Export results.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function export(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'lab_request_item_id', 'template_field_id', 'flag', 'is_abnormal_flagged',
                'is_verified', 'date_from', 'date_to'
            ]);
            
            $result = $this->resultService->exportResults($filters);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to export results', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to export results',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}