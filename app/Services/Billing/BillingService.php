<?php

namespace App\Services\Billing;

use App\Models\BillingCycle;
use App\Models\FinancialAdjustment;
use App\Models\InvoiceLineItem;
use App\Models\Patient;
use App\Models\Staff;
use App\Models\User;
use App\Models\Visit;
use App\Services\Billing\Processing\BillingProcessor;
use App\Services\Billing\Traits\BillingHelpers;
use App\Services\Billing\Validation\BillingValidation;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Services\Billing\Support\BillingAuditTrailService;


class BillingService
{
    use BillingHelpers;

    protected BillingValidation $validation;
    protected BillingProcessor $processor;

   protected BillingAuditTrailService $billingAuditTrail;

    public function __construct(
        BillingValidation $validation,
        BillingProcessor $processor,
        BillingAuditTrailService $billingAuditTrail
    ) {
        $this->validation = $validation;
        $this->processor = $processor;
        $this->billingAuditTrail = $billingAuditTrail;
    }


    /**
     * Save a billing submission.
     *
     * Design principles in this rewrite:
     * - persisted billing cycle data is authoritative
     * - discounts are owned by the billing cycle, never by line items
     * - payments are derived from persisted cycle history + current request
     * - balances, totals, and statuses are derived by determineBillingState()
     */
    public function saveBilling(array $data, int $facilityId, int $staffId): array
    {
        try {
            $visitVerification = $this->verifyVisit(
                (int) $data['visit_id'],
                $facilityId,
                (int) $data['patient_id']
            );

            if (empty($visitVerification['success'])) {
                return $visitVerification;
            }

            /** @var Visit $visit */
            $visit = $visitVerification['data'];

            $data['charge_items'] = $this->normalizeChargeItems($data['charge_items'] ?? []);
            $data['payment_methods'] = $this->normalizePaymentMethods($data['payment_methods'] ?? []);
            $data['taxes'] = $this->normalizeTaxDefinitions($data['taxes'] ?? []);

            $existingBillingCheck = $this->checkExistingBilling($visit->id, $facilityId);
            if (empty($existingBillingCheck['success'])) {
                return $existingBillingCheck;
            }

            /** @var BillingCycle|null $existingEditableBillingCycle */
            $existingEditableBillingCycle = $existingBillingCheck['data'] ?? null;

            $billingEligibility = $this->canBeBilled($visit, $existingEditableBillingCycle);
            if (empty($billingEligibility['success'])) {
                return $billingEligibility;
            }

            if ($existingEditableBillingCycle === null && empty($data['charge_items'])) {
                return [
                    'success' => false,
                    'message' => 'At least one charge item is required to create a billing cycle.',
                    'errors' => ['charge_items' => ['No charge items were supplied for billing.']],
                ];
            }

            $discountWasProvided = array_key_exists('discount', $data);
            $taxesWereProvided = array_key_exists('taxes', $data) && !empty($data['taxes']);

            $data['discount'] = $this->resolveEffectiveDiscount(
                $discountWasProvided ? ($data['discount'] ?? null) : null,
                $existingEditableBillingCycle,
                $discountWasProvided
            );

            $data['taxes'] = $this->resolveEffectiveTaxes(
                $taxesWereProvided ? $data['taxes'] : null,
                $existingEditableBillingCycle,
                $taxesWereProvided
            );

            $paymentSplit = $this->calculatePaymentSplit(
                $data['payment_methods'] ?? [],
                (float) (($data['billing_data']['totalPaid'] ?? 0))
            );

            $authoritativeState = $this->buildAuthoritativeBillingDataForSave(
                $data['charge_items'],
                $existingEditableBillingCycle,
                $data['discount'],
                $data['taxes'],
                $data['payment_methods'],
                (string) ($data['status'] ?? 'ready')
            );

            if ($this->billingDataMismatch($data['billing_data'] ?? [], $authoritativeState)) {
                Log::warning('Client billing_data mismatch detected. Backend state will be used.', [
                    'visit_id' => $data['visit_id'] ?? null,
                    'provided' => $data['billing_data'] ?? [],
                    'computed' => $this->buildBillingDataFromState($authoritativeState),
                ]);
            }

            $data['billing_data'] = $this->buildBillingDataFromState($authoritativeState);
            $data['payment_status'] = $authoritativeState['payment_status'];
            $data['resolved_billing_status'] = $authoritativeState['billing_status'];
            $data['resolved_ui_status'] = $authoritativeState['ui_status'];
            $data['resolved_total_paid'] = $authoritativeState['total_paid'];
            $data['resolved_balance'] = $authoritativeState['balance'];
            $data['resolved_is_fully_paid'] = $authoritativeState['is_fully_paid'];
            $data['resolved_patient_payment'] = $authoritativeState['patient_payment'];
            $data['resolved_insurance_payment'] = $authoritativeState['insurance_payment'];

            $data['submission_fingerprint'] = $this->buildBillingSubmissionFingerprint($data, $facilityId);
            $replayedCycle = $this->findReplaySubmission(
                (int) $data['visit_id'],
                $facilityId,
                $data['submission_fingerprint']
            );

            if ($replayedCycle) {
                Log::info('Idempotent billing replay detected.', [
                    'visit_id' => $data['visit_id'],
                    'billing_cycle_id' => $replayedCycle->id,
                ]);

                return $this->buildReplaySuccessResponse($replayedCycle);
            }

            $inventoryValidation = $this->validation->validateInventoryAvailability(
                $data['charge_items'],
                $staffId
            );

            if (empty($inventoryValidation['success'])) {
                return $inventoryValidation;
            }

            $largestPaymentMethod = collect($data['payment_methods'] ?? [])
                ->sortByDesc('amount')
                ->first();

            $isPrimaryCash = ($largestPaymentMethod['type'] ?? null) === 'cash';
            $isInsuranceInvolved = $this->isInsuranceInvolved($data['payment_methods'] ?? []);

            $result = $this->processor->processBillingTransaction(
                $data,
                $facilityId,
                $staffId,
                (float) ($authoritativeState['discount_amount'] ?? 0),
                $paymentSplit,
                $isPrimaryCash,
                $isInsuranceInvolved,
                $visit,
                $existingEditableBillingCycle
            );

            /** @var BillingCycle $billingCycle */
            $billingCycle = $result['billing_cycle']->fresh(['lineItems']);
            $finalState = $this->buildCycleStateSnapshot($billingCycle);
            $wasExistingCycleUpdated = $existingEditableBillingCycle !== null;

            Log::info('Billing finalized successfully.', [
                'billing_cycle_id' => $billingCycle->id,
                'visit_id' => $visit->id,
                'facility_id' => $facilityId,
                'staff_id' => $staffId,
                'grand_total' => $finalState['grand_total'],
                'total_paid' => $finalState['total_paid'],
                'balance' => $finalState['balance'],
                'billing_status' => $finalState['billing_status'],
                'was_existing_cycle_updated' => $wasExistingCycleUpdated,
            ]);

            return [
                'success' => true,
                'message' => !empty($finalState['is_fully_paid'])
                    ? 'Payment successfully settled.'
                    : ($wasExistingCycleUpdated
                        ? 'Existing billing cycle updated successfully.'
                        : 'Billing saved successfully.'),
                'data' => [
                    'billing_cycle_id' => $billingCycle->id,
                    'billing_cycle_uuid' => $billingCycle->billing_cycle_uuid,
                    'receipt_number' => 'REC-' . $billingCycle->id,
                    'billing_status' => $billingCycle->billing_status,
                    'net_amount' => round((float) ($billingCycle->net_amount ?? 0), 2),
                    'total_paid' => round((float) ($billingCycle->total_paid_amount ?? 0), 2),
                    'balance' => round((float) ($billingCycle->balance_amount ?? 0), 2),
                    'created_at' => optional($billingCycle->created_at)->toISOString() ?? now()->toISOString(),
                    'line_items_count' => $billingCycle->lineItems->count(),
                    'was_existing_cycle_updated' => $wasExistingCycleUpdated,
                    'idempotent_replay' => false,
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Billing save failed unexpectedly.', [
                'visit_id' => $data['visit_id'] ?? null,
                'patient_id' => $data['patient_id'] ?? null,
                'facility_id' => $facilityId,
                'staff_id' => $staffId,
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return [
                'success' => false,
                'message' => 'An unexpected error occurred while submitting billing. Please try again.',
                'errors' => ['system' => ['Billing transaction failed. All changes have been rolled back.']],
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * Resolve the effective cycle-level discount rule.
     */
    protected function resolveEffectiveDiscount(
        $incomingDiscount,
        ?BillingCycle $existingBillingCycle,
        bool $discountWasProvided
    ): array {
        if ($discountWasProvided) {
            return is_array($incomingDiscount)
                ? $this->normalizeDiscount($incomingDiscount)
                : $this->normalizeDiscount([]);
        }

        if ($existingBillingCycle) {
            $metadata = $this->decodeJsonishToArray($existingBillingCycle->metadata ?? null);
            $storedRule = is_array($metadata['discount_rule'] ?? null)
                ? $metadata['discount_rule']
                : [];

            return $this->normalizeDiscount([
                'type' => $storedRule['type'] ?? 'fixed',
                'value' => $storedRule['value'] ?? $existingBillingCycle->discount_applied ?? 0,
                'reason' => $storedRule['reason'] ?? $existingBillingCycle->discount_reason,
            ]);
        }

        return $this->normalizeDiscount([]);
    }

    /**
     * Resolve the effective tax definitions for the cycle.
     */
    protected function resolveEffectiveTaxes(
        ?array $incomingTaxes,
        ?BillingCycle $existingBillingCycle,
        bool $taxesWereProvided
    ): array {
        if ($taxesWereProvided) {
            return $this->normalizeTaxDefinitions($incomingTaxes ?? []);
        }

        if ($existingBillingCycle) {
            $metadata = $this->decodeJsonishToArray($existingBillingCycle->metadata ?? null);
            $storedTaxDefinitions = $metadata['tax_definitions'] ?? null;

            if (is_array($storedTaxDefinitions) && !empty($storedTaxDefinitions)) {
                return $this->normalizeTaxDefinitions($storedTaxDefinitions);
            }

            return $this->normalizeTaxDefinitions(
                $this->decodeJsonishToArray($existingBillingCycle->tax_details ?? null)
            );
        }

        return [];
    }

    protected function buildReplaySuccessResponse(BillingCycle $billingCycle): array
    {
        $billingCycle = $billingCycle->fresh();

        return [
            'success' => true,
            'message' => 'Duplicate billing submission ignored. Existing billing record returned.',
            'data' => [
                'billing_cycle_id' => $billingCycle->id,
                'billing_cycle_uuid' => $billingCycle->billing_cycle_uuid,
                'receipt_number' => 'REC-' . $billingCycle->id,
                'billing_status' => $billingCycle->billing_status,
                'net_amount' => round((float) ($billingCycle->net_amount ?? 0), 2),
                'total_paid' => round((float) ($billingCycle->total_paid_amount ?? 0), 2),
                'balance' => round((float) ($billingCycle->balance_amount ?? 0), 2),
                'created_at' => optional($billingCycle->created_at)->toISOString() ?? now()->toISOString(),
                'line_items_count' => $billingCycle->lineItems()->count(),
                'was_existing_cycle_updated' => true,
                'idempotent_replay' => true,
            ],
        ];
    }

    public function getBillingByVisit(int $visitId, int $facilityId, ?int $currentStaffId = null): array
    {
        try {
            $visit = Visit::query()
                ->where('id', $visitId)
                ->where('facility_id', $facilityId)
                ->with(['patient.user'])
                ->first();

            if (!$visit) {
                return [
                    'success' => false,
                    'message' => 'Visit not found or does not belong to this facility.',
                    'errors' => ['visit_id' => ['Invalid visit for this facility.']],
                ];
            }

            $billingCycle = BillingCycle::query()
                ->where('visit_id', $visitId)
                ->where('facility_id', $facilityId)
                ->with(['lineItems'])
                ->latest('id')
                ->first();

            if (!$billingCycle) {
                return [
                    'success' => true,
                    'message' => 'No billing record found for this visit.',
                    'data' => [
                        'has_billing' => false,
                        'visit_id' => $visitId,
                        'visit' => [
                            'id' => $visit->id,
                            'current_phase' => $visit->current_phase,
                            'payment_status' => $visit->payment_status,
                            'status' => $visit->status,
                        ],
                        'patient' => [
                            'id' => $visit->patient_id,
                            'name' => $visit->patient?->user?->display_name
                                ?: trim((string) ($visit->patient?->user?->first_name ?? '') . ' ' . (string) ($visit->patient?->user?->last_name ?? '')),
                        ],
                    ],
                ];
            }

            return [
                'success' => true,
                'message' => 'Billing data retrieved successfully.',
                'data' => $this->transformBillingData($billingCycle, $visit, $currentStaffId),
            ];
        } catch (Throwable $e) {
            Log::error('Billing retrieval by visit failed.', [
                'visit_id' => $visitId,
                'facility_id' => $facilityId,
                'error_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving billing data.',
                'errors' => ['system' => ['Failed to retrieve billing information.']],
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
 * Transform persisted billing into the frontend billing shape.
 *
 * Retrieval reuses the same state engine so that read-side numbers stay in
 * lockstep with save-side numbers.
 */
public function transformBillingData(BillingCycle $billingCycle, Visit $visit, ?int $currentStaffId = null): array
{
    $metadata = $this->decodeJsonishToArray($billingCycle->metadata ?? null);
    $lineItems = $billingCycle->relationLoaded('lineItems')
        ? $billingCycle->lineItems
        : $billingCycle->lineItems()->get();

    $billingCycle->setRelation('lineItems', $lineItems);

    $activeLineItems = $this->getActiveBillingCycleLineItems($billingCycle);
    $auditBundle = $this->billingAuditTrail->buildBillingCycleAuditBundle($billingCycle);
    $lineItemAuditMap = $auditBundle['line_items'] ?? [];

    $refundedItems = $this->getRefundedItemsFromAdjustments(
        $billingCycle,
        $currentStaffId,
        $lineItemAuditMap
    );

    $state = $this->buildCycleStateSnapshot($billingCycle);
    $billingData = $this->buildBillingDataFromState($state);

    $paymentMethods = is_array($metadata['payment_methods'] ?? null)
        ? $metadata['payment_methods']
        : [];
    $additionalNotes = isset($metadata['additional_notes'])
        ? (string) $metadata['additional_notes']
        : '';

    $discount = [
        'type' => $state['discount_rule']['type'] ?? 'fixed',
        'value' => ($state['discount_rule']['type'] ?? 'fixed') === 'percentage'
            ? round((float) ($state['discount_rule']['value'] ?? 0), 2)
            : round((float) ($state['discount_amount'] ?? 0), 2),
        'reason' => $state['discount_rule']['reason'] ?? $billingCycle->discount_reason,
    ];

    $patientUser = $visit->patient?->user;
    $patientName = $patientUser?->display_name
        ?: trim((string) ($patientUser->first_name ?? '') . ' ' . (string) ($patientUser->last_name ?? ''));

    return [
        'has_billing' => true,
        'visit_id' => $visit->id,
        'visit_uuid' => $visit->visit_uuid,
        'patient_id' => $visit->patient_id,
        'patient_number' => Patient::query()->where('id', $visit->patient_id)->value('patient_uuid'),
        'patient_name' => $patientName ?: 'Unknown',
        'billing_cycle_id' => $billingCycle->id,
        'billing_cycle_uuid' => $billingCycle->billing_cycle_uuid,
        'receipt_number' => "REC-{$billingCycle->id}",
        'billing_status' => (string) ($billingCycle->billing_status ?? $state['billing_status']),
        'attending_staff_id' => $billingCycle->created_by_staff_id,
        'attending_staff_name' => $this->resolveStaffDisplayName($billingCycle->created_by_staff_id),
        'attending_staff_role' => $this->resolveFacilityRoleCode($billingCycle->created_by_staff_id, $visit->facility_id),
        'attending_staff_display' => $this->resolveAttendingStaffDisplay($billingCycle->created_by_staff_id, $visit->facility_id),
        'charge_items' => $activeLineItems->map(function (InvoiceLineItem $lineItem) use ($billingCycle, $currentStaffId, $lineItemAuditMap) {
            return $this->transformLineItem(
                $lineItem,
                $billingCycle,
                $currentStaffId,
                $lineItemAuditMap[(int) $lineItem->id] ?? []
            );
        })->values()->toArray(),
        'refunded_items' => $refundedItems,
        'discount' => $discount,
        'taxes' => $billingData['taxes'],
        'payment_methods' => $paymentMethods,
        'additional_notes' => $additionalNotes,
        'payment_status' => (string) ($state['payment_status'] ?? 'pending'),
        'status' => (string) ($state['ui_status'] ?? $this->mapBillingStatusToUI((string) $billingCycle->billing_status)),
        'billing_data' => $billingData,
        'audit_logs' => $auditBundle['timeline'] ?? [],
        'audit_logs_summary' => $auditBundle['summary'] ?? [
            'count' => 0,
            'last_event_at' => null,
            'last_event' => null,
        ],
        'billed_at' => optional($billingCycle->billed_at)->toIso8601String(),
        'created_at' => optional($billingCycle->created_at)->toIso8601String(),
        'updated_at' => optional($billingCycle->updated_at)->toIso8601String(),
        'last_updated' => optional($billingCycle->updated_at)->getTimestamp() ? optional($billingCycle->updated_at)->getTimestamp() * 1000 : 0,
        'is_dirty' => false,
        'is_processing' => false,
        '_debug' => [
            'discount_scope' => 'billing_cycle',
            'stored_billing_status' => $billingCycle->billing_status,
            'computed_billing_status' => $state['billing_status'] ?? null,
            'total_line_items' => $lineItems->count(),
            'active_line_items' => $activeLineItems->count(),
            'refunded_items_count' => count($refundedItems),
        ],
    ];
}
/**
 * Get refunded items from financial adjustments for a billing cycle.
 * This extracts the affected_line_items from refund adjustments.
 */
/**
 * Get refunded items from financial adjustments for a billing cycle.
 */
protected function getRefundedItemsFromAdjustments(
    BillingCycle $billingCycle,
    ?int $currentStaffId = null,
    array $lineItemAuditMap = []
): array {
    $refundedItems = [];
    
    // Get all refund adjustments for this billing cycle
    $refundAdjustments = FinancialAdjustment::query()
        ->where('billing_cycle_id', $billingCycle->id)
        ->whereIn('adjustment_type', ['full_refund', 'partial_refund'])
        ->where('status', 'completed')
        ->orderBy('created_at', 'desc')
        ->get();
    
    foreach ($refundAdjustments as $adjustment) {
        // Get the affected line items from the adjustment
        $affectedLineItems = $adjustment->affected_line_items ?? [];
        
        if (is_string($affectedLineItems)) {
            $affectedLineItems = json_decode($affectedLineItems, true);
        }
        
        if (!is_array($affectedLineItems)) {
            continue;
        }
        
        // Get refund methods for this adjustment
        $refundMethods = $adjustment->refund_methods ?? [];
        if (is_string($refundMethods)) {
            $refundMethods = json_decode($refundMethods, true);
        }
        
        // Get the staff member who processed the refund
        $refundedByStaffId = $adjustment->approved_by_staff_id ?? $adjustment->requested_by_staff_id;
        $refundedByStaffName = null;
        $staffNumber = Staff::where('id', $refundedByStaffId)->value('staff_uuid');
        
        if ($refundedByStaffId) {
            $staff = Staff::query()->find($refundedByStaffId);
            if ($staff && $staff->user) {
                $refundedByStaffName = trim(($staff->user->display_name ?: ($staff->user->first_name . ' ' . $staff->user->last_name)));
            }
        }
        
        foreach ($affectedLineItems as $item) {
            // Try to find the original line item to get service details
            $originalLineItem = InvoiceLineItem::query()
                ->where('id', $item['line_item_id'] ?? 0)
                ->withTrashed()
                ->first();
            
            // Get audit logs for this line item
            $lineItemId = (int) ($item['line_item_id'] ?? 0);
            $auditLogs = $lineItemId > 0 ? ($lineItemAuditMap[$lineItemId] ?? []) : [];
            
            $refundedItems[] = [
                'id' => 'refund::' . ($item['line_item_uuid'] ?? $item['line_item_id']),
                'source' => 'refund',
                'persisted' => true,
                'refunded' => true,
                'line_item_id' => $item['line_item_id'] ?? null,
                'line_item_uuid' => $item['line_item_uuid'] ?? null,
                'billing_cycle_id' => $billingCycle->id,
                'adjustment_id' => $adjustment->id,
                'adjustment_reference' => $adjustment->reference_number,
                'adjustment_type' => $adjustment->adjustment_type,
                'adjustment_created_at' => optional($adjustment->created_at)->toIso8601String(),
                'service_key' => $item['service_code'] ?? 'unknown',
                'serviceKey' => $item['service_code'] ?? 'unknown',
                'service' => [
                    'id' => null,
                    'code' => $item['service_code'] ?? '',
                    'name' => $item['service_description'] ?? ($originalLineItem->service_description ?? 'Refunded Item'),
                    'unitPrice' => $originalLineItem 
                        ? round((float) ($originalLineItem->unit_price_at_time ?? 0), 2)
                        : round($item['original_subtotal'] / max(0.01, $item['original_quantity']), 2),
                    'category' => 'Refunded',
                ],
                'quantity' => [
                    'original' => round((float) ($item['original_quantity'] ?? 0), 2),
                    'refunded' => round((float) ($item['refund_quantity'] ?? 0), 2),
                    'remaining' => round((float) ($item['remaining_quantity'] ?? 0), 2),
                ],
                'amounts' => [
                    'original_subtotal' => round((float) ($item['original_subtotal'] ?? 0), 2),
                    'refund_subtotal' => round((float) ($item['refund_subtotal'] ?? 0), 2),
                    'remaining_subtotal' => round((float) ($item['remaining_subtotal'] ?? 0), 2),
                ],
                'totalAmount' => round((float) ($item['refund_subtotal'] ?? 0), 2),
                'line_item_status' => 'refunded',
                'matched_reference' => [
                    'id' => $item['matched_reference_id'] ?? null,
                    'type' => $item['matched_reference_type'] ?? null,
                ],
                'refund_info' => [
                    'refund_amount' => round((float) ($adjustment->adjustment_amount ?? 0), 2),
                    'patient_refund' => round((float) ($adjustment->patient_refund_amount ?? 0), 2),
                    'insurance_refund' => round((float) ($adjustment->insurance_refund_amount ?? 0), 2),
                    'refund_methods' => $refundMethods,
                    'refund_reason' => $adjustment->adjustment_reason,
                    'refunded_at' => optional($adjustment->completed_at)->toIso8601String() ?? optional($adjustment->created_at)->toIso8601String(),
                    'refunded_by_staff_id' => $staffNumber,
                    'refunded_by_staff_name' => $refundedByStaffName,
                ],
                'permissions' => [
                    'entered_by_staff_id' => null,
                    'current_staff_id' => $currentStaffId ?? null,
                    'requires_reason_on_cross_staff_edit' => true,
                    'reason_required' => false,
                    'can_edit_without_reason' => false,
                ],
                'audit' => [
                    'adjustment_id' => $adjustment->id,
                    'reference_number' => $adjustment->reference_number,
                    'adjustment_type' => $adjustment->adjustment_type,
                    'completed_at' => optional($adjustment->completed_at)->toIso8601String(),
                ],
                'audit_logs' => $auditLogs,
                'audit_logs_summary' => [
                    'count' => count($auditLogs),
                    'last_event_at' => $auditLogs[0]['performed_at'] ?? null,
                    'last_event' => $auditLogs[0] ?? null,
                ],
            ];
        }
    }
    
    return $refundedItems;
}
    public function getBillingByVisitForFacility(int $visitId, int $facilityId, ?int $currentStaffId = null): array
    {
        try {
            $visit = Visit::query()
                ->where('id', $visitId)
                ->where('facility_id', $facilityId)
                ->with(['patient.user'])
                ->first();

            if (!$visit) {
                return [
                    'success' => false,
                    'message' => 'Visit not found or does not belong to this facility.',
                    'errors' => ['visit_id' => ['Invalid visit for this facility.']],
                ];
            }

            $billingCycle = BillingCycle::query()
                ->where('visit_id', $visitId)
                ->where('facility_id', $facilityId)
                ->with(['lineItems' => fn ($q) => $q->withTrashed()])
                ->latest('id')
                ->first();

            if (!$billingCycle) {
                return [
                    'success' => true,
                    'message' => 'No billing record found for this visit.',
                    'data' => [
                        'items' => [],
                        'pagination' => [
                            'current_page' => 1,
                            'per_page' => 1,
                            'total_items' => 0,
                            'total_pages' => 0,
                            'from' => null,
                            'to' => null,
                            'has_previous' => false,
                            'has_next' => false,
                        ],
                        'filters_applied' => [],
                        'search_term' => null,
                        'visit_id' => $visitId,
                        'has_billing' => false,
                    ],
                ];
            }

            return [
                'success' => true,
                'message' => 'Billing data retrieved successfully.',
                'data' => [
                    'items' => [$this->transformBillingData($billingCycle, $visit, $currentStaffId)],
                    'pagination' => [
                        'current_page' => 1,
                        'per_page' => 1,
                        'total_items' => 1,
                        'total_pages' => 1,
                        'from' => 1,
                        'to' => 1,
                        'has_previous' => false,
                        'has_next' => false,
                    ],
                    'filters_applied' => [],
                    'search_term' => null,
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Billing retrieval by visit/facility shape failed.', [
                'visit_id' => $visitId,
                'facility_id' => $facilityId,
                'error_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving billing data.',
                'errors' => ['system' => ['Failed to retrieve billing information.']],
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }
  public function getBillingByFacility(
        int $facilityId,
        array $filters = [],
        string $search = '',
        int $perPage = 15,
        int $page = 1
    ): array {
        try {
            $query = BillingCycle::query()
                ->where('facility_id', $facilityId)
                ->withTrashed()
                ->with([
                    'visit' => fn ($q) => $q->withTrashed(),
                    'visit.patient.user',
                    'lineItems' => fn ($q) => $q->withTrashed(),
                ])
                ->latest('id');

            if (!empty($filters['status'])) {
                $query->where('billing_status', $filters['status']);
            }

            if (!empty($filters['date_from'])) {
                $query->whereDate('created_at', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->whereDate('created_at', '<=', $filters['date_to']);
            }

            if (!empty($filters['payment_method'])) {
                $query->where('metadata', 'like', '%' . $filters['payment_method'] . '%');
            }

            if (!empty($filters['min_amount'])) {
                $query->where('grand_total_amount', '>=', (float) $filters['min_amount']);
            }

            if (!empty($filters['max_amount'])) {
                $query->where('grand_total_amount', '<=', (float) $filters['max_amount']);
            }

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('billing_cycle_uuid', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%")
                        ->orWhere('visit_id', 'like', "%{$search}%")
                        ->orWhereHas('visit.patient.user', function ($userQuery) use ($search) {
                            $userQuery->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('display_name', 'like', "%{$search}%");
                        });
                });
            }

            $total = $query->count();
            $billingCycles = $query->forPage($page, $perPage)->get();

            $items = $billingCycles
                ->map(function (BillingCycle $billingCycle) {
                    if (!$billingCycle->visit) {
                        Log::warning('Billing cycle missing visit during facility listing.', [
                            'billing_cycle_id' => $billingCycle->id,
                        ]);

                        return null;
                    }

                    return $this->transformBillingData($billingCycle, $billingCycle->visit);
                })
                ->filter()
                ->values()
                ->toArray();

            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;

            return [
                'success' => true,
                'message' => 'Billing data retrieved successfully.',
                'data' => [
                    'items' => $items,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total_items' => $total,
                        'total_pages' => $totalPages,
                        'from' => $total > 0 ? (($page - 1) * $perPage) + 1 : null,
                        'to' => $total > 0 ? min($page * $perPage, $total) : null,
                        'has_previous' => $page > 1,
                        'has_next' => $page < $totalPages,
                    ],
                    'filters_applied' => array_filter($filters, fn ($value) => $value !== null && $value !== ''),
                    'search_term' => $search !== '' ? $search : null,
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Facility billing retrieval failed.', [
                'facility_id' => $facilityId,
                'filters' => $filters,
                'search' => $search,
                'error_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving billing data.',
                'errors' => ['system' => ['Failed to retrieve billing information.']],
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }
    public function getBillingStatistics(int $facilityId, array $filters = []): array
    {
        try {
            $query = BillingCycle::query()->where('facility_id', $facilityId);

            if (!empty($filters['date_from'])) {
                $query->whereDate('created_at', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->whereDate('created_at', '<=', $filters['date_to']);
            }

            $statusTotals = (clone $query)
                ->selectRaw('billing_status, COUNT(*) as count, SUM(grand_total_amount) as total_amount, SUM(patient_payment_received) as total_patient_paid, SUM(insurance_payment_received) as total_insurance_paid, SUM(discount_applied) as total_discount')
                ->groupBy('billing_status')
                ->get();

            $statistics = [
                'total_billing_cycles' => (clone $query)->count(),
                'total_revenue' => round((float) (clone $query)->sum('grand_total_amount'), 2),
                'total_patient_payments' => round((float) (clone $query)->sum('patient_payment_received'), 2),
                'total_insurance_payments' => round((float) (clone $query)->sum('insurance_payment_received'), 2),
                'total_discounts_applied' => round((float) (clone $query)->sum('discount_applied'), 2),
                'average_cycle_amount' => round((float) ((clone $query)->avg('grand_total_amount') ?? 0), 2),
                'by_status' => [],
            ];

            foreach ($statusTotals as $statusTotal) {
                $uiStatus = $this->mapBillingStatusToUI((string) $statusTotal->billing_status);

                if (!isset($statistics['by_status'][$uiStatus])) {
                    $statistics['by_status'][$uiStatus] = [
                        'count' => 0,
                        'total_amount' => 0.00,
                        'breakdown' => [],
                    ];
                }

                $statistics['by_status'][$uiStatus]['count'] += (int) $statusTotal->count;
                $statistics['by_status'][$uiStatus]['total_amount'] += round((float) $statusTotal->total_amount, 2);
                $statistics['by_status'][$uiStatus]['breakdown'][$statusTotal->billing_status] = [
                    'count' => (int) $statusTotal->count,
                    'total_amount' => round((float) $statusTotal->total_amount, 2),
                    'patient_paid' => round((float) $statusTotal->total_patient_paid, 2),
                    'insurance_paid' => round((float) $statusTotal->total_insurance_paid, 2),
                    'discount_applied' => round((float) $statusTotal->total_discount, 2),
                ];
            }

            return [
                'success' => true,
                'message' => 'Billing statistics retrieved successfully.',
                'data' => $statistics,
            ];
        } catch (Throwable $e) {
            Log::error('Billing statistics retrieval failed.', [
                'facility_id' => $facilityId,
                'filters' => $filters,
                'error_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve billing statistics.',
                'errors' => ['system' => [$e->getMessage()]],
            ];
        }
    }

    public function getBillingByPatient(int $patientId, int $facilityId, ?int $currentStaffId = null): array
    {
        try {
            $billingCycles = BillingCycle::query()
                ->where('facility_id', $facilityId)
                ->where('patient_id', $patientId)
                ->with(['visit.patient.user', 'lineItems'])
                ->latest('id')
                ->get();

            if ($billingCycles->isEmpty()) {
                return [
                    'success' => true,
                    'message' => 'No billing records found for this patient.',
                    'data' => [],
                ];
            }

            $items = $billingCycles
                ->filter(fn (BillingCycle $cycle) => $cycle->visit !== null)
                ->map(fn (BillingCycle $cycle) => $this->transformBillingData($cycle, $cycle->visit, $currentStaffId))
                ->values()
                ->toArray();

            return [
                'success' => true,
                'message' => 'Patient billing data retrieved successfully.',
                'data' => $items,
            ];
        } catch (Throwable $e) {
            Log::error('Patient billing retrieval failed.', [
                'patient_id' => $patientId,
                'facility_id' => $facilityId,
                'error_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve patient billing data.',
                'errors' => ['system' => ['Failed to retrieve patient billing information.']],
            ];
        }
    }

    /**
     * Paginated billing cycles for a patient across all facilities (patient portal).
     * Shapes items like facility billing review plus facility_id / facility_name for each visit.
     */
    public function getBillingForPatientPortal(
        int $patientId,
        array $filters = [],
        string $search = '',
        int $perPage = 25,
        int $page = 1
    ): array {
        try {
            $query = BillingCycle::query()
                ->where('patient_id', $patientId)
                ->withTrashed()
                ->with([
                    'visit' => fn ($q) => $q->withTrashed()->with(['patient.user', 'facility']),
                    'lineItems' => fn ($q) => $q->withTrashed(),
                ])
                ->latest('id');

            if (!empty($filters['status'])) {
                $query->where('billing_status', $filters['status']);
            }

            if (!empty($filters['date_from'])) {
                $query->whereDate('created_at', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->whereDate('created_at', '<=', $filters['date_to']);
            }

            if (!empty($filters['payment_method'])) {
                $query->where('metadata', 'like', '%'.$filters['payment_method'].'%');
            }

            if (!empty($filters['min_amount'])) {
                $query->where('grand_total_amount', '>=', (float) $filters['min_amount']);
            }

            if (!empty($filters['max_amount'])) {
                $query->where('grand_total_amount', '<=', (float) $filters['max_amount']);
            }

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('billing_cycle_uuid', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%")
                        ->orWhere('visit_id', 'like', "%{$search}%")
                        ->orWhereHas('visit.facility', function ($fq) use ($search) {
                            $fq->where('name', 'like', "%{$search}%");
                        });
                });
            }

            $summaryRow = (clone $query)
                ->reorder()
                ->selectRaw('
                    COUNT(*) as receipt_count,
                    ROUND(COALESCE(SUM(grand_total_amount), 0), 2) as total_billed,
                    ROUND(COALESCE(SUM(COALESCE(total_paid_amount, 0)), 0), 2) as total_paid,
                    ROUND(COALESCE(SUM(COALESCE(balance_amount, 0)), 0), 2) as total_balance
                ')
                ->first();

            $total = $query->count();
            $billingCycles = $query->forPage($page, $perPage)->get();

            $items = $billingCycles
                ->map(function (BillingCycle $billingCycle) {
                    if (!$billingCycle->visit) {
                        Log::warning('Billing cycle missing visit during patient portal listing.', [
                            'billing_cycle_id' => $billingCycle->id,
                        ]);

                        return null;
                    }

                    $item = $this->transformBillingData($billingCycle, $billingCycle->visit, null);
                    $facility = $billingCycle->visit->facility;
                    $item['facility_id'] = (int) $billingCycle->visit->facility_id;
                    $item['facility_name'] = $facility?->name;

                    return $item;
                })
                ->filter()
                ->values()
                ->toArray();

            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;

            return [
                'success' => true,
                'message' => 'Patient billing data retrieved successfully.',
                'data' => [
                    'items' => $items,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total_items' => $total,
                        'total_pages' => $totalPages,
                        'from' => $total > 0 ? (($page - 1) * $perPage) + 1 : null,
                        'to' => $total > 0 ? min($page * $perPage, $total) : null,
                        'has_previous' => $page > 1,
                        'has_next' => $page < $totalPages,
                    ],
                    'summary' => [
                        'receipt_count' => (int) ($summaryRow->receipt_count ?? 0),
                        'total_billed' => (float) ($summaryRow->total_billed ?? 0),
                        'total_paid' => (float) ($summaryRow->total_paid ?? 0),
                        'total_balance' => (float) ($summaryRow->total_balance ?? 0),
                    ],
                    'filters_applied' => array_filter($filters, fn ($value) => $value !== null && $value !== ''),
                    'search_term' => $search !== '' ? $search : null,
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Patient portal billing retrieval failed.', [
                'patient_id' => $patientId,
                'error_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving billing data.',
                'errors' => ['system' => ['Failed to retrieve billing information.']],
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    public function adjustBillingLineItem(int $lineItemId, array $data, int $facilityId, int $staffId): array
    {
        try {
            $lineItem = InvoiceLineItem::query()
                ->where('id', $lineItemId)
                ->whereHas('billingCycle', function ($query) use ($facilityId) {
                    $query->where('facility_id', $facilityId);
                })
                ->first();

            if (!$lineItem) {
                return [
                    'success' => false,
                    'message' => 'Billing line item not found for this facility.',
                    'errors' => ['line_item_id' => ['Invalid billing line item.']],
                ];
            }

            $action = (string) ($data['action'] ?? '');
            $quantity = round((float) ($data['quantity'] ?? 1), 2);
            $reason = trim((string) ($data['reason'] ?? ''));

            if (!in_array($action, ['increase', 'decrease', 'remove'], true)) {
                return [
                    'success' => false,
                    'message' => 'Invalid billing adjustment action.',
                    'errors' => ['action' => ['Action must be increase, decrease, or remove.']],
                ];
            }

            if ($action !== 'remove' && $quantity <= 0) {
                return [
                    'success' => false,
                    'message' => 'Quantity must be greater than zero.',
                    'errors' => ['quantity' => ['Quantity must be greater than zero for this action.']],
                ];
            }

            $lineMetadata = $this->decodeJsonishToArray($lineItem->metadata ?? null);
            $enteredByStaffId = $lineMetadata['originated_by_staff_id'] ?? $lineItem->created_by_staff_id;

            if ($this->shouldRequireCrossStaffReason($enteredByStaffId, $staffId) && $reason === '') {
                return [
                    'success' => false,
                    'message' => 'Reason is required when editing an item entered by another staff member.',
                    'errors' => ['reason' => ['Please provide a reason for this cross-staff billing adjustment.']],
                ];
            }

            if ($action === 'increase') {
                $inventoryValidation = $this->validation->validateInventoryAvailability([
                    [
                        'service' => [
                            'code' => $lineItem->service_code,
                            'name' => $lineItem->service_description,
                        ],
                        'quantity' => $quantity,
                    ],
                ], $staffId);

                if (empty($inventoryValidation['success'])) {
                    return $inventoryValidation;
                }
            }

            $result = $this->processor->processPersistedLineItemAdjustment(
                $lineItem,
                $action,
                $quantity,
                $reason !== '' ? $reason : null,
                $staffId
            );

            return [
                'success' => true,
                'message' => 'Billing line item adjusted successfully.',
                'data' => [
                    'billing_cycle_id' => $result['billing_cycle']->id,
                    'billing_cycle_uuid' => $result['billing_cycle']->billing_cycle_uuid,
                    'line_item_id' => $result['line_item']->id,
                    'line_item_uuid' => $result['line_item']->line_item_uuid,
                    'billing_status' => $result['billing_cycle']->billing_status,
                    'total_paid' => round((float) ($result['billing_cycle']->total_paid_amount ?? 0), 2),
                    'balance' => round((float) ($result['billing_cycle']->balance_amount ?? 0), 2),
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Billing line item adjustment failed.', [
                'line_item_id' => $lineItemId,
                'facility_id' => $facilityId,
                'staff_id' => $staffId,
                'error_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'An unexpected error occurred while adjusting the billing item.',
                'errors' => ['system' => ['Billing adjustment failed.']],
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    public function shouldRequireCrossStaffReason(?int $enteredByStaffId, ?int $currentStaffId): bool
    {
        if (!$enteredByStaffId || !$currentStaffId) {
            return true;
        }

        return (int) $enteredByStaffId !== (int) $currentStaffId;
    }

    public function buildLineItemEditPolicy(?int $enteredByStaffId, ?int $currentStaffId): array
    {
        $reasonRequired = $this->shouldRequireCrossStaffReason($enteredByStaffId, $currentStaffId);

        return [
            'entered_by_staff_id' => $enteredByStaffId,
            'current_staff_id' => $currentStaffId,
            'requires_reason_on_cross_staff_edit' => true,
            'reason_required' => $reasonRequired,
            'can_edit_without_reason' => !$reasonRequired,
        ];
    }

    /**
     * Build a read-side cycle state snapshot from stored data + active line items.
     */
    protected function buildCycleStateSnapshot(BillingCycle $billingCycle): array
    {
        $metadata = $this->decodeJsonishToArray($billingCycle->metadata ?? null);
        $discountRule = is_array($metadata['discount_rule'] ?? null)
            ? $metadata['discount_rule']
            : [
                'type' => 'fixed',
                'value' => $billingCycle->discount_applied ?? 0,
                'reason' => $billingCycle->discount_reason,
            ];

        $taxDefinitions = is_array($metadata['tax_definitions'] ?? null) && !empty($metadata['tax_definitions'])
            ? $metadata['tax_definitions']
            : $this->decodeJsonishToArray($billingCycle->tax_details ?? null);

        $requestedUiStatus = (string) ($metadata['frontend_ui_status'] ?? $this->mapBillingStatusToUI((string) $billingCycle->billing_status));
        $subtotal = $this->getActiveBillingCycleSubtotal($billingCycle);

        return $this->determineBillingState(
            $subtotal,
            $discountRule,
            $taxDefinitions,
            [
                'patient_payment' => 0,
                'insurance_payment' => 0,
                'total_paid' => 0,
            ],
            $requestedUiStatus,
            $billingCycle
        );
    }

   protected function transformLineItem(
    InvoiceLineItem $lineItem,
    BillingCycle $billingCycle,
    ?int $currentStaffId = null,
    array $auditLogs = []
): array {
    $serviceSnapshot = $this->decodeJsonishToArray($lineItem->service_version_snapshot ?? null);
    $lineMetadata = $this->decodeJsonishToArray($lineItem->metadata ?? null);

    $serviceCode = (string) ($lineItem->service_code ?? '');
    $serviceKey = (string) ($lineMetadata['service_key'] ?? ($serviceCode !== '' ? "key::{$serviceCode}" : 'key::unknown'));
    $enteredByStaffId = $lineMetadata['originated_by_staff_id']
        ?? $lineItem->created_by_staff_id
        ?? $billingCycle->created_by_staff_id;

    return [
        'id' => $lineItem->line_item_uuid ? 'backend-charge::' . $lineItem->line_item_uuid : 'backend-charge::' . $lineItem->id,
        'source' => 'backend',
        'persisted' => true,
        'line_item_id' => $lineItem->id,
        'line_item_uuid' => $lineItem->line_item_uuid,
        'billing_cycle_id' => $billingCycle->id,
        'service_key' => $serviceKey,
        'serviceKey' => $serviceKey,
        'service' => [
            'id' => $lineItem->service_version_id ?? ($serviceSnapshot['id'] ?? null),
            'code' => $serviceCode,
            'name' => (string) ($lineItem->service_description ?? ($serviceSnapshot['name'] ?? '')),
            'unitPrice' => round((float) ($lineItem->unit_price_at_time ?? 0), 2),
            'category' => (string) ($lineMetadata['category'] ?? ($serviceSnapshot['category'] ?? 'General')),
        ],
        'quantity' => round((float) ($lineItem->quantity ?? 0), 2),
        'totalAmount' => round((float) ($lineItem->line_total_amount ?? 0), 2),
        'line_item_status' => (string) ($lineItem->line_item_status ?? 'pending'),
        'entered_by_staff_id' => $enteredByStaffId,
        'entered_by_staff_name' => $this->resolveStaffDisplayName($enteredByStaffId),
        'permissions' => $this->buildLineItemEditPolicy($enteredByStaffId ? (int) $enteredByStaffId : null, $currentStaffId),
        'audit' => [
            'originated_by_staff_id' => $enteredByStaffId,
            'last_adjusted_by_staff_id' => $lineMetadata['last_adjusted_by_staff_id'] ?? null,
            'last_appended_by_staff_id' => $lineMetadata['last_appended_by_staff_id'] ?? null,
            'last_adjusted_at' => $lineMetadata['last_adjusted_at'] ?? null,
            'adjustment_history' => $lineMetadata['adjustment_history'] ?? [],
            'discount_scope' => 'billing_cycle',
        ],
        'audit_logs' => $auditLogs,
        'audit_logs_summary' => [
            'count' => count($auditLogs),
            'last_event_at' => $auditLogs[0]['performed_at'] ?? null,
            'last_event' => $auditLogs[0] ?? null,
        ],
    ];
}
    protected function resolveStaffDisplayName(?int $staffId): ?string
    {
        if (!$staffId) {
            return null;
        }

        $staff = Staff::query()->find($staffId);
        if (!$staff) {
            return null;
        }

        $user = User::query()->find($staff->user_id);
        if (!$user) {
            return null;
        }

        return trim((string) ($user->display_name ?: (($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))));
    }

    protected function resolveFacilityRoleCode(?int $staffId, int $facilityId): ?string
    {
        if (!$staffId) {
            return null;
        }

        return \App\Models\FacilityStaffRole::query()
            ->where('staff_id', $staffId)
            ->where('facility_id', $facilityId)
            ->value('role_code');
    }

    protected function resolveAttendingStaffDisplay(?int $staffId, int $facilityId): ?string
    {
        $name = $this->resolveStaffDisplayName($staffId);
        $role = $this->resolveFacilityRoleCode($staffId, $facilityId);

        if (!$role) {
            return $name;
        }

        $formattedRole = ucwords(strtolower(str_replace(['_', '-'], ' ', $role)));

        return $name ? "{$name} ({$formattedRole})" : $formattedRole;
    }
}