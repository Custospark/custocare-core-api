<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BillingCycle;
use App\Services\Billing\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Models\FacilityStaffRole;
use App\Models\Staff;
use Illuminate\Support\Facades\Validator;


class BillingController extends Controller
{
        protected BillingService $billingService;

        public function __construct(BillingService $billingService)
        {
            $this->billingService = $billingService;
        }


        /**
 * Resolve facility ID from request header.
 *
 * Frontend already sends X-Facility-Id, so we treat that as authoritative
 * request context for billing operations.
 */
protected function resolveFacilityId(Request $request): int
{
    return (int) $request->header('X-Facility-Id');
}

/**
 * Resolve the currently authenticated staff ID for the active facility.
 *
 * We do not trust frontend to send staff_id directly for audit-sensitive actions.
 * Instead, we derive the staff record from the authenticated user and facility.
 */
protected function resolveCurrentStaffId(Request $request, int $facilityId): ?int
{
    $user = $request->user();
    if (!$user) {
        return null;
    }

    $staffIds = Staff::query()
        ->where('user_id', $user->id)
        ->pluck('id');

    if ($staffIds->isEmpty()) {
        return null;
    }

    $facilityScopedStaffId = FacilityStaffRole::query()
        ->where('facility_id', $facilityId)
        ->whereIn('staff_id', $staffIds)
        ->value('staff_id');

    return $facilityScopedStaffId ?: null;
}

/**
 * Consistent JSON response helper.
 */
protected function respond(array $result, int $successStatus = 200): JsonResponse
{
    $status = $result['success'] ? $successStatus : 422;

    if (!$result['success'] && isset($result['errors']['visit_id'])) {
        $status = 404;
    }

    if (!$result['success'] && isset($result['errors']['line_item_id'])) {
        $status = 404;
    }

    return response()->json($result, $status);
}


