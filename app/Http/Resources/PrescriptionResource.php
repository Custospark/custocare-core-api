<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class PrescriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'prescription_uuid' => $this->prescription_uuid,
            'facility' => new FacilityResource($this->whenLoaded('facility')),
            'patient' => new PatientResource($this->whenLoaded('patient')),
            'visit' => new VisitResource($this->whenLoaded('visit')),
            'prescribing_provider' => new StaffResource($this->whenLoaded('prescribingProvider')),
            'inventory_item' => new InventoryItemResource($this->whenLoaded('inventoryItem')),
            'created_by' => new StaffResource($this->whenLoaded('createdBy')),
            'discontinued_by' => new StaffResource($this->whenLoaded('discontinuedBy')),
            
            // Medication details
            'medication_name' => $this->medication_name,
            'generic_name' => $this->generic_name,
            'ndc_code' => $this->ndc_code,
            'controlled_substance_schedule' => $this->controlled_substance_schedule,
            'is_controlled_substance' => $this->isControlledSubstance(),
            
            // Dosing instructions
            'dosage_strength' => $this->dosage_strength,
            'dosage_form' => $this->dosage_form,
            'route' => $this->route,
            'sig_instructions' => $this->sig_instructions,
            'pharmacist_notes' => $this->pharmacist_notes,
            
            // Quantity & refills
            'quantity_prescribed' => (float) $this->quantity_prescribed,
            'quantity_unit' => $this->quantity_unit,
            'refills_allowed' => $this->refills_allowed,
            'refills_remaining' => $this->refills_remaining,
            'days_supply' => $this->days_supply,
            'is_refillable' => $this->isRefillable(),
            
            // Clinical context
            'diagnosis_codes' => $this->diagnosis_codes,
            'clinical_indication' => $this->clinical_indication,
            'drug_allergy_check_results' => $this->when(
                $request->user()->hasRole(['admin', 'provider', 'pharmacist']),
                $this->drug_allergy_check_results
            ),
            'drug_interaction_check_results' => $this->when(
                $request->user()->hasRole(['admin', 'provider', 'pharmacist']),
                $this->drug_interaction_check_results
            ),
            
            // Validity period
            'prescribed_at' => $this->prescribed_at?->toIso8601String(),
            'valid_from' => $this->valid_from?->format('Y-m-d'),
            'valid_to' => $this->valid_to?->format('Y-m-d'),
            'do_not_fill_before' => $this->do_not_fill_before?->format('Y-m-d'),
            'is_active' => $this->isActive(),
            'days_remaining' => $this->valid_to ? Carbon::parse($this->valid_to)->diffInDays(now()) : null,
            
            // Authorization
            'requires_prior_authorization' => (bool) $this->requires_prior_authorization,
            'prior_authorization_number' => $this->prior_authorization_number,
            'prior_auth_status' => $this->prior_auth_status,
            
            // Electronic prescribing
            'is_electronic_prescription' => (bool) $this->is_electronic_prescription,
            'erx_message_id' => $this->erx_message_id,
            'transmitted_at' => $this->transmitted_at?->toIso8601String(),
            'transmit_to_pharmacy' => $this->transmit_to_pharmacy,
            'pharmacy_ncpdp_id' => $this->pharmacy_ncpdp_id,
            'needs_transmission' => $this->is_electronic_prescription && !$this->transmitted_at,
            
            // Dispense tracking
            'dispense_status' => $this->dispense_status,
            'dispense_status_label' => $this->getDispenseStatusLabel($this->dispense_status),
            
            // Safety & monitoring
            'is_high_risk_medication' => (bool) $this->is_high_risk_medication,
            'safety_monitoring_required' => $this->when(
                $request->user()->hasRole(['admin', 'provider', 'pharmacist']),
                $this->safety_monitoring_required
            ),
            'special_instructions' => $this->special_instructions,
            
            // Status
            'status' => $this->status,
            'status_label' => $this->getStatusLabel($this->status),
            'status_reason' => $this->status_reason,
            'discontinued_at' => $this->discontinued_at?->toIso8601String(),
            
            // Audit
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->when(
                $request->user()->hasRole('admin'),
                $this->deleted_at?->toIso8601String()
            ),
            'metadata' => $this->when(
                $request->user()->hasRole(['admin', 'provider']),
                $this->metadata
            ),
            
            // Links
            'links' => [
                'self' => route('prescriptions.show', $this->prescription_uuid),
                'patient' => $this->patient_id ? route('patients.show', $this->patient_id) : null,
                'provider' => $this->prescribing_provider_staff_id ? route('staff.show', $this->prescribing_provider_staff_id) : null,
            ],
            
            // Actions (based on user permissions)
            'actions' => $this->getAvailableActions($request->user()),
        ];
    }
    
    /**
     * Get dispense status label
     *
     * @param string $status
     * @return string
     */
    private function getDispenseStatusLabel(string $status): string
    {
        $labels = [
            'pending' => 'Pending',
            'transmitted' => 'Transmitted to Pharmacy',
            'received_by_pharmacy' => 'Received by Pharmacy',
            'in_progress' => 'In Progress',
            'ready_for_pickup' => 'Ready for Pickup',
            'dispensed' => 'Dispensed',
            'not_picked_up' => 'Not Picked Up',
            'cancelled' => 'Cancelled',
            'discontinued' => 'Discontinued',
        ];
        
        return $labels[$status] ?? $status;
    }
    
    /**
     * Get status label
     *
     * @param string $status
     * @return string
     */
    private function getStatusLabel(string $status): string
    {
        $labels = [
            'active' => 'Active',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'discontinued' => 'Discontinued',
            'expired' => 'Expired',
            'on_hold' => 'On Hold',
        ];
        
        return $labels[$status] ?? $status;
    }
    
    /**
     * Get available actions for the user
     *
     * @param mixed $user
     * @return array
     */
    private function getAvailableActions($user): array
    {
        $actions = [];
        
        if ($user->can('update', $this->resource)) {
            $actions[] = 'update';
        }
        
        if ($user->can('delete', $this->resource) && $this->resource->isActive()) {
            $actions[] = 'delete';
        }
        
        if ($user->can('refill', $this->resource) && $this->resource->isRefillable()) {
            $actions[] = 'refill';
        }
        
        if ($user->can('discontinue', $this->resource) && $this->resource->isActive()) {
            $actions[] = 'discontinue';
        }
        
        if ($user->can('transmit', $this->resource) && 
            $this->resource->is_electronic_prescription && 
            !$this->resource->transmitted_at) {
            $actions[] = 'transmit';
        }
        
        if ($user->can('updateDispenseStatus', $this->resource)) {
            $actions[] = 'update_dispense_status';
        }
        
        return $actions;
    }
    
    /**
     * Customize the response for a given request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\JsonResponse  $response
     * @return void
     */
    public function withResponse($request, $response)
    {
        $response->header('X-Prescription-API-Version', '1.0');
        
        // Add rate limit headers if available
        if ($request->has('rate_limit_remaining')) {
            $response->header('X-RateLimit-Remaining', $request->rate_limit_remaining);
            $response->header('X-RateLimit-Limit', $request->rate_limit_limit);
        }
    }
}