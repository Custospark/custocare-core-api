<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for InventoryLedger model.
 * Transforms the model into a consistent JSON response structure.
 */
class InventoryLedgerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_uuid' => $this->transaction_uuid,
            
            // Facility information
            'facility' => $this->whenLoaded('facility', function () {
                return new FacilityResource($this->facility);
            }),
            'facility_id' => $this->facility_id,
            
            // Inventory item information
            'inventory_item' => $this->whenLoaded('inventoryItem', function () {
                return new InventoryItemResource($this->inventoryItem);
            }),
            'inventory_item_id' => $this->inventory_item_id,
            
            // Transaction details
            'transaction_type' => $this->transaction_type,
            'transaction_type_label' => $this->getTransactionTypeLabel(),
            'quantity_change' => (float) $this->quantity_change,
            'balance_after_transaction' => (float) $this->balance_after_transaction,
            'unit_of_measure' => $this->unit_of_measure,
            
            // Lot & serial tracking
            'lot_number' => $this->lot_number,
            'serial_number' => $this->serial_number,
            'expiry_date' => $this->expiry_date?->toDateString(),
            'manufacture_date' => $this->manufacture_date?->toDateString(),
            'days_to_expiry' => $this->expiry_date ? $this->expiry_date->diffInDays(now()) : null,
            'is_expired' => $this->expiry_date ? $this->expiry_date->isPast() : false,
            
            // Financial tracking
            'unit_cost_at_transaction' => $this->unit_cost_at_transaction ? (float) $this->unit_cost_at_transaction : null,
            'total_cost' => $this->total_cost ? (float) $this->total_cost : null,
            'currency' => config('app.currency', 'USD'),
            
            // Context & linkage
            'reference_visit' => $this->whenLoaded('referenceVisit', function () {
                return new VisitResource($this->referenceVisit);
            }),
            'reference_visit_id' => $this->reference_visit_id,
            'reference_prescription_id' => $this->reference_prescription_id,
            'reference_purchase_order_id' => $this->reference_purchase_order_id,
            'transfer_from_facility_id' => $this->transfer_from_facility_id,
            'transfer_to_facility_id' => $this->transfer_to_facility_id,
            
            // Reason & documentation
            'transaction_cause' => $this->transaction_cause,
            'transaction_cause_label' => $this->getTransactionCauseLabel(),
            'transaction_notes' => $this->transaction_notes,
            'reference_document_number' => $this->reference_document_number,
            
            // Approval & verification
            'performed_by_staff' => $this->whenLoaded('performedByStaff', function () {
                return new StaffResource($this->performedByStaff);
            }),
            'performed_by_staff_id' => $this->performed_by_staff_id,
            'verified_by_staff' => $this->whenLoaded('verifiedByStaff', function () {
                return new StaffResource($this->verifiedByStaff);
            }),
            'verified_by_staff_id' => $this->verified_by_staff_id,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'is_verified' => !is_null($this->verified_at),
            
            // Storage location
            'storage_location' => $this->storage_location,
            'department_id' => $this->department_id,
            
            // Audit & timestamps
            'transaction_timestamp' => $this->transaction_timestamp->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            
            // Integrity
            'transaction_hash' => $this->transaction_hash,
            'metadata' => $this->metadata,
            
            // Computed fields
            'is_incoming' => $this->quantity_change > 0,
            'is_outgoing' => $this->quantity_change < 0,
            'absolute_quantity' => abs((float) $this->quantity_change),
            
            // Links
            'links' => [
                'self' => route('inventory-ledger.show', $this->id),
                'verify' => route('inventory-ledger.verify', $this->id),
            ],
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'success' => true,
            'meta' => [
                'transaction_types' => $this->getTransactionTypes(),
                'transaction_causes' => $this->getTransactionCauses(),
                'version' => '1.0',
                'timestamp' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Get human-readable label for transaction type.
     *
     * @return string
     */
    private function getTransactionTypeLabel(): string
    {
        $labels = [
            'purchase' => 'Purchase',
            'adjustment_increase' => 'Adjustment Increase',
            'adjustment_decrease' => 'Adjustment Decrease',
            'consumption_visit' => 'Patient Visit Consumption',
            'consumption_waste' => 'Waste',
            'return_to_supplier' => 'Return to Supplier',
            'transfer_in' => 'Transfer In',
            'transfer_out' => 'Transfer Out',
            'cycle_count' => 'Cycle Count',
            'expired' => 'Expired',
            'damaged' => 'Damaged',
            'stolen' => 'Stolen',
            'recalled' => 'Recalled',
        ];
        
        return $labels[$this->transaction_type] ?? $this->transaction_type;
    }

    /**
     * Get human-readable label for transaction cause.
     *
     * @return string
     */
    private function getTransactionCauseLabel(): string
    {
        $labels = [
            'manual_entry' => 'Manual Entry',
            'system_automated' => 'System Automated',
            'physical_count' => 'Physical Count',
            'reconciliation' => 'Reconciliation',
            'patient_use' => 'Patient Use',
            'procedural_use' => 'Procedural Use',
            'administrative' => 'Administrative',
        ];
        
        return $labels[$this->transaction_cause] ?? $this->transaction_cause;
    }

    /**
     * Get all transaction types with labels.
     *
     * @return array
     */
    private function getTransactionTypes(): array
    {
        return [
            ['value' => 'purchase', 'label' => 'Purchase', 'category' => 'incoming'],
            ['value' => 'adjustment_increase', 'label' => 'Adjustment Increase', 'category' => 'incoming'],
            ['value' => 'adjustment_decrease', 'label' => 'Adjustment Decrease', 'category' => 'outgoing'],
            ['value' => 'consumption_visit', 'label' => 'Patient Visit Consumption', 'category' => 'outgoing'],
            ['value' => 'consumption_waste', 'label' => 'Waste', 'category' => 'outgoing'],
            ['value' => 'return_to_supplier', 'label' => 'Return to Supplier', 'category' => 'outgoing'],
            ['value' => 'transfer_in', 'label' => 'Transfer In', 'category' => 'incoming'],
            ['value' => 'transfer_out', 'label' => 'Transfer Out', 'category' => 'outgoing'],
            ['value' => 'cycle_count', 'label' => 'Cycle Count', 'category' => 'adjustment'],
            ['value' => 'expired', 'label' => 'Expired', 'category' => 'outgoing'],
            ['value' => 'damaged', 'label' => 'Damaged', 'category' => 'outgoing'],
            ['value' => 'stolen', 'label' => 'Stolen', 'category' => 'outgoing'],
            ['value' => 'recalled', 'label' => 'Recalled', 'category' => 'outgoing'],
        ];
    }

    /**
     * Get all transaction causes with labels.
     *
     * @return array
     */
    private function getTransactionCauses(): array
    {
        return [
            ['value' => 'manual_entry', 'label' => 'Manual Entry'],
            ['value' => 'system_automated', 'label' => 'System Automated'],
            ['value' => 'physical_count', 'label' => 'Physical Count'],
            ['value' => 'reconciliation', 'label' => 'Reconciliation'],
            ['value' => 'patient_use', 'label' => 'Patient Use'],
            ['value' => 'procedural_use', 'label' => 'Procedural Use'],
            ['value' => 'administrative', 'label' => 'Administrative'],
        ];
    }
}