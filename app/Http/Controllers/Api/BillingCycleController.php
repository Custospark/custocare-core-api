<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BillingCycle\StoreBillingCycleRequest;
use App\Http\Requests\BillingCycle\UpdateBillingCycleRequest;
use App\Http\Resources\BillingCycleResource;
use App\Services\Contracts\BillingCycleServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BillingCycleController extends Controller
{
    /**
     * Service instance
     *
     * @var BillingCycleServiceInterface
     */
    private BillingCycleServiceInterface $billingCycleService;

    /**
     * Constructor
     *
     * @param BillingCycleServiceInterface $billingCycleService
     */
    public function __construct(BillingCycleServiceInterface $billingCycleService)
    {
        $this->billingCycleService = $billingCycleService;
    }

    /**
     * Display a listing of the billing cycles.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Authorize using policy
            $this->authorize('viewAny', \App\Models\BillingCycle::class);
            
            $filters = $request->only([
                'facility_id', 'patient_id', 'visit_id', 'billing_status',
                'cycle_type', 'period_start_from', 'period_start_to',
                'search', 'order_by', 'order_direction', 'per_page'
            ]);
            
            $result = $this->billingCycleService->getAllBillingCycles($filters);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            // Transform the data using resource
            $billingCycles = BillingCycleResource::collection($result['data']['billing_cycles']);
            $result['data']['billing_cycles'] = $billingCycles;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve billing cycles list', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve billing cycles',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created billing cycle in storage.
     *
     * @param StoreBillingCycleRequest $request
     * @return JsonResponse
     */
    public function store(StoreBillingCycleRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            
            $result = $this->billingCycleService->createBillingCycle($validatedData);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_CREATED 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $result['data']['billing_cycle'] = new BillingCycleResource($result['data']['billing_cycle']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to create billing cycle', [
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create billing cycle',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified billing cycle.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            $result = $this->billingCycleService->getBillingCycleByUuid($uuid);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Billing cycle not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($result, $statusCode);
            }
            
            // Authorize using policy
            $this->authorize('view', $result['data']['billing_cycle']);
            
            $result['data']['billing_cycle'] = new BillingCycleResource($result['data']['billing_cycle']);
            
            return response()->json($result);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
                'error' => 'You are not authorized to view this billing cycle',
                'data' => []
            ], JsonResponse::HTTP_FORBIDDEN);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve billing cycle', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve billing cycle',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified billing cycle in storage.
     *
     * @param UpdateBillingCycleRequest $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function update(UpdateBillingCycleRequest $request, string $uuid): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            
            $result = $this->billingCycleService->updateBillingCycle($uuid, $validatedData);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : ($result['message'] === 'Billing cycle not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST);
            
            if ($result['success']) {
                $result['data']['billing_cycle'] = new BillingCycleResource($result['data']['billing_cycle']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to update billing cycle', [
                'uuid' => $uuid,
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update billing cycle',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified billing cycle from storage.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            // First get the billing cycle to authorize
            $getResult = $this->billingCycleService->getBillingCycleByUuid($uuid);
            
            if (!$getResult['success']) {
                $statusCode = $getResult['message'] === 'Billing cycle not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($getResult, $statusCode);
            }
            
            // Authorize using policy
            $this->authorize('delete', $getResult['data']['billing_cycle']);
            
            $result = $this->billingCycleService->deleteBillingCycle($uuid);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            return response()->json($result, $statusCode);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
                'error' => 'You are not authorized to delete this billing cycle',
                'data' => []
            ], JsonResponse::HTTP_FORBIDDEN);
        } catch (\Exception $e) {
            Log::error('Failed to delete billing cycle', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete billing cycle',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update billing status
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function updateStatus(Request $request, string $uuid): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'required|string|in:draft,pending_review,pending_submission,submitted_to_insurance,partially_paid,paid_in_full,payment_plan,collections,disputed,written_off,charity_care',
                'dispute_reason' => 'nullable|string|max:1000',
                'collections_agency' => 'nullable|string|max:200',
            ]);
            
            // First get the billing cycle to authorize
            $getResult = $this->billingCycleService->getBillingCycleByUuid($uuid);
            
            if (!$getResult['success']) {
                $statusCode = $getResult['message'] === 'Billing cycle not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($getResult, $statusCode);
            }
            
            // Authorize using policy with specific status
            $this->authorize('updateStatus', [$getResult['data']['billing_cycle'], $request->input('status')]);
            
            $additionalData = $request->only(['dispute_reason', 'collections_agency']);
            
            $result = $this->billingCycleService->updateBillingStatus(
                $uuid, 
                $request->input('status'), 
                $additionalData
            );
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $result['data']['billing_cycle'] = new BillingCycleResource($result['data']['billing_cycle']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
                'error' => 'You are not authorized to update the status of this billing cycle',
                'data' => []
            ], JsonResponse::HTTP_FORBIDDEN);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'data' => []
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to update billing status', [
                'uuid' => $uuid,
                'status' => $request->input('status'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update billing status',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Record payment
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function recordPayment(Request $request, string $uuid): JsonResponse
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0.01|max:999999999.99',
                'payment_type' => 'required|string|in:insurance,patient',
                'payment_method' => 'nullable|string|max:100',
                'transaction_id' => 'nullable|string|max:100',
                'notes' => 'nullable|string|max:500',
            ]);
            
            // First get the billing cycle to authorize
            $getResult = $this->billingCycleService->getBillingCycleByUuid($uuid);
            
            if (!$getResult['success']) {
                $statusCode = $getResult['message'] === 'Billing cycle not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($getResult, $statusCode);
            }
            
            // Authorize using policy
            $this->authorize('recordPayment', $getResult['data']['billing_cycle']);
            
            $paymentData = $request->only([
                'amount', 'payment_type', 'payment_method', 
                'transaction_id', 'notes'
            ]);
            
            $result = $this->billingCycleService->recordPayment($uuid, $paymentData);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $result['data']['billing_cycle'] = new BillingCycleResource($result['data']['billing_cycle']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
                'error' => 'You are not authorized to record payments for this billing cycle',
                'data' => []
            ], JsonResponse::HTTP_FORBIDDEN);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'data' => []
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to record payment', [
                'uuid' => $uuid,
                'payment_data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to record payment',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get billing cycles by facility
     *
     * @param Request $request
     * @param int $facilityId
     * @return JsonResponse
     */
    public function byFacility(Request $request, int $facilityId): JsonResponse
    {
        try {
            $this->authorize('viewAny', \App\Models\BillingCycle::class);
            
            $filters = $request->only([
                'patient_id', 'billing_status', 'cycle_type',
                'period_start_from', 'period_start_to', 'search',
                'order_by', 'order_direction', 'per_page'
            ]);
            
            $result = $this->billingCycleService->getBillingCyclesByFacility($facilityId, $filters);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $billingCycles = BillingCycleResource::collection($result['data']['billing_cycles']);
            $result['data']['billing_cycles'] = $billingCycles;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve billing cycles by facility', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve billing cycles',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get billing cycles by patient
     *
     * @param Request $request
     * @param int $patientId
     * @return JsonResponse
     */
    public function byPatient(Request $request, int $patientId): JsonResponse
    {
        try {
            $this->authorize('viewAny', \App\Models\BillingCycle::class);
            
            $filters = $request->only([
                'facility_id', 'billing_status', 'cycle_type',
                'period_start_from', 'period_start_to',
                'order_by', 'order_direction', 'per_page'
            ]);
            
            $result = $this->billingCycleService->getBillingCyclesByPatient($patientId, $filters);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $billingCycles = BillingCycleResource::collection($result['data']['billing_cycles']);
            $result['data']['billing_cycles'] = $billingCycles;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve billing cycles by patient', [
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve billing cycles',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get overdue billing cycles
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function overdue(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', \App\Models\BillingCycle::class);
            
            $filters = $request->only([
                'facility_id', 'patient_id', 'per_page'
            ]);
            
            $result = $this->billingCycleService->getOverdueBillingCycles($filters);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $billingCycles = BillingCycleResource::collection($result['data']['billing_cycles']);
            $result['data']['billing_cycles'] = $billingCycles;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve overdue billing cycles', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve overdue billing cycles',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get disputed billing cycles
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function disputed(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', \App\Models\BillingCycle::class);
            
            $filters = $request->only([
                'facility_id', 'patient_id', 'per_page'
            ]);
            
            $result = $this->billingCycleService->getDisputedBillingCycles($filters);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $billingCycles = BillingCycleResource::collection($result['data']['billing_cycles']);
            $result['data']['billing_cycles'] = $billingCycles;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve disputed billing cycles', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve disputed billing cycles',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get financial summary
     *
     * @param Request $request
     * @param int $facilityId
     * @return JsonResponse
     */
    public function financialSummary(Request $request, int $facilityId): JsonResponse
    {
        try {
            $this->authorize('viewAny', \App\Models\BillingCycle::class);
            
            $dateRange = $request->only(['start', 'end']);
            
            $result = $this->billingCycleService->getFinancialSummary($facilityId, $dateRange);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve financial summary', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve financial summary',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}