<?php

namespace App\Services\Billing;

use App\Models\Visit;
use App\Models\BillingCycle;
use App\Services\Billing\Traits\BillingHelpers;
use App\Services\Billing\Validation\BillingValidation;
use App\Services\Billing\Processing\BillingProcessor;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Billing Service
 *
 * Main entry point for billing operations from controllers.
 * Handles business logic orchestration with atomic transactions
 * and comprehensive error handling.
 */
class BillingService
{
    use BillingHelpers;

    /**
     * @var BillingValidation
     */
    protected BillingValidation $validation;

    /**
     * @var BillingProcessor
     */
    protected BillingProcessor $processor;

    /**
     * BillingService constructor.
     *
     * @param BillingValidation $validation
     * @param BillingProcessor $processor
     */
    public function __construct(
        BillingValidation $validation,
        BillingProcessor $processor
    ) {
        $this->validation = $validation;
        $this->processor = $processor;
    }

    /**
     * Finalize billing and persist to database with atomic transaction
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

            // Step 4: Validate inventory availability BEFORE transaction
            $inventoryValidation = $this->validation->validateInventoryAvailability($data['charge_items'], $staffId);
            
            if (!$inventoryValidation['success']) {
                return $inventoryValidation;
            }

            // Step 5: Calculate discount amount
            $discountAmount = $this->calculateDiscountAmount(
                $data['discount']['type'],
                $data['discount']['value'],
                $data['billing_data']['subtotal']
            );

            // Step 6: Calculate payment split
            $paymentSplit = $this->calculatePaymentSplit(
                $data['payment_methods'],
                $data['billing_data']['totalPaid']
            );

            // Step 7: Determine primary payment method
            $primaryPaymentMethod = collect($data['payment_methods'])
                ->sortByDesc('amount')
                ->first();

            $isPrimaryCash = $primaryPaymentMethod['type'] === 'cash';
            $isInsuranceInvolved = $this->isInsuranceInvolved($data['payment_methods']);

            // Step 8: Process billing within atomic transaction
            $result = $this->processor->processBillingTransaction(
                $data,
                $facilityId,
                $staffId,
                $discountAmount,
                $paymentSplit,
                $isPrimaryCash,
                $isInsuranceInvolved,
                $visit
            );

            // Calculate balance for response context
            $balance = $data['billing_data']['grandTotal'] - $data['billing_data']['totalPaid'];
            $isFullyPaid = $balance <= 0;

            Log::info('Billing finalized successfully', [
                'billing_cycle_id' => $result['billing_cycle']->id,
                'visit_id' => $data['visit_id'],
                'staff_id' => $staffId,
                'grand_total' => $data['billing_data']['grandTotal'],
                'is_fully_paid' => $isFullyPaid,
            ]);

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

        } catch (Throwable $e) {
            Log::error('Failed to finalize billing', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'visit_id' => $data['visit_id'] ?? null,
                'patient_id' => $data['patient_id'] ?? null,
                'facility_id' => $facilityId,
                'staff_id' => $staffId,
            ]);

            return [
                'success' => false,
                'message' => 'An unexpected error occurred while finalizing billing. Please try again or contact support if the issue persists.',
                'errors' => ['system' => ['Billing transaction failed. All changes have been rolled back.']],
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
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

            Log::info('Billing data retrieved successfully', [
                'visit_id' => $visitId,
                'billing_cycle_id' => $billingCycle->id,
            ]);

            return [
                'success' => true,
                'message' => 'Billing data retrieved successfully.',
                'data' => $billingData,
            ];

        } catch (Throwable $e) {
            Log::error('Failed to retrieve billing data', [
                'visit_id' => $visitId,
                'facility_id' => $facilityId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving billing data. Please try again or contact support.',
                'errors' => ['system' => ['Failed to retrieve billing information.']],
                'error' => config('app.debug') ? $e->getMessage() : null,
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
                    } catch (Throwable $e) {
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