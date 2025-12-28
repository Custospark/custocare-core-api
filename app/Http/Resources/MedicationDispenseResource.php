<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicationDispenseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dispense_uuid' => $this->dispense_uuid,
            
            // Context information
            'facility_id' => $this->facility_id,
            'visit_id' => $this->visit_id,
            'prescription_id' => $this->prescription_id,
            'patient_id' => $this->patient_id,
            
            // Dispense details
            'prescription_details_snapshot' => $this->prescription_details_snapshot,
            'dispensed_inventory_ledger_id' => $this->dispensed_inventory_ledger_id,
            
            // Quantity information
            'quantity_dispensed' => (float) $this->quantity_dispensed,
            'quantity_unit' => $this->quantity_unit,
            'lot_number' => $this->lot_number,
            'expiry_date' => $this->expiry_date?->format('Y-m-d'),
            
            // Staff verification (4-eyes principle)
            'dispensed_by_staff_id' => $this->dispensed_by_staff_id,
            'dispensed_at' => $this->dispensed_at?->format('Y-m-d H:i:s'),
            'checked_by_staff_id' => $this->checked_by_staff_id,
            'checked_at' => $this->checked_at?->format('Y-m-d H:i:s'),
            'pharmacist_notes' => $this->pharmacist_notes,
            'is_verified' => $this->isVerified(),
            
            // Patient education & counseling
            'patient_counseling_provided' => (bool) $this->patient_counseling_provided,
            'medication_guide_provided' => (bool) $this->medication_guide_provided,
            'patient_education_topics' => $this->patient_education_topics,
            'patient_questions_addressed' => $this->patient_questions_addressed,
            
            // Instructions
            'dispensed_instructions' => $this->dispensed_instructions,
            'followup_instructions' => $this->followup_instructions,
            'warning_labels_applied' => $this->warning_labels_applied,
            
            // Safety checks
            'safety_checks_performed' => $this->safety_checks_performed,
            'all_safety_checks_passed' => (bool) $this->all_safety_checks_passed,
            'safety_check_overrides' => $this->safety_check_overrides,
            'override_justification' => $this->override_justification,
            
            // Delivery method
            'delivery_method' => $this->delivery_method,
            'picked_up_at' => $this->picked_up_at?->format('Y-m-d H:i:s'),
            'picked_up_by_name' => $this->picked_up_by_name,
            'pickup_id_verified' => $this->pickup_id_verified,
            'is_picked_up' => $this->isPickedUp(),
            
            // Billing
            'copay_collected' => $this->copay_collected ? (float) $this->copay_collected : null,
            'total_cost_to_patient' => $this->total_cost_to_patient ? (float) $this->total_cost_to_patient : null,
            'insurance_payment' => $this->insurance_payment ? (float) $this->insurance_payment : null,
            
            // Status
            'status' => $this->status,
            
            // Timestamps
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Metadata
            'metadata' => $this->metadata,
            
            // Relationships (loaded when requested)
            'patient' => new PatientResource($this->whenLoaded('patient')),
            'prescription' => new PrescriptionResource($this->whenLoaded('prescription')),
            'facility' => new FacilityResource($this->whenLoaded('facility')),
            'dispensing_staff' => new StaffResource($this->whenLoaded('dispensingStaff')),
            'checking_pharmacist' => new StaffResource($this->whenLoaded('checkingPharmacist')),
            'inventory_ledger' => new InventoryLedgerResource($this->whenLoaded('inventoryLedger')),
            'visit' => new VisitResource($this->whenLoaded('visit')),
            
            // Calculated fields
            'requires_verification' => !$this->isVerified(),
            'can_be_modified' => !$this->isVerified() && !$this->isPickedUp(),
            'safety_check_summary' => $this->getSafetyChecksPerformed()
        ];
    }
}