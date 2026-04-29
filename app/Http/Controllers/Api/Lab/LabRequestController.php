<?php

namespace App\Http\Controllers\Api\Lab;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\StoreLabRequestRequest;
use App\Http\Requests\Lab\UpdateLabRequestRequest;
use App\Http\Requests\Lab\BulkUpdateLabRequestItemsStatusRequest;
use App\Http\Resources\Lab\LabRequestResource;
use App\Http\Resources\Lab\LabRequestCollection;
use App\Services\Lab\Contracts\LabRequestServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LabRequestController extends Controller
{
    /**
     * @var LabRequestServiceInterface
     */
    protected LabRequestServiceInterface $requestService;

    /**
     * Constructor.
     *
     * @param LabRequestServiceInterface $requestService
     */
    public function __construct(LabRequestServiceInterface $requestService)
    {
        $this->requestService = $requestService;
    }

    /**
     * Display a listing of lab requests.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'facility_id', 'patient_id', 'visit_id', 'status', 'priority',
                'requested_by_staff_id', 'date_from', 'date_to', 'search',
                'order_by', 'order_direction', 'per_page'
            ]);
            
            $perPage = $request->get('per_page', 20);
            $result = $this->requestService->getAllRequests($filters, $perPage);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $requests = new LabRequestCollection($result['data']['requests']);
            $result['data']['requests'] = $requests;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve lab requests', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve lab requests',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created lab request.
     *
     * @param StoreLabRequestRequest $request
     * @return JsonResponse
     */
    public function store(StoreLabRequestRequest $request): JsonResponse
    {
        Log::info($request);
        dd("Wait");
        try {
            $validatedData = $request->validated();
            $result = $this->requestService->createRequest($validatedData);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_CREATED 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $result['data']['request'] = new LabRequestResource($result['data']['request']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to create lab request', [
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create lab request',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified lab request.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            $result = $this->requestService->getRequestByUuid($uuid);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Lab request not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($result, $statusCode);
            }
            
            $result['data']['request'] = new LabRequestResource($result['data']['request']);
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve lab request', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve lab request',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified lab request.
     *
     * @param UpdateLabRequestRequest $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function update(UpdateLabRequestRequest $request, string $uuid): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $result = $this->requestService->updateRequest($uuid, $validatedData);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : ($result['message'] === 'Lab request not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST);
            
            if ($result['success']) {
                $result['data']['request'] = new LabRequestResource($result['data']['request']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to update lab request', [
                'uuid' => $uuid,
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update lab request',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified lab request.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $result = $this->requestService->deleteRequest($uuid);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : ($result['message'] === 'Lab request not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST);
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to delete lab request', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete lab request',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update request status.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function updateStatus(Request $request, string $uuid): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'required|string|in:pending,in_progress,completed,reviewed,cancelled'
            ]);
            
            $result = $this->requestService->updateRequestStatus($uuid, $request->status);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $result['data']['request'] = new LabRequestResource($result['data']['request']);
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
            Log::error('Failed to update request status', [
                'uuid' => $uuid,
                'status' => $request->status ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update request status',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Cancel a lab request.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function cancel(Request $request, string $uuid): JsonResponse
    {
        try {
            $request->validate([
                'reason' => 'required|string|max:500',
                'cancelled_by_staff_id' => 'nullable|exists:staff,id'
            ]);
            
            $result = $this->requestService->cancelRequest(
                $uuid, 
                $request->reason, 
                $request->cancelled_by_staff_id
            );
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $result['data']['request'] = new LabRequestResource($result['data']['request']);
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
            Log::error('Failed to cancel lab request', [
                'uuid' => $uuid,
                'reason' => $request->reason ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel lab request',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get lab requests by facility.
     *
     * @param Request $request
     * @param int $facilityId
     * @return JsonResponse
     */
    public function byFacility(Request $request, int $facilityId): JsonResponse
    {
        try {
            $filters = $request->only([
                'patient_id', 'status', 'priority', 'date_from', 'date_to', 'search'
            ]);
            
            $perPage = $request->get('per_page', 20);
            $result = $this->requestService->getRequestsByFacility($facilityId, $filters, $perPage);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $requests = new LabRequestCollection($result['data']['requests']);
            $result['data']['requests'] = $requests;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve requests by facility', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve lab requests',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get lab requests by patient.
     *
     * @param Request $request
     * @param int $patientId
     * @return JsonResponse
     */
    public function byPatient(Request $request, int $patientId): JsonResponse
    {
        try {
            $filters = $request->only([
                'facility_id', 'status', 'priority', 'date_from', 'date_to', 'search'
            ]);
            
            $perPage = $request->get('per_page', 20);
            $result = $this->requestService->getRequestsByPatient($patientId, $filters, $perPage);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $requests = new LabRequestCollection($result['data']['requests']);
            $result['data']['requests'] = $requests;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve requests by patient', [
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve lab requests',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get lab requests by visit.
     *
     * @param int $visitId
     * @return JsonResponse
     */
    public function byVisit(int $visitId): JsonResponse
    {
        try {
            $result = $this->requestService->getRequestsByVisit($visitId);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $requests = LabRequestResource::collection($result['data']['requests']);
            $result['data']['requests'] = $requests;
            
            $response= response()->json($result);
            return $response;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve requests by visit', [
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve lab requests',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get pending lab requests.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function pending(Request $request): JsonResponse
    {
        try {
            $facilityId = $request->get('facility_id');
            $result = $this->requestService->getPendingRequests($facilityId);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $requests = LabRequestResource::collection($result['data']['requests']);
            $result['data']['requests'] = $requests;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve pending requests', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve pending requests',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get requests requiring attention.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function requiringAttention(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'facility_id' => 'required|exists:facilities,id'
            ]);
            
            $result = $this->requestService->getRequestsRequiringAttention($request->facility_id);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $requests = LabRequestResource::collection($result['data']['requests']);
            $result['data']['requests'] = $requests;
            
            return response()->json($result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'data' => []
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve requests requiring attention', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve requests',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get request with its items.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function withItems(string $uuid): JsonResponse
    {
        try {
            $result = $this->requestService->getRequestWithItems($uuid);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Lab request not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($result, $statusCode);
            }
            
            $result['data']['request'] = new LabRequestResource($result['data']['request']);
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve request with items', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve request details',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get request with full details (items and results).
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function withFullDetails(string $uuid): JsonResponse
    {
        try {
            $result = $this->requestService->getRequestWithFullDetails($uuid);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Lab request not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($result, $statusCode);
            }
            
            $result['data']['request'] = new LabRequestResource($result['data']['request']);
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve request with full details', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve request details',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get request statistics.
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
            
            $result = $this->requestService->getRequestStatistics(
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
            Log::error('Failed to retrieve request statistics', [
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
     * Create request with items.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function storeWithItems(Request $request): JsonResponse
    {
        try {
            $request->validate([
                // Request validation
                'visit_id' => 'required|exists:visits,id',
                'patient_id' => 'required|exists:patients,id',
                'facility_id' => 'required|exists:facilities,id',
                'requested_by_staff_id' => 'nullable|exists:staff,id',
                'priority' => 'required|in:routine,urgent,stat',
                'clinical_notes' => 'nullable|string',
                'diagnosis_context' => 'nullable|array',
                
                // Items validation
                'items' => 'required|array|min:1',
                'items.*.lab_test_id' => 'required|exists:lab_tests,id',
                'items.*.sample_type' => 'nullable|string|max:100',
                'items.*.notes' => 'nullable|string',
            ]);
            
            $requestData = $request->only([
                'visit_id', 'patient_id', 'facility_id', 'requested_by_staff_id',
                'priority', 'clinical_notes', 'diagnosis_context', 'metadata'
            ]);
            
            $itemsData = $request->items;
            
            $result = $this->requestService->createRequestWithItems($requestData, $itemsData);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_CREATED 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $result['data']['request'] = new LabRequestResource($result['data']['request']);
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
            Log::error('Failed to create request with items', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create lab request',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Add items to existing request.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function addItems(Request $request, string $uuid): JsonResponse
    {
        try {
            $request->validate([
                'items' => 'required|array|min:1',
                'items.*.lab_test_id' => 'required|exists:lab_tests,id',
                'items.*.sample_type' => 'nullable|string|max:100',
                'items.*.notes' => 'nullable|string',
            ]);
            
            $result = $this->requestService->addItemsToRequest($uuid, $request->items);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $result['data']['request'] = new LabRequestResource($result['data']['request']);
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
            Log::error('Failed to add items to request', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to add items',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}