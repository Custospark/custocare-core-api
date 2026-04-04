<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FacilityStaffRole;
use App\Models\Staff;
use App\Services\Billing\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BillingController extends Controller
{
    protected BillingService $billingService;

    public function __construct(BillingService $billingService)
    {
        $this->billingService = $billingService;
    }

    /**
     * Resolve facility context from the request header.
     *
     * Billing is facility-scoped. We therefore treat the facility header as the
     * authoritative execution context for save, retrieval, and adjustment flows.
     */
    protected function resolveFacilityId(Request $request): int
    {
        return (int) $request->header('X-Facility-Id');
    }

    /**
     * Resolve the authenticated staff member for the active facility.
     *
     * We do not accept a caller-supplied staff identifier for audit-sensitive
     * operations. Instead, we derive the staff member from the authenticated user
     * and then constrain it to the active facility.
     */
    protected function resolveCurrentStaffId(Request $request, int $facilityId): ?int
    {
        $user = $request->user();

        if (!$user || $facilityId <= 0) {
            return null;
        }

        $staffIds = Staff::query()
            ->where('user_id', $user->id)
            ->pluck('id');

        if ($staffIds->isEmpty()) {
            return null;
        }

        $staffId = FacilityStaffRole::query()
            ->where('facility_id', $facilityId)
            ->whereIn('staff_id', $staffIds)
            ->value('staff_id');

        return $staffId ? (int) $staffId : null;
    }

    /**
     * Standard response envelope for service results.
     */
    protected function respond(array $result, int $successStatus = 200): JsonResponse
    {
        $status = !empty($result['success']) ? $successStatus : 422;

        if (empty($result['success']) && isset($result['errors']['visit_id'])) {
            $status = 404;
        }

        if (empty($result['success']) && isset($result['errors']['line_item_id'])) {
            $status = 404;
        }

        return response()->json($result, $status);
    }

    /**
     * Validate that the request carries a usable facility/staff execution context.
     */
    protected function validateExecutionContext(Request $request): array|JsonResponse
    {
        $facilityId = $this->resolveFacilityId($request);
        $staffId = $this->resolveCurrentStaffId($request, $facilityId);

        $errors = [];

        if ($facilityId <= 0) {
            $errors['facility_id'] = ['X-Facility-Id header is required.'];
        }

        if ($staffId === null) {
            $errors['staff_id'] = ['Authenticated staff could not be resolved for this facility.'];
        }

        if (!empty($errors)) {
            return response()->json([
                'success' => false,
                'message' => 'Billing request context could not be established.',
                'errors' => $errors,
            ], 422);
        }

        return [
            'facility_id' => $facilityId,
            'staff_id' => $staffId,
        ];
    }

    /**
     * Finalize or append billing for a visit.
     *
     * The backend remains authoritative for all monetary values. Client supplied
     * totals are accepted only as advisory input and are recalculated before
     * persistence.
     */
    public function saveBilling(Request $request): JsonResponse
    {
        Log::info('Billing save request received', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'facility_header' => $request->header('X-Facility-Id'),
            'idempotency_key_present' => trim((string) $request->header('X-Idempotency-Key')) !== '',
            'authenticated_user_id' => optional($request->user())->id,
        ]);

        try {
            $context = $this->validateExecutionContext($request);
            if ($context instanceof JsonResponse) {
                return $context;
            }

            $rules = [
                'visit_id' => ['required', 'integer', 'exists:visits,id'],
                'patient_id' => ['required', 'integer', 'exists:patients,id'],

                'charge_items' => ['nullable', 'array'],
                'charge_items.*.service_key' => ['nullable', 'string'],
                'charge_items.*.serviceKey' => ['nullable', 'string'],
                'charge_items.*.service' => ['required_with:charge_items', 'array'],
                'charge_items.*.service.id' => ['required_with:charge_items', 'integer'],
                'charge_items.*.service.code' => ['required_with:charge_items', 'string'],
                'charge_items.*.service.name' => ['required_with:charge_items', 'string'],
                'charge_items.*.service.unitPrice' => ['required_with:charge_items', 'numeric', 'min:0'],
                'charge_items.*.service.category' => ['nullable', 'string'],
                'charge_items.*.quantity' => ['required_with:charge_items', 'numeric', 'min:0.01'],
                'charge_items.*.totalAmount' => ['nullable', 'numeric', 'min:0'],

                'discount' => ['nullable', 'array'],
                'discount.type' => ['required_with:discount', 'in:percentage,fixed'],
                'discount.value' => ['required_with:discount', 'numeric', 'min:0'],
                'discount.reason' => ['nullable', 'string', 'max:255'],

                'taxes' => ['nullable', 'array'],
                'taxes.*.name' => ['required_with:taxes', 'string'],
                'taxes.*.rate' => ['required_with:taxes', 'numeric', 'min:0', 'max:100'],
                'taxes.*.amount' => ['nullable', 'numeric', 'min:0'],

                'payment_methods' => ['nullable', 'array'],
                'payment_methods.*.type' => ['required_with:payment_methods.*.amount', 'in:cash,card,insurance,mobile,mobile_money,bank_transfer,cheque,mixed,other'],
                'payment_methods.*.amount' => ['required_with:payment_methods.*.type', 'numeric', 'min:0'],
                'payment_methods.*.reference' => ['nullable', 'string'],
                'payment_methods.*.details' => ['nullable'],

                'billing_data' => ['required', 'array'],
                'billing_data.subtotal' => ['nullable', 'numeric', 'min:0'],
                'billing_data.discountAmount' => ['nullable', 'numeric', 'min:0'],
                'billing_data.taxableAmount' => ['nullable', 'numeric', 'min:0'],
                'billing_data.taxTotal' => ['nullable', 'numeric', 'min:0'],
                'billing_data.grandTotal' => ['nullable', 'numeric', 'min:0'],
                'billing_data.totalPaid' => ['nullable', 'numeric', 'min:0'],
                'billing_data.balance' => ['nullable', 'numeric', 'min:0'],

                'additional_notes' => ['nullable', 'string'],
                'payment_status' => ['nullable', 'in:pending,partially_paid,paid_in_full'],
                'status' => ['required', 'in:draft,ready,settled'],
            ];

            $validated = $request->validate($rules);

            $validated['charge_items'] = $validated['charge_items'] ?? [];
            $validated['payment_methods'] = $validated['payment_methods'] ?? [];
            $validated['taxes'] = $validated['taxes'] ?? [];
            $validated['billing_data'] = $validated['billing_data'] ?? [];

            $idempotencyKey = trim((string) $request->header('X-Idempotency-Key'));
            if ($idempotencyKey !== '') {
                $validated['idempotency_key'] = $idempotencyKey;
            }

            $result = $this->billingService->saveBilling(
                $validated,
                $context['facility_id'],
                $context['staff_id']
            );

            if (empty($result['success'])) {
                Log::warning('Billing save failed', [
                    'visit_id' => $validated['visit_id'] ?? null,
                    'facility_id' => $context['facility_id'],
                    'staff_id' => $context['staff_id'],
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
                'message' => 'Validation failed. Please review the billing payload.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Billing save crashed unexpectedly', [
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing billing.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'error_type' => config('app.debug') ? get_class($e) : null,
            ], 500);
        }
    }

    /**
     * Retrieve billing data for a visit in the facility list shape.
     */
    public function getByVisitForFacility(Request $request, int $visitId): JsonResponse
    {
        $facilityId = $this->resolveFacilityId($request);

        if ($facilityId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Missing X-Facility-Id header.',
                'errors' => ['facility_id' => ['Facility ID is required for billing operations.']],
            ], 422);
        }

        $staffId = $this->resolveCurrentStaffId($request, $facilityId);

        $result = $this->billingService->getBillingByVisitForFacility($visitId, $facilityId, $staffId);

        return $this->respond($result);
    }

    /**
     * Retrieve billing data for a single visit.
     */
    public function getByVisit(Request $request, int $visitId): JsonResponse
    {
        $facilityId = $this->resolveFacilityId($request);

        if ($facilityId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Missing X-Facility-Id header.',
                'errors' => ['facility_id' => ['Facility ID is required for billing operations.']],
            ], 422);
        }

        $staffId = $this->resolveCurrentStaffId($request, $facilityId);

        $result = $this->billingService->getBillingByVisit($visitId, $facilityId, $staffId);

        return $this->respond($result);
    }

    /**
     * Adjust a persisted billing line item through the audited backend flow.
     */
    public function adjustLineItem(Request $request, int $lineItemId): JsonResponse
    {
        $context = $this->validateExecutionContext($request);
        if ($context instanceof JsonResponse) {
            return $context;
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
            $context['facility_id'],
            $context['staff_id']
        );

        return $this->respond($result);
    }

    /**
     * Retrieve billing records for a facility with filters and pagination.
     */
    public function getByFacility(Request $request, int $facilityId): JsonResponse
    {
        try {
            $headerFacilityId = $this->resolveFacilityId($request);

            if ($headerFacilityId <= 0) {
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

            $filters = $request->validate([
                'status' => ['nullable', 'string', 'in:draft,pending,pending_review,pending_submission,submitted_to_insurance,partially_paid,paid_in_full,payment_plan,collections,disputed,written_off,charity_care'],
                'date_from' => ['nullable', 'date'],
                'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
                'payment_method' => ['nullable', 'string', 'in:cash,insurance,card,bank_transfer,mobile,mobile_money,other'],
                'min_amount' => ['nullable', 'numeric', 'min:0'],
                'max_amount' => ['nullable', 'numeric', 'min:0', 'gte:min_amount'],
            ]);

            $search = trim((string) $request->input('search', ''));
            $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
            $page = max((int) $request->input('page', 1), 1);

            $result = $this->billingService->getBillingByFacility(
                $facilityId,
                $filters,
                $search,
                $perPage,
                $page
            );

            return response()->json($result, !empty($result['success']) ? 200 : 500);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Facility billing retrieval failed', [
                'facility_id' => $facilityId,
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve billing data.',
            ], 500);
        }
    }

    /**
     * Retrieve billing statistics for a facility.
     */
    public function getFacilityStatistics(Request $request, int $facilityId): JsonResponse
    {
        try {
            $headerFacilityId = $this->resolveFacilityId($request);

            if ($headerFacilityId <= 0 || $headerFacilityId !== $facilityId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid facility access.',
                ], 403);
            }

            $filters = $request->validate([
                'date_from' => ['nullable', 'date'],
                'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            ]);

            $result = $this->billingService->getBillingStatistics($facilityId, $filters);

            return response()->json($result, !empty($result['success']) ? 200 : 500);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Facility billing statistics retrieval failed', [
                'facility_id' => $facilityId,
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve billing statistics.',
            ], 500);
        }
    }

    /**
     * Retrieve all billing records for a patient within the active facility.
     */
    public function getByPatient(Request $request, int $patientId): JsonResponse
    {
        try {
            $facilityId = $this->resolveFacilityId($request);

            if ($facilityId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing X-Facility-Id header.',
                    'errors' => ['facility_id' => ['Facility ID is required for billing operations.']],
                ], 422);
            }

            $staffId = $this->resolveCurrentStaffId($request, $facilityId);

            $result = $this->billingService->getBillingByPatient($patientId, $facilityId, $staffId);

            return response()->json($result, !empty($result['success']) ? 200 : 500);
        } catch (\Throwable $e) {
            Log::error('Patient billing retrieval failed', [
                'patient_id' => $patientId,
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve patient billing data.',
            ], 500);
        }
    }
}