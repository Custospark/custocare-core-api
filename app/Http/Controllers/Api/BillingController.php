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

    return $facilityScopedStaffId ?: $staffIds->first();
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
    {           // Log full incoming request for debugging

        Log::info("Request Data", ["request" => $request->all()]);
        Log::info('Billing finalization request received', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => [
                'x-facility-id' => $request->header('X-Facility-Id'),
                'x-staff-id' => $request->header('X-Staff-Id'),
                'content-type' => $request->header('Content-Type'),
                'user-agent' => $request->header('User-Agent'),
            ],
            'payload' => $request->all(),
        ]);

        try {
            // Get facility and staff IDs from headers/context
            $facilityId = (int) $request->header('X-Facility-Id');
            $staffId = (int) $request->header('X-Staff-Id');

            // Validate headers first
            $headerErrors = [];
            
            if (!$facilityId) {
                $headerErrors['facility_id'] = ['X-Facility-Id header is required.'];
                Log::error('Missing X-Facility-Id header', [
                    'headers' => $request->headers->all(),
                ]);
            }

            if (!$staffId) {
                $headerErrors['staff_id'] = ['X-Staff-Id header is required.'];
                Log::error('Missing X-Staff-Id header', [
                    'headers' => $request->headers->all(),
                ]);
            }

            if (!empty($headerErrors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing required headers.',
                    'errors' => $headerErrors,
                ], 422);
            }

            // Get all request data
            $data = $request->all();
            
            // Log raw data structure for validation comparison
            Log::debug('Raw request data structure', [
                'has_visit_id' => isset($data['visit_id']),
                'has_patient_id' => isset($data['patient_id']),
                'has_charge_items' => isset($data['charge_items']),
                'charge_items_count' => isset($data['charge_items']) ? count($data['charge_items']) : 0,
                'has_discount' => isset($data['discount']),
                'has_taxes' => isset($data['taxes']),
                'taxes_count' => isset($data['taxes']) ? count($data['taxes']) : 0,
                'has_payment_methods' => isset($data['payment_methods']),
                'payment_methods_count' => isset($data['payment_methods']) ? count($data['payment_methods']) : 0,
                'has_billing_data' => isset($data['billing_data']),
               'has_status' => isset($data['status']),
                'status_value' => $data['status'] ?? null,
                'has_payment_status' => isset($data['payment_status']),
                'payment_status_value' => $data['payment_status'] ?? null,

                            ]);

           // Keep the UI status exactly as the frontend sent it.
            // IMPORTANT:
            // - draft   => still editable
            // - ready   => prepared for billing but not necessarily paid
            // - settled => only valid when fully paid
            //
            // The authoritative payment/billing state will be derived later
            // from actual amounts and payment methods in the service layer.
            $originalStatus = $data['status'] ?? null;

            $request->replace($data);


            // Define validation rules for reference
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
                'charge_items.*.quantity' => 'required|integer|min:1',
                'charge_items.*.totalAmount' => 'required|numeric|min:0',
                'discount' => 'required|array',
                'discount.type' => 'required|in:percentage,fixed',
                'discount.value' => 'required|numeric|min:0',
                'discount.reason' => 'nullable|string|max:255',
                'taxes' => 'required|array',
                'taxes.*.name' => 'required|string',
                'taxes.*.rate' => 'required|numeric|min:0|max:100',
                'taxes.*.amount' => 'required|numeric|min:0',
                'payment_methods' => 'nullable|array|min:0',
                'payment_methods.*.type' => 'nullable|in:cash,card,insurance,mobile,bank_transfer,cheque,mixed,other',
                'payment_methods.*.amount' => 'nullable|numeric|min:0',
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
                // Frontend may send UI status + payment_status.
                // We accept them, but payment_status will still be re-derived from actual amounts later.
                'payment_status' => 'nullable|in:pending,partially_paid,paid_in_full',
                'status' => 'required|in:draft,ready,settled',
            ];

            try {
                // Validate request data
                $validated = $request->validate($rules);
                
            } catch (ValidationException $e) {
                // Log detailed validation errors for debugging
                Log::error('Validation failed for billing finalization', [
                    'errors' => $e->errors(),
                    'received_data' => [
                        'visit_id' => $data['visit_id'] ?? null,
                        'patient_id' => $data['patient_id'] ?? null,
                        'charge_items_count' => count($data['charge_items'] ?? []),
                        'payment_methods_count' => count($data['payment_methods'] ?? []),
                        'taxes_count' => count($data['taxes'] ?? []),
                        'has_discount' => isset($data['discount']),
                        'has_billing_data' => isset($data['billing_data']),
                        'status' => $data['status'] ?? null,
                    ],
                    'sample_charge_item' => isset($data['charge_items'][0]) ? [
                        'has_service_key' => isset($data['charge_items'][0]['service_key']),
                        'has_service' => isset($data['charge_items'][0]['service']),
                        'service_fields' => isset($data['charge_items'][0]['service']) ? 
                            array_keys($data['charge_items'][0]['service']) : [],
                        'has_quantity' => isset($data['charge_items'][0]['quantity']),
                        'has_totalAmount' => isset($data['charge_items'][0]['totalAmount']),
                    ] : null,
                    'sample_payment_method' => isset($data['payment_methods'][0]) ? [
                        'has_type' => isset($data['payment_methods'][0]['type']),
                        'has_amount' => isset($data['payment_methods'][0]['amount']),
                        'has_reference' => isset($data['payment_methods'][0]['reference']),
                        'type_value' => $data['payment_methods'][0]['type'] ?? null,
                    ] : null,
                    'billing_data_fields' => isset($data['billing_data']) ? 
                        array_keys($data['billing_data']) : [],
                    'required_fields_present' => [
                        'visit_id' => isset($data['visit_id']),
                        'patient_id' => isset($data['patient_id']),
                        'charge_items' => isset($data['charge_items']) && is_array($data['charge_items']),
                        'discount' => isset($data['discount']) && is_array($data['discount']),
                        'taxes' => isset($data['taxes']) && is_array($data['taxes']),
                        'payment_methods' => isset($data['payment_methods']) && is_array($data['payment_methods']),
                        'billing_data' => isset($data['billing_data']) && is_array($data['billing_data']),
                        'status' => isset($data['status']),
                    ],
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed. Please check the request payload structure.',
                    'errors' => $e->errors(),
                    'debug' => config('app.debug') ? [
                        'received_structure' => [
                            'keys' => array_keys($data),
                            'charge_items_keys' => isset($data['charge_items'][0]) ? 
                                array_keys($data['charge_items'][0]) : [],
                            'payment_methods_keys' => isset($data['payment_methods'][0]) ? 
                                array_keys($data['payment_methods'][0]) : [],
                            'billing_data_keys' => isset($data['billing_data']) ? 
                                array_keys($data['billing_data']) : [],
                        ],
                    ] : null,
                ], 422);
            }

            // Log successful validation
            Log::info('Billing validation passed', [
                'facility_id' => $facilityId,
                'staff_id' => $staffId,
                'visit_id' => $validated['visit_id'],
                'patient_id' => $validated['patient_id'],
                'original_status' => $originalStatus,
                'final_status' => $validated['status'],
                'grand_total' => $validated['billing_data']['grandTotal'],
                'total_paid' => $validated['billing_data']['totalPaid'],
                'balance' => $validated['billing_data']['balance'],
                'payment_methods_count' => count($validated['payment_methods']),
                'charge_items_count' => count($validated['charge_items']),
            ]);

            // Process billing
            $result = $this->billingService->saveBilling($validated, $facilityId, $staffId);

            if (!$result['success']) {
                Log::warning('Billing service returned error', [
                    'visit_id' => $validated['visit_id'],
                    'errors' => $result['errors'] ?? null,
                    'message' => $result['message'] ?? null,
                ]);
                
                return response()->json($result, 422);
            }

            Log::info('Billing finalized successfully', [
                'visit_id' => $validated['visit_id'],
                'receipt_number' => $result['data']['receipt_number'] ?? null,
                'transaction_id' => $result['data']['transaction_id'] ?? null,
            ]);

            $statusCode = !empty($result['data']['was_existing_cycle_updated']) ? 200 : 201;
            return response()->json($result, $statusCode);

        } catch (ValidationException $e) {
            // This should not happen as we're catching ValidationException above,
            // but kept for safety
            Log::error('Unexpected validation exception', [
                'errors' => $e->errors(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('Billing finalization failed with exception', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => [
                    'visit_id' => $data['visit_id'] ?? null,
                    'patient_id' => $data['patient_id'] ?? null,
                    'has_charge_items' => isset($data['charge_items']),
                ],
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
                'payment_method' => 'nullable|string|in:cash,insurance,card,bank_transfer,mobile_money,other',
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