    /**
     * Finalize billing for a visit
     *
     * @param Request $request
     * @return JsonResponse
     */
  public function saveBilling(Request $request): JsonResponse
{
    Log::info('Billing finalization request received', [
        'method' => $request->method(),
        'url' => $request->fullUrl(),
        'headers' => [
            'x-facility-id' => $request->header('X-Facility-Id'),
            'x-staff-id' => $request->header('X-Staff-Id'),
            'x-idempotency-key' => $request->header('X-Idempotency-Key'),
            'content-type' => $request->header('Content-Type'),
            'user-agent' => $request->header('User-Agent'),
        ],
        'payload' => $request->all(),
    ]);

    try {
        $facilityId = $this->resolveFacilityId($request);
        $staffId = $this->resolveCurrentStaffId($request, $facilityId);

        $headerErrors = [];

        if (!$facilityId) {
            $headerErrors['facility_id'] = ['X-Facility-Id header is required.'];
        }

        if (!$staffId) {
            $headerErrors['staff_id'] = ['Authenticated staff could not be resolved for this facility.'];
        }

        if (!empty($headerErrors)) {
            return response()->json([
                'success' => false,
                'message' => 'Missing required headers.',
                'errors' => $headerErrors,
            ], 422);
        }

        $data = $request->all();

        Log::debug('Raw billing save payload structure', [
            'has_visit_id' => isset($data['visit_id']),
            'has_patient_id' => isset($data['patient_id']),
            'charge_items_count' => count($data['charge_items'] ?? []),
            'payment_methods_count' => count($data['payment_methods'] ?? []),
            'taxes_count' => count($data['taxes'] ?? []),
            'has_discount' => isset($data['discount']),
            'has_billing_data' => isset($data['billing_data']),
            'status' => $data['status'] ?? null,
            'payment_status' => $data['payment_status'] ?? null,
        ]);

        $rules = [
            'visit_id' => 'required|integer|exists:visits,id',
            'patient_id' => 'required|integer|exists:patients,id',

            'charge_items' => 'required|array|min:1',
            'charge_items.*.service_key' => 'required|string',
            'charge_items.*.service' => 'required|array',
            'charge_items.*.service.id' => 'required|integer',
            'charge_items.*.service.code' => 'required|string',
            'charge_items.*.service.name' => 'required|string',
            'charge_items.*.service.unitPrice' => 'required|numeric|min:0',
            'charge_items.*.service.category' => 'required|string',
            'charge_items.*.quantity' => 'required|numeric|min:0.01',
            'charge_items.*.totalAmount' => 'required|numeric|min:0',

            'discount' => 'required|array',
            'discount.type' => 'required|in:percentage,fixed',
            'discount.value' => 'required|numeric|min:0',
            'discount.reason' => 'nullable|string|max:255',

            'taxes' => 'required|array',
            'taxes.*.name' => 'required|string',
            'taxes.*.rate' => 'required|numeric|min:0|max:100',
            'taxes.*.amount' => 'required|numeric|min:0',

            'payment_methods' => 'nullable|array',
            'payment_methods.*.type' => 'required_with:payment_methods.*.amount|in:cash,card,insurance,mobile,mobile_money,bank_transfer,cheque,mixed,other',
            'payment_methods.*.amount' => 'required_with:payment_methods.*.type|numeric|min:0',
            'payment_methods.*.reference' => 'nullable|string',
            'payment_methods.*.details' => 'nullable',

            'billing_data' => 'required|array',
            'billing_data.subtotal' => 'required|numeric|min:0',
            'billing_data.discountAmount' => 'required|numeric|min:0',
            'billing_data.taxableAmount' => 'required|numeric|min:0',
            'billing_data.taxTotal' => 'required|numeric|min:0',
            'billing_data.grandTotal' => 'required|numeric|min:0',
            'billing_data.totalPaid' => 'required|numeric|min:0',
            'billing_data.balance' => 'required|numeric|min:0',

            'additional_notes' => 'nullable|string',
            'payment_status' => 'nullable|in:pending,partially_paid,paid_in_full',
            'status' => 'required|in:draft,ready,settled',
        ];

        try {
            $validated = $request->validate($rules);
        } catch (ValidationException $e) {
            Log::error('Validation failed for billing finalization', [
                'errors' => $e->errors(),
                'received_keys' => array_keys($data),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed. Please check the request payload structure.',
                'errors' => $e->errors(),
            ], 422);
        }

        $idempotencyKey = trim((string) $request->header('X-Idempotency-Key'));
        if ($idempotencyKey !== '') {
            $validated['idempotency_key'] = $idempotencyKey;
        }

        Log::info('Billing validation passed', [
            'facility_id' => $facilityId,
            'staff_id' => $staffId,
            'visit_id' => $validated['visit_id'],
            'patient_id' => $validated['patient_id'],
            'charge_items_count' => count($validated['charge_items']),
            'payment_methods_count' => count($validated['payment_methods'] ?? []),
            'billing_data' => $validated['billing_data'],
        ]);

        $result = $this->billingService->saveBilling($validated, $facilityId, $staffId);

        if (!$result['success']) {
            Log::warning('Billing service returned error', [
                'visit_id' => $validated['visit_id'],
                'message' => $result['message'] ?? null,
                'errors' => $result['errors'] ?? null,
            ]);

            return response()->json($result, 422);
        }

        $statusCode = !empty($result['data']['idempotent_replay'])
            ? 200
            : (!empty($result['data']['was_existing_cycle_updated']) ? 200 : 201);

        return response()->json($result, $statusCode);
    } catch (ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $e->errors(),
        ], 422);
    } catch (\Throwable $e) {
        Log::error('Billing finalization failed with exception', [
            'error' => $e->getMessage(),
            'error_class' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'An unexpected error occurred while processing the billing.',
            'error' => config('app.debug') ? $e->getMessage() : null,
            'error_type' => config('app.debug') ? get_class($e) : null,
        ], 500);
    }
}

     /**
     * Retrieve billing data for a visit with facility context.
     *
     * This method returns billing data in the SAME format as getByFacility,
     * making it suitable for single-visit billing review.
     *
     * @param Request $request
     * @param int $visitId
     * @return JsonResponse
     */
    public function getByVisitForFacility(Request $request, int $visitId): JsonResponse
    {
        $facilityId = $this->resolveFacilityId($request);
        
        if (!$facilityId) {
            return response()->json([
                'success' => false,
                'message' => 'Missing X-Facility-Id header.',
                'errors' => ['facility_id' => ['Facility ID is required for billing operations.']],
            ], 422);
        }

        $staffId = $this->resolveCurrentStaffId($request, $facilityId);

        $result = $this->billingService->getBillingByVisitForFacility(
            $visitId,
            $facilityId,
            $staffId
        );

        return $this->respond($result);
    }
    /**
 * Retrieve billing data for a visit.
 *
 * The service returns persisted billing in the same structural shape
 * used by the frontend billing slice renderer.
 */
    public function getByVisit(Request $request, int $visitId): JsonResponse
    {
        $facilityId = $this->resolveFacilityId($request);
        $staffId = $this->resolveCurrentStaffId($request, $facilityId);

        $result = $this->billingService->getBillingByVisit(
            $visitId,
            $facilityId,
            $staffId
        );

        return $this->respond($result);
    }

/**
 * Adjust an already persisted billing line item.
 *
 * Enterprise billing rule:
 * - persisted line items are never casually changed on frontend only
 * - any such change must go through audited backend adjustment flow
 */
