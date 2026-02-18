<?php

namespace App\Services\Billing;

use App\Models\BillingCycle;
use App\Models\InvoiceLineItem;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Billing Service
 *
 * Handles business logic for billing operations
 */
class BillingService
{
    /**
     * Finalize billing and persist to database
     *
     * @param array $data Validated billing data
     * @param int $facilityId Facility ID
     * @param int $staffId Staff ID performing the operation
     * @return array Success status and result data
     */
    public function finalizeBilling(array $data, int $facilityId, int $staffId): array
    {
        try {
            // Step 1: Verify visit belongs to facility and patient
            $visitVerification = $this->verifyVisit(
                $data['visit_id'],
                $facilityId,
                $data['patient_id']
            );

            if (!$visitVerification['success']) {
                return $visitVerification;
            }

            $visit = $visitVerification['data'];

            // Step 2: Check if visit can be billed
            $billingEligibility = $this->canBeBilled($visit);
            
            if (!$billingEligibility['success']) {
                return $billingEligibility;
            }

            // Step 3: Check for existing billing cycle to prevent duplicate billing
            $existingBillingCheck = $this->checkExistingBilling($visit->id, $facilityId);
            
            if (!$existingBillingCheck['success']) {
                return $existingBillingCheck;
            }

            // Step 4: Calculate discount amount
            $discountAmount = $this->calculateDiscountAmount(
                $data['discount']['type'],
                $data['discount']['value'],
                $data['billing_data']['subtotal']
            );

            // Step 5: Calculate payment split
            $paymentSplit = $this->calculatePaymentSplit(
                $data['payment_methods'],
                $data['billing_data']['totalPaid']
            );

            // Step 6: Determine primary payment method
            $primaryPaymentMethod = collect($data['payment_methods'])
                ->sortByDesc('amount')
                ->first();

            $isPrimaryCash = $primaryPaymentMethod['type'] === 'cash';
            $isInsuranceInvolved = $this->isInsuranceInvolved($data['payment_methods']);

            // Step 7: Begin database transaction
            $result = DB::transaction(function () use (
                $data,
                $facilityId,
                $staffId,
                $discountAmount,
                $paymentSplit,
                $isPrimaryCash,
                $isInsuranceInvolved,
                $visit
            ) {
                // Create BillingCycle record
                $billingCycle = $this->createBillingCycle(
                    $data,
                    $facilityId,
                    $staffId,
                    $discountAmount,
                    $paymentSplit,
                    $isPrimaryCash,
                    $isInsuranceInvolved
                );

                // Create InvoiceLineItem records
                $lineItems = $this->createLineItems(
                    $data,
                    $billingCycle->id,
                    $staffId,
                    $discountAmount
                );

                // Update visit with billing information
                $this->updateVisitBillingStatus($visit, $data, $billingCycle, $staffId);

                return [
                    'billing_cycle' => $billingCycle->fresh(),
                    'line_items' => $lineItems,
                ];
            });

            // Calculate balance for response context
            $balance = $data['billing_data']['grandTotal'] - $data['billing_data']['totalPaid'];
            $isFullyPaid = $balance <= 0;

            // Return success response with original structure
            return [
                'success' => true,
                'message' => $isFullyPaid 
                    ? 'Payment successfully settled. Visit has been completed.' 
                    : 'Billing finalized successfully.',
                'data' => [
                    'billing_cycle_id' => $result['billing_cycle']->id,
                    'billing_cycle_uuid' => $result['billing_cycle']->billing_cycle_uuid,
                    'receipt_number' => "REC-{$result['billing_cycle']->id}",
                    'billing_status' => $result['billing_cycle']->billing_status,
                    'net_amount' => $result['billing_cycle']->net_amount,
                    'created_at' => $result['billing_cycle']->created_at->toISOString(),
                    'line_items_count' => count($result['line_items']),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to finalize billing in service', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'visit_id' => $data['visit_id'] ?? null,
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while finalizing billing.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * Check if a visit can be billed
     *
     * @param Visit $visit
     * @return array Success status and message
     */
    public function canBeBilled(Visit $visit): array
    {
        // Check if visit is already completed
        if ($visit->status === 'completed') {
            return [
                'success' => false,
                'message' => 'Cannot bill a completed visit.',
                'errors' => ['visit' => ['This visit has already been completed and cannot be billed again.']],
            ];
        }

        // Check if visit is cancelled
        if ($visit->status === 'cancelled') {
            return [
                'success' => false,
                'message' => 'Cannot bill a cancelled visit.',
                'errors' => ['visit' => ['This visit has been cancelled and cannot be billed.']],
            ];
        }

        // Check if visit is in terminal phase
        $terminalPhases = ['discharged', 'expired', 'transferred'];
        if (in_array($visit->current_phase, $terminalPhases)) {
            return [
                'success' => false,
                'message' => 'Cannot bill a visit that is already ' . $visit->current_phase . '.',
                'errors' => ['visit' => ['This visit is already in a terminal phase.']],
            ];
        }

        // Check payment status - if already paid in full, prevent re-billing
        if ($visit->payment_status === 'paid_in_full') {
            return [
                'success' => false,
                'message' => 'Visit payment is already settled.',
                'errors' => ['visit' => ['This visit has already been paid in full.']],
            ];
        }

        return [
            'success' => true,
            'message' => 'Visit is eligible for billing.',
        ];
    }

    /**
     * Check if visit already has an existing billing cycle
     *
     * @param int $visitId
     * @param int $facilityId
     * @return array Success status and message
     */
    public function checkExistingBilling(int $visitId, int $facilityId): array
    {
        $existingBillingCycle = BillingCycle::query()
            ->where('visit_id', $visitId)
            ->where('facility_id', $facilityId)
            ->whereIn('billing_status', ['paid_in_full', 'submitted_to_insurance', 'pending_submission'])
            ->first();

        if ($existingBillingCycle) {
            return [
                'success' => false,
                'message' => 'This visit already has an active billing cycle.',
                'errors' => ['billing' => ['A billing cycle already exists for this visit.']],
            ];
        }

        return [
            'success' => true,
            'message' => 'No existing billing cycle found.',
        ];
    }

    /**
     * Verify visit belongs to facility and patient
     *
     * @param int $visitId Visit ID
     * @param int $facilityId Facility ID
     * @param int $patientId Patient ID
     * @return array Success status and visit data
     */
    public function verifyVisit(int $visitId, int $facilityId, int $patientId): array
    {
        $visit = Visit::query()
            ->where('id', $visitId)
            ->where('facility_id', $facilityId)
            ->where('patient_id', $patientId)
            ->first();

        if (!$visit) {
            return [
                'success' => false,
                'message' => 'Visit not found or does not belong to this facility/patient.',
                'errors' => ['visit_id' => ['Invalid visit for the given facility and patient.']],
            ];
        }

        return [
            'success' => true,
            'data' => $visit,
        ];
    }

    /**
     * Calculate discount amount based on type
     *
     * @param string $type Discount type (percentage or fixed)
     * @param float $value Discount value
     * @param float $subtotal Subtotal amount
     * @return float Calculated discount amount
     */
    public function calculateDiscountAmount(string $type, float $value, float $subtotal): float
    {
        return $type === 'percentage'
            ? $subtotal * ($value / 100)
            : $value;
    }

    /**
     * Determine if insurance is involved in payment methods
     *
     * @param array $paymentMethods Payment methods array
     * @return bool True if insurance is involved
     */
    public function isInsuranceInvolved(array $paymentMethods): bool
    {
        return collect($paymentMethods)->contains('type', 'insurance');
    }

    /**
     * Calculate insurance and patient payments
     *
     * @param array $paymentMethods Payment methods array
     * @param float $totalPaid Total amount paid
     * @return array Insurance and patient payment amounts
     */
    public function calculatePaymentSplit(array $paymentMethods, float $totalPaid): array
    {
        $insurancePayment = collect($paymentMethods)
            ->where('type', 'insurance')
            ->sum('amount');
        
        $patientPayment = $totalPaid - $insurancePayment;

        return [
            'insurance_payment' => $insurancePayment,
            'patient_payment' => $patientPayment,
        ];
    }

    /**
     * Map database billing status to UI status
     *
     * @param string $billingStatus Database billing status
     * @return string UI status (draft, ready, settled)
     */
    public function mapBillingStatusToUI(string $billingStatus): string
    {
        $statusMap = [
            'draft' => 'draft',
            'pending_review' => 'draft',
            'pending_submission' => 'ready',
            'submitted_to_insurance' => 'ready',
            'partially_paid' => 'ready',
            'paid_in_full' => 'settled',
            'payment_plan' => 'ready',
            'collections' => 'ready',
            'disputed' => 'ready',
            'written_off' => 'settled',
            'charity_care' => 'settled',
        ];

        return $statusMap[$billingStatus] ?? 'draft';
    }

    /**
     * Calculate pro-rated discount for a line item
     *
     * @param float $lineTotal Line item total
     * @param float $subtotal Overall subtotal
     * @param float $totalDiscount Total discount amount
     * @return float Pro-rated discount for this line item
     */
    public function calculateLineItemDiscount(float $lineTotal, float $subtotal, float $totalDiscount): float
    {
        if ($subtotal <= 0) {
            return 0;
        }

        return ($lineTotal / $subtotal) * $totalDiscount;
    }

    /**
 * Update visit with billing information
 *
 * @param Visit $visit
 * @param array $data
 * @param BillingCycle $billingCycle
 * @param int $staffId
 * @return void
 */
protected function updateVisitBillingStatus(Visit $visit, array $data, BillingCycle $billingCycle, int $staffId): void
{
    // Calculate balance
    $balance = $data['billing_data']['grandTotal'] - $data['billing_data']['totalPaid'];
    $isFullyPaid = $balance <= 0;

    // Update visit to completed when payment is in full
    if ($isFullyPaid) {
        $visit->current_phase = 'discharged'; 
        $visit->status = 'completed'; 
        $visit->clinical_care_ended_at = now();
        $visit->discharged_at = now();
    } else {
        // For partial payments, update to billing phase if not in terminal phase
        if (!in_array($visit->current_phase, ['discharged', 'completed', 'expired', 'transferred'])) {
            $visit->current_phase = 'billing'; 
        }
    }

    // Update payment status based on balance
    if ($isFullyPaid) {
        $visit->payment_status = 'paid_in_full'; 
    } elseif ($data['billing_data']['totalPaid'] > 0) {
        $visit->payment_status = 'partially_paid'; 
    } else {
        $visit->payment_status = 'pending'; 
    }

    // Update financial snapshot
    $visit->estimated_total_charges = $data['billing_data']['grandTotal'];
    $visit->patient_estimated_responsibility = $data['billing_data']['totalPaid'];

    // Update audit trail
    $visit->updated_by_staff_id = $staffId;

    // Add billing metadata
    $metadata = $visit->metadata ?? [];
    
    // Initialize billing array if not exists
    if (!isset($metadata['billing'])) {
        $metadata['billing'] = [];
    }
    
    // Add this billing transaction
    $metadata['billing'][] = [
        'billing_cycle_id' => $billingCycle->id,
        'billing_cycle_uuid' => $billingCycle->billing_cycle_uuid,
        'completed_at' => now()->toIso8601String(),
        'completed_by_staff_id' => $staffId,
        'receipt_number' => "REC-{$billingCycle->id}",
        'grand_total' => $data['billing_data']['grandTotal'],
        'total_paid' => $data['billing_data']['totalPaid'],
        'balance' => $balance,
        'is_fully_paid' => $isFullyPaid,
        'payment_methods' => $data['payment_methods'],
        'discount' => [
            'type' => $data['discount']['type'],
            'value' => $data['discount']['value'],
            'reason' => $data['discount']['reason'] ?? null,
        ],
    ];

    // Store latest billing summary at root level for quick access
    $metadata['latest_billing'] = [
        'billing_cycle_id' => $billingCycle->id,
        'receipt_number' => "REC-{$billingCycle->id}",
        'completed_at' => now()->toIso8601String(),
        'grand_total' => $data['billing_data']['grandTotal'],
        'balance' => $balance,
        'payment_status' => $visit->payment_status,
    ];

    // If visit is completed, add completion metadata
    if ($isFullyPaid) {
        $metadata['visit_completion'] = [
            'completed_at' => now()->toIso8601String(),
            'completed_by_staff_id' => $staffId,
            'completion_reason' => 'billing_finalized',
            'final_balance' => $balance,
            'billing_cycle_id' => $billingCycle->id,
            'receipt_number' => "REC-{$billingCycle->id}",
        ];
    }

    $visit->metadata = $metadata;

    $visit->save();
    
    Log::info('Visit billing status updated', [
        'visit_id' => $visit->id,
        'payment_status' => $visit->payment_status,
        'current_phase' => $visit->current_phase,
        'visit_status' => $visit->status,
        'balance' => $balance,
        'is_fully_paid' => $isFullyPaid,
        'billing_cycle_id' => $billingCycle->id,
    ]);
}

    /**
     * Create BillingCycle record
     *
     * @param array $data Billing data
     * @param int $facilityId Facility ID
     * @param int $staffId Staff ID
     * @param float $discountAmount Calculated discount amount
     * @param array $paymentSplit Payment split data
     * @param bool $isPrimaryCash Is primary payment cash
     * @param bool $isInsuranceInvolved Is insurance involved
     * @return BillingCycle
     */
    protected function createBillingCycle(
        array $data,
        int $facilityId,
        int $staffId,
        float $discountAmount,
        array $paymentSplit,
        bool $isPrimaryCash,
        bool $isInsuranceInvolved
    ): BillingCycle {
        return BillingCycle::create([
            'billing_cycle_uuid' => Str::uuid(),
            'facility_id' => $facilityId,
            'visit_id' => $data['visit_id'],
            'patient_id' => $data['patient_id'],
            
            'cycle_type' => 'visit_based',
            'period_start' => now(),
            'period_end' => now(),
            'days_in_cycle' => 1,
            
            // Financial summary
            'total_amount_charged' => $data['billing_data']['subtotal'],
            'total_adjustments' => $discountAmount,
            'net_amount' => $data['billing_data']['grandTotal'],
            
            // Insurance (if applicable)
            'insurance_covered_amount' => $paymentSplit['insurance_payment'],
            'insurance_payment_received' => $paymentSplit['insurance_payment'],
            'insurance_claim_submitted_at' => $isInsuranceInvolved ? now() : null,
            'insurance_payment_received_at' => $isInsuranceInvolved ? now() : null,
            
            // Patient responsibility
            'patient_responsibility_amount' => $paymentSplit['patient_payment'],
            'patient_payment_received' => $paymentSplit['patient_payment'],
            
            // Discount
            'discount_applied' => $discountAmount,
            'discount_reason' => $data['discount']['reason'] ?? null,
            
            // Tax
            'tax_details' => json_encode($data['taxes']),
            'total_tax_amount' => $data['billing_data']['taxTotal'],
            
            // Status
            'billing_status' => $data['status'] === 'settled' ? 'paid_in_full' : 'draft',
            'billed_at' => $data['status'] === 'settled' ? now() : null,
            'payment_due_date' => now()->addDays(30),
            
            // Audit
            'created_by_staff_id' => $staffId,
            'updated_by_staff_id' => $staffId,
            'metadata' => json_encode([
                'payment_methods' => $data['payment_methods'],
                'additional_notes' => $data['additional_notes'] ?? null,
                'is_cash_payment' => $isPrimaryCash,
            ]),
        ]);
    }

    /**
     * Create InvoiceLineItem records
     *
     * @param array $data Billing data
     * @param int $billingCycleId Billing cycle ID
     * @param int $staffId Staff ID
     * @param float $discountAmount Total discount amount
     * @return array Created line items
     */
    protected function createLineItems(
        array $data,
        int $billingCycleId,
        int $staffId,
        float $discountAmount
    ): array {
        $lineItems = [];

        foreach ($data['charge_items'] as $chargeItem) {
            $service = $chargeItem['service'];
            $quantity = $chargeItem['quantity'];
            $unitPrice = $service['unitPrice'];
            $lineTotal = $chargeItem['totalAmount'];

            // Calculate pro-rated discount for this line item
            $lineDiscountAmount = $this->calculateLineItemDiscount(
                $lineTotal,
                $data['billing_data']['subtotal'],
                $discountAmount
            );

            $netAmount = $lineTotal - $lineDiscountAmount;

            $lineItem = InvoiceLineItem::create([
                'line_item_uuid' => Str::uuid(),
                'billing_cycle_id' => $billingCycleId,
                'visit_id' => $data['visit_id'],
                
                // Service snapshot (frozen at time of billing)
                // 'service_version_id' => $service['id'],//TODO: To be uncommented in the feature after integrating with service version.
                'service_version_snapshot' => json_encode($service),
                'service_code' => $service['code'],
                'service_description' => $service['name'],
                
                // Quantity & pricing
                'quantity' => $quantity,
                'unit_of_measure' => 'each',
                'unit_price_at_time' => $unitPrice,
                'line_total_amount' => $lineTotal,
                
                // Discount
                'discount_amount' => $lineDiscountAmount,
                'net_amount' => $netAmount,
                
                // Service delivery
                'staff_performed_id' => $staffId,
                'service_performed_at' => now(),
                
                // Status
                'line_item_status' => $data['status'] === 'settled' ? 'paid' : 'pending',
                
                // Audit trail (SHA-256 hash for tamper detection)
                'audit_trail_hash' => hash('sha256', json_encode([
                    'service_code' => $service['code'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'timestamp' => now()->toISOString(),
                ])),
                
                'created_by_staff_id' => $staffId,
                'metadata' => json_encode([
                    'service_key' => $chargeItem['service_key'],
                    'category' => $service['category'],
                ]),
            ]);

            $lineItems[] = $lineItem;
        }

        return $lineItems;
    }

    /**
     * Get billing data for a visit
     *
     * @param int $visitId Visit ID
     * @param int $facilityId Facility ID
     * @return array Success status and billing data
     */
    public function getBillingByVisit(int $visitId, int $facilityId): array
    {
        try {
            // Verify visit exists and belongs to facility
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

            // Get billing cycle for this visit
            $billingCycle = BillingCycle::query()
                ->where('visit_id', $visitId)
                ->where('facility_id', $facilityId)
                ->with(['lineItems'])
                ->orderByDesc('created_at')
                ->first();

            // If no billing cycle exists, return empty state
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
                            'name' => $visit->patient->user->display_name ?? 
                                     "{$visit->patient->user->first_name} {$visit->patient->user->last_name}",
                        ],
                    ],
                ];
            }

            // Parse and transform billing data
            $billingData = $this->transformBillingData($billingCycle, $visit);

            return [
                'success' => true,
                'message' => 'Billing data retrieved successfully.',
                'data' => $billingData,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve billing data in service', [
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while retrieving billing data.',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Transform billing data for response
     *
     * @param BillingCycle $billingCycle Billing cycle model
     * @param Visit $visit Visit model
     * @return array Transformed billing data
     */
    protected function transformBillingData(BillingCycle $billingCycle, Visit $visit): array
    {
        // ---- Helpers ------------------------------------------------------------

        $decodeJsonish = function ($value): array {
            if (is_array($value)) {
                return $value;
            }

            if (is_string($value) && trim($value) !== '') {
                $decoded = json_decode($value, true);
                return is_array($decoded) ? $decoded : [];
            }

            return [];
        };

        $toIso = function ($date): ?string {
            if (!$date) {
                return null;
            }

            if (is_object($date)) {
                if (method_exists($date, 'toIso8601String')) {
                    return $date->toIso8601String();
                }
                if (method_exists($date, 'format')) {
                    return $date->format('c');
                }
            }

            if (is_string($date) && trim($date) !== '') {
                $ts = strtotime($date);
                return $ts ? date(DATE_ATOM, $ts) : null;
            }

            return null;
        };

        $toEpochMs = function ($date): int {
            if (!$date) {
                return 0;
            }

            if (is_object($date)) {
                if (method_exists($date, 'getTimestamp')) {
                    return (int) $date->getTimestamp() * 1000;
                }
                if (method_exists($date, 'timestamp')) {
                    try {
                        $ts = $date->timestamp;
                        return is_numeric($ts) ? ((int) $ts * 1000) : 0;
                    } catch (\Throwable $e) {
                        return 0;
                    }
                }
            }

            if (is_string($date) && trim($date) !== '') {
                $ts = strtotime($date);
                return $ts ? ((int) $ts * 1000) : 0;
            }

            return 0;
        };

        // ---- Parse cycle-level blobs -------------------------------------------

        $metadata = $decodeJsonish($billingCycle->metadata ?? null);
        $paymentMethods = is_array($metadata['payment_methods'] ?? null) ? $metadata['payment_methods'] : [];
        $additionalNotes = (string) ($metadata['additional_notes'] ?? '');

        $taxes = $decodeJsonish($billingCycle->tax_details ?? null);

        // Discount normalization
        $discountApplied = (float) ($billingCycle->discount_applied ?? 0);
        $discountReason  = $billingCycle->discount_reason ?? null;

        $discount = [
            'type'   => $discountApplied > 0 ? ($discountReason ? 'fixed' : 'percentage') : 'percentage',
            'value'  => $discountApplied,
            'reason' => $discountReason,
        ];

        // ---- Line items -> charge_items ----------------------------------------

        $lineItems = $billingCycle->lineItems ?? collect();

        $chargeItems = $lineItems->map(function ($lineItem) use ($decodeJsonish) {
            $serviceSnapshot = $decodeJsonish($lineItem->service_version_snapshot ?? null);
            $lineMetadata    = $decodeJsonish($lineItem->metadata ?? null);

            $serviceCode = (string) ($lineItem->service_code ?? '');
            $serviceKey  = (string) ($lineMetadata['service_key'] ?? ($serviceCode !== '' ? "key::{$serviceCode}" : "key::unknown"));

            $uuid = $lineItem->line_item_uuid ?? null;
            $chargeId = $uuid ? "charge::{$uuid}" : "charge::{$lineItem->id}";

            return [
                'id' => $chargeId,
                'service_key' => $serviceKey,
                'service' => [
                    'id' => $lineItem->service_version_id ?? ($serviceSnapshot['id'] ?? null),
                    'code' => $serviceCode,
                    'name' => (string) ($lineItem->service_description ?? ($serviceSnapshot['name'] ?? '')),
                    'unitPrice' => (float) ($lineItem->unit_price_at_time ?? 0),
                    'category' => (string) ($lineMetadata['category'] ?? ($serviceSnapshot['category'] ?? 'General')),
                ],
                'quantity' => (int) ($lineItem->quantity ?? 0),
                'totalAmount' => (float) ($lineItem->line_total_amount ?? 0),
            ];
        })->values()->toArray();

        // ---- Totals summary -----------------------------------------------------

        $totalCharged = (float) ($billingCycle->total_amount_charged ?? 0);
        $totalTax     = (float) ($billingCycle->total_tax_amount ?? 0);
        $netAmount    = (float) ($billingCycle->net_amount ?? 0);

        $patientPaid   = (float) ($billingCycle->patient_payment_received ?? 0);
        $insurancePaid = (float) ($billingCycle->insurance_payment_received ?? 0);

        $billingData = [
            'subtotal'       => $totalCharged,
            'discountAmount' => $discountApplied,
            'taxableAmount'  => max(0, $totalCharged - $discountApplied),
            'taxes'          => $taxes,
            'taxTotal'       => $totalTax,
            'grandTotal'     => $netAmount,
            'totalPaid'      => $patientPaid + $insurancePaid,
            'balance'        => 0,
            'isPaid'         => true,
        ];

        // ---- Status mapping -----------------------------------------------------

        $uiStatus = $this->mapBillingStatusToUI($billingCycle->billing_status);

        // ---- Patient name safe fallback ----------------------------------------

        $patientUser = $visit->patient->user ?? null;
        $patientName = null;

        if ($patientUser) {
            $patientName = $patientUser->display_name
                ?? trim((string) ($patientUser->first_name ?? '') . ' ' . (string) ($patientUser->last_name ?? ''));
        }

        // ---- Response -----------------------------------------------------------

        return [
            'has_billing' => true,

            // Visit & patient info
            'visit_id'      => $visit->id,
            'visit_uuid'    => $visit->visit_uuid,
            'patient_id'    => $visit->patient_id,
            'patient_name'  => $patientName ?: 'Unknown',

            // Billing cycle info
            'billing_cycle_id'   => $billingCycle->id,
            'billing_cycle_uuid' => $billingCycle->billing_cycle_uuid,
            'receipt_number'     => "REC-{$billingCycle->id}",

            // Redux-shaped billing state
            'charge_items'      => $chargeItems,
            'discount'          => $discount,
            'taxes'             => $taxes,
            'payment_methods'   => $paymentMethods,
            'additional_notes'  => $additionalNotes,
            'status'            => $uiStatus,
            'billing_status'    => $billingCycle->billing_status,

            // Calculated
            'billing_data' => $billingData,

            // Timestamps (safe ISO)
            'billed_at'  => $toIso($billingCycle->billed_at ?? null),
            'created_at' => $toIso($billingCycle->created_at ?? null),
            'updated_at' => $toIso($billingCycle->updated_at ?? null),

            // Metadata (safe epoch ms)
            'last_updated'   => $toEpochMs($billingCycle->updated_at ?? null),
            'is_dirty'       => false,
            'is_processing'  => false,
        ];
    }
}