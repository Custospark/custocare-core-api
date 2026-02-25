<?php

namespace App\Services\Billing\Processing;

use App\Models\BillingCycle;
use App\Models\InvoiceLineItem;
use App\Models\InventoryItem;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Billing Processor Service
 *
 * Handles core processing logic including transactions, inventory deduction,
 * and creation of billing records
 */
class BillingProcessor
{
    /**
     * Process billing within a database transaction
     *
     * @param array $data Validated billing data
     * @param int $facilityId Facility ID
     * @param int $staffId Staff ID
     * @param float $discountAmount Calculated discount amount
     * @param array $paymentSplit Payment split data (now includes total_paid)
     * @param bool $isPrimaryCash Is primary payment cash
     * @param bool $isInsuranceInvolved Is insurance involved
     * @param Visit $visit Visit model
     * @return array Processed billing data
     * @throws Throwable
     */
    public function processBillingTransaction(
        array $data,
        int $facilityId,
        int $staffId,
        float $discountAmount,
        array $paymentSplit,
        bool $isPrimaryCash,
        bool $isInsuranceInvolved,
        Visit $visit
    ): array {
        return DB::transaction(function () use (
            $data,
            $facilityId,
            $staffId,
            $discountAmount,
            $paymentSplit,
            $isPrimaryCash,
            $isInsuranceInvolved,
            $visit
        ) {
            // Create BillingCycle record with corrected payment amounts
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

            // Reduce inventory stock for inventory items
            $this->deductInventoryStock($data['charge_items'], $staffId);

            // Update visit with billing information using corrected payment data
            $this->updateVisitBillingStatus($visit, $data, $billingCycle, $staffId, $paymentSplit);

            return [
                'billing_cycle' => $billingCycle->fresh(),
                'line_items' => $lineItems,
            ];
        });
    }