public function adjustLineItem(Request $request, int $lineItemId): JsonResponse
{
    Log::info("Data",["Data"=>$request->all()]);
    $facilityId = $this->resolveFacilityId($request);
    $staffId = $this->resolveCurrentStaffId($request, $facilityId);

    if (!$staffId) {
        return response()->json([
            'success' => false,
            'message' => 'Unable to resolve current staff context for this billing action.',
            'errors' => [
                'staff' => ['Authenticated staff could not be determined for this facility.'],
            ],
        ], 403);
    }

    $validator = Validator::make($request->all(), [
        'action' => ['required', 'in:increase,decrease,remove'],
        'quantity' => ['nullable', 'numeric', 'min:0'],
        'reason' => ['nullable', 'string', 'max:1000'],
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Billing adjustment validation failed.',
            'errors' => $validator->errors()->toArray(),
        ], 422);
    }

    $result = $this->billingService->adjustBillingLineItem(
        $lineItemId,
        $validator->validated(),
        $facilityId,
        $staffId
    );

    return $this->respond($result);
}

    /**
     * Get all billing data for a facility with pagination and filters
     *
     * @param Request $request
     * @param int $facilityId
     * @return JsonResponse
     */
    public function getByFacility(Request $request, int $facilityId): JsonResponse
    {
        try {
            // Verify facility ID from header matches URL parameter
            $headerFacilityId = (int) $request->header('X-Facility-Id');
            
            if (!$headerFacilityId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing X-Facility-Id header.',
                ], 422);
            }

            if ($headerFacilityId !== $facilityId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility ID mismatch between header and URL.',
                ], 403);
            }

            // Validate filter parameters
            $filters = $request->validate([
              'status' => 'nullable|string|in:draft,pending,pending_review,pending_submission,submitted_to_insurance,partially_paid,paid_in_full,payment_plan,collections,disputed,written_off,charity_care',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'payment_method' => 'nullable|string|in:cash,insurance,card,bank_transfer,mobile,mobile_money,other',
                'min_amount' => 'nullable|numeric|min:0',
                'max_amount' => 'nullable|numeric|min:0|gt:min_amount',
            ]);

            $search = $request->input('search', '');
            $perPage = (int) $request->input('per_page', 15);
            $page = (int) $request->input('page', 1);

            // Ensure reasonable limits
            $perPage = min(max($perPage, 1), 100);
            $page = max($page, 1);

            $result = $this->billingService->getBillingByFacility(
                $facilityId,
                $filters,
                $search,
                $perPage,
                $page
            );

            if (!$result['success']) {
                return response()->json($result, 500);
            }

            return response()->json($result);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve facility billing', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve billing data.',
            ], 500);
        }
    }

    /**
     * Get billing statistics for a facility
     *
     * @param Request $request
     * @param int $facilityId
     * @return JsonResponse
     */
    public function getFacilityStatistics(Request $request, int $facilityId): JsonResponse
    {
        try {
            // Verify facility ID from header matches URL parameter
            $headerFacilityId = (int) $request->header('X-Facility-Id');
            
            if (!$headerFacilityId || $headerFacilityId !== $facilityId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid facility access.',
                ], 403);
            }

            $filters = $request->validate([
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
            ]);

            $result = $this->billingService->getBillingStatistics($facilityId, $filters);

            if (!$result['success']) {
                return response()->json($result, 500);
            }

            return response()->json($result);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve billing statistics', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve billing statistics.',
            ], 500);
        }
    }

    /**
     * Get billing data for a patient across all visits
     *
     * @param Request $request
     * @param int $patientId
     * @return JsonResponse
     */
    public function getByPatient(Request $request, int $patientId): JsonResponse
    {
        try {
            $facilityId = (int) $request->header('X-Facility-Id');

            if (!$facilityId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing X-Facility-Id header.',
                ], 422);
            }

            // Get all billing cycles for this patient at this facility
            $billingCycles = BillingCycle::query()
                ->where('facility_id', $facilityId)
                ->where('patient_id', $patientId)
                ->with(['visit', 'lineItems'])
                ->orderByDesc('created_at')
                ->get();

            if ($billingCycles->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No billing records found for this patient.',
                    'data' => [],
                ]);
            }

            // Transform each billing cycle
            $transformedData = $billingCycles->map(function ($billingCycle) {
                return $this->billingService->transformBillingData(
                    $billingCycle, 
                    $billingCycle->visit
                );
            })->values();

            return response()->json([
                'success' => true,
                'message' => 'Patient billing data retrieved successfully.',
                'data' => $transformedData,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve patient billing', [
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve patient billing data.',
            ], 500);
        }
    }
}