    /**
     * Create BillingCycle record
     * 
     * FIXED: Use paymentSplit['total_paid'] instead of data['billing_data']['totalPaid']
     * to ensure consistency with actual payment methods
     *
     * @param array $data Billing data
     * @param int $facilityId Facility ID
     * @param int $staffId Staff ID
     * @param float $discountAmount Calculated discount amount
     * @param array $paymentSplit Payment split data (with total_paid)
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
        // Use the validated total from payment methods
        $validatedTotalPaid = $paymentSplit['total_paid'] ?? 
            ($paymentSplit['insurance_payment'] + $paymentSplit['patient_payment']);
        
        // Calculate if this is truly paid in full
        $grandTotal = $data['billing_data']['grandTotal'];
        $isFullyPaid = abs($validatedTotalPaid - $grandTotal) < 0.01; // Account for floating point
        
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
            'net_amount' => $grandTotal,
            
            // Insurance (if applicable)
            'insurance_covered_amount' => $paymentSplit['insurance_payment'],
            'insurance_payment_received' => $paymentSplit['insurance_payment'],
            'insurance_claim_submitted_at' => $isInsuranceInvolved ? now() : null,
            'insurance_payment_received_at' => $isInsuranceInvolved ? now() : null,
            
            // Patient responsibility - FIXED: Use actual patient payment from methods
            'patient_responsibility_amount' => $paymentSplit['patient_payment'],
            'patient_payment_received' => $paymentSplit['patient_payment'],
            
            // Discount
            'discount_applied' => $discountAmount,
            'discount_reason' => $data['discount']['reason'] ?? null,
            
            // Tax
            'tax_details' => json_encode($data['taxes']),
            'total_tax_amount' => $data['billing_data']['taxTotal'],
            
            // Status - Note:Currently processing only full payments.
            'billing_status' => 'paid_in_full', 
            'billed_at' => $validatedTotalPaid > 0 ? now() : null,
            'payment_due_date' => now()->addDays(30),
            
            // Audit
            'created_by_staff_id' => $staffId,
            'updated_by_staff_id' => $staffId,
            'metadata' => json_encode([
                'payment_methods' => $data['payment_methods'],
                'additional_notes' => $data['additional_notes'] ?? null,
                'is_cash_payment' => $isPrimaryCash,
                'validated_total_paid' => $validatedTotalPaid, // Store for audit
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

            // Collect all service codes first
            $serviceCodes = array_map(function($chargeItem) {
                return $chargeItem['service']['code'];
            }, $data['charge_items']);

            // Batch load all inventory items and service catalog entries
            $inventoryItems = \App\Models\InventoryItem::whereIn('item_code', $serviceCodes)
                ->get()
                ->keyBy('item_code');
            
            $serviceCatalogs = \App\Models\ServiceCatalog::whereIn('service_code', $serviceCodes)
                ->get()
                ->keyBy('service_code');

            foreach ($data['charge_items'] as $chargeItem) {
                $service = $chargeItem['service'];
                $quantity = $chargeItem['quantity'];
                $unitPrice = $service['unitPrice'];
                $lineTotal = $chargeItem['totalAmount'];
                $serviceCode = $service['code'];

                // Get IDs from our pre-loaded collections
                $inventoryItem = $inventoryItems->get($serviceCode);
                $serviceCatalog = $serviceCatalogs->get($serviceCode);

                // Calculate pro-rated discount for this line item
                $lineDiscountAmount = $this->calculateLineItemDiscount(
                    $lineTotal,
                    $data['billing_data']['subtotal'],
                    $discountAmount
                );

                $netAmount = $lineTotal - $lineDiscountAmount;

                $lineItemData = [
                    'line_item_uuid' => Str::uuid(),
                    'billing_cycle_id' => $billingCycleId,
                    'visit_id' => $data['visit_id'],
                    
                    // Service snapshot
                    'service_version_snapshot' => json_encode($service),
                    'service_code' => $serviceCode,
                    'service_description' => $service['name'],
                    
                    // Foreign keys
                    'inventory_item_id' => $inventoryItem?->id,
                    'service_catalog_id' => $serviceCatalog?->id,
                    
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
                    
                    // Audit trail
                    'audit_trail_hash' => hash('sha256', json_encode([
                        'service_code' => $serviceCode,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'timestamp' => now()->toISOString(),
                    ])),
                    
                    'created_by_staff_id' => $staffId,
                    'metadata' => json_encode([
                        'service_key' => $chargeItem['service_key'],
                        'category' => $service['category'],
                        'source_type' => $inventoryItem ? 'inventory' : ($serviceCatalog ? 'service_catalog' : 'unknown'),
                    ]),
                ];

                $lineItem = InvoiceLineItem::create($lineItemData);
                $lineItems[] = $lineItem;
            }

            return $lineItems;
        }

    /**
     * Deduct inventory stock for billed inventory items with proper locking
     *
     * @param array $chargeItems Charge items from billing payload
     * @param int $staffId Staff performing the action
     * @return void
     * @throws \Exception
     */
    protected function deductInventoryStock(array $chargeItems, int $staffId): void
    {
        foreach ($chargeItems as $chargeItem) {
            try {
                $service = $chargeItem['service'];
                $quantity = (int) $chargeItem['quantity'];

                if ($quantity <= 0) {
                    continue;
                }

                // Only process items that have a service code (inventory items will match)
                $inventoryItem = InventoryItem::query()
                    ->where('item_code', $service['code'])
                    ->where('status', 'active')
                    ->lockForUpdate() // Prevent race conditions
                    ->first();

                if (!$inventoryItem) {
                    // It's a service (no inventory record) — skip silently
                    continue;
                }

                // Double-check stock availability (race condition safety)
                if ($inventoryItem->package_quantity < $quantity) {
                    Log::error('Race condition detected: Insufficient stock during deduction', [
                        'item_code' => $service['code'],
                        'available' => $inventoryItem->package_quantity,
                        'requested' => $quantity,
                        'staff_id' => $staffId,
                    ]);

                    throw new \Exception(
                        "Insufficient stock for item '{$service['name']}' (Code: {$service['code']}). "
                        . "Available: {$inventoryItem->package_quantity}, Requested: {$quantity}. "
                        . "This may be due to concurrent transactions. Please try again."
                    );
                }

                // Calculate new quantity
                $previousQuantity = $inventoryItem->package_quantity;
                $unitsToDeduct = $quantity;
                $newPackageQuantity = max(0, $previousQuantity - $unitsToDeduct);

                $inventoryItem->package_quantity = $newPackageQuantity;
                $inventoryItem->updated_by_staff_id = $staffId;

                // Append deduction to metadata audit trail
                $metadata = is_array($inventoryItem->metadata) 
                    ? $inventoryItem->metadata 
                    : (json_decode($inventoryItem->metadata ?? '{}', true) ?? []);

                if (!isset($metadata['stock_deductions'])) {
                    $metadata['stock_deductions'] = [];
                }

                $metadata['stock_deductions'][] = [
                    'deducted_at' => now()->toIso8601String(),
                    'deducted_by_staff_id' => $staffId,
                    'units_deducted' => $unitsToDeduct,
                    'previous_quantity' => $previousQuantity,
                    'new_quantity' => $newPackageQuantity,
                    'service_code' => $service['code'],
                    'service_name' => $service['name'] ?? 'Unknown',
                    'reason' => 'billing_finalization',
                ];

                // Track last deduction for quick reference
                $metadata['last_stock_deduction'] = [
                    'deducted_at' => now()->toIso8601String(),
                    'deducted_by_staff_id' => $staffId,
                    'units_deducted' => $unitsToDeduct,
                    'new_quantity' => $newPackageQuantity,
                ];

                $inventoryItem->metadata = $metadata;
                $inventoryItem->save();

                Log::info('Inventory stock deducted successfully', [
                    'item_code' => $service['code'],
                    'item_name' => $service['name'] ?? 'Unknown',
                    'units_deducted' => $unitsToDeduct,
                    'previous_quantity' => $previousQuantity,
                    'new_quantity' => $newPackageQuantity,
                    'status' => $inventoryItem->status,
                    'staff_id' => $staffId,
                ]);

            } catch (\Exception $e) {
                Log::error('Failed to deduct inventory stock', [
                    'item_code' => $service['code'] ?? 'unknown',
                    'item_name' => $service['name'] ?? 'unknown',
                    'error_message' => $e->getMessage(),
                    'staff_id' => $staffId,
                ]);

                throw $e; // Re-throw to trigger transaction rollback
            }
        }
    }

    /**
     * Update visit with billing information
     * 
     * FIXED: Use paymentSplit for accurate payment calculations
     *
     * @param Visit $visit
     * @param array $data
     * @param BillingCycle $billingCycle
     * @param int $staffId
     * @param array $paymentSplit
     * @return void
     */
    protected function updateVisitBillingStatus(
        Visit $visit, 
        array $data, 
        BillingCycle $billingCycle, 
        int $staffId,
        array $paymentSplit
    ): void {
        // Calculate balance using validated payment amounts
        $grandTotal = $data['billing_data']['grandTotal'];
        $totalPaid = $paymentSplit['total_paid'] ?? 
            ($paymentSplit['insurance_payment'] + $paymentSplit['patient_payment']);
        $balance = max(0, $grandTotal - $totalPaid);
        $isFullyPaid = abs($balance) < 0.01; // Account for floating point

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

        // Update payment status based on actual balance
        if ($isFullyPaid) {
            $visit->payment_status = 'paid_in_full'; 
        } elseif ($totalPaid > 0) {
            $visit->payment_status = 'partially_paid'; 
        } else {
            $visit->payment_status = 'pending'; 
        }

        // Update financial snapshot with accurate values
        $visit->estimated_total_charges = $grandTotal;
        $visit->patient_estimated_responsibility = $totalPaid;

        // Update audit trail
        $visit->updated_by_staff_id = $staffId;

        // Add billing metadata
        $metadata = is_array($visit->metadata) ? $visit->metadata : (json_decode($visit->metadata ?? '{}', true) ?? []);
        
        // Initialize billing array if not exists
        if (!isset($metadata['billing'])) {
            $metadata['billing'] = [];
        }
        
        // Add this billing transaction with accurate payment data
        $metadata['billing'][] = [
            'billing_cycle_id' => $billingCycle->id,
            'billing_cycle_uuid' => $billingCycle->billing_cycle_uuid,
            'completed_at' => now()->toIso8601String(),
            'completed_by_staff_id' => $staffId,
            'receipt_number' => "REC-{$billingCycle->id}",
            'grand_total' => $grandTotal,
            'total_paid' => $totalPaid,
            'balance' => $balance,
            'is_fully_paid' => $isFullyPaid,
            'payment_methods' => $data['payment_methods'],
            'payment_split' => [ // Store the validated split
                'insurance' => $paymentSplit['insurance_payment'],
                'patient' => $paymentSplit['patient_payment'],
            ],
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
            'grand_total' => $grandTotal,
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
            'grand_total' => $grandTotal,
            'total_paid' => $totalPaid,
            'balance' => $balance,
            'is_fully_paid' => $isFullyPaid,
            'billing_cycle_id' => $billingCycle->id,
        ]);
    }

    /**
     * Calculate pro-rated discount for a line item
     *
     * @param float $lineTotal Line item total
     * @param float $subtotal Overall subtotal
     * @param float $totalDiscount Total discount amount
     * @return float Pro-rated discount for this line item
     */
    protected function calculateLineItemDiscount(float $lineTotal, float $subtotal, float $totalDiscount): float
    {
        if ($subtotal <= 0) {
            return 0;
        }

        return ($lineTotal / $subtotal) * $totalDiscount;
    }
}