<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingCycleResource extends JsonResource
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
            'billing_cycle_uuid' => $this->billing_cycle_uuid,
            
            'facility_id' => $this->facility_id,
            'visit_id' => $this->visit_id,
            'patient_id' => $this->patient_id,
            
            // Facility relationship (loaded when needed)
            'facility' => $this->whenLoaded('facility', function () {
                return new FacilityResource($this->facility);
            }),
            
            // Visit relationship (loaded when needed)
            'visit' => $this->whenLoaded('visit', function () {
                return new VisitResource($this->visit);
            }),
            
            // Patient relationship (loaded when needed)
            'patient' => $this->whenLoaded('patient', function () {
                return new PatientResource($this->patient);
            }),
            
            'cycle_type' => $this->cycle_type,
            'cycle_type_label' => $this->getCycleTypeLabel(),
            
            'period_start' => $this->period_start?->toIso8601String(),
            'period_end' => $this->period_end?->toIso8601String(),
            'days_in_cycle' => $this->days_in_cycle,
            
            // Financial summary
            'total_amount_charged' => (float) $this->total_amount_charged,
            'total_adjustments' => (float) $this->total_adjustments,
            'net_amount' => (float) $this->net_amount,
            
            // Insurance processing
            'primary_insurance_claim_number' => $this->primary_insurance_claim_number,
            'insurance_covered_amount' => (float) $this->insurance_covered_amount,
            'insurance_adjustment_amount' => (float) $this->insurance_adjustment_amount,
            'insurance_payment_received' => (float) $this->insurance_payment_received,
            'insurance_claim_submitted_at' => $this->insurance_claim_submitted_at?->toIso8601String(),
            'insurance_payment_received_at' => $this->insurance_payment_received_at?->toIso8601String(),
            
            // Patient responsibility
            'patient_responsibility_amount' => (float) $this->patient_responsibility_amount,
            'patient_copay_amount' => (float) $this->patient_copay_amount,
            'patient_deductible_amount' => (float) $this->patient_deductible_amount,
            'patient_coinsurance_amount' => (float) $this->patient_coinsurance_amount,
            'patient_payment_received' => (float) $this->patient_payment_received,
            
            // Patient payment summary
            'patient_outstanding_amount' => max(0, 
                (float) $this->patient_responsibility_amount - (float) $this->patient_payment_received
            ),
            
            // Discounts & adjustments
            'discount_applied' => (float) $this->discount_applied,
            'discount_reason' => $this->discount_reason,
            'contractual_adjustment' => (float) $this->contractual_adjustment,
            'charity_care_adjustment' => (float) $this->charity_care_adjustment,
            'bad_debt_adjustment' => (float) $this->bad_debt_adjustment,
            
            // Tax & fees
            'tax_details' => $this->tax_details,
            'total_tax_amount' => (float) $this->total_tax_amount,
            
            // Billing status
            'billing_status' => $this->billing_status,
            'billing_status_label' => $this->getBillingStatusLabel(),
            'billed_at' => $this->billed_at?->toIso8601String(),
            'payment_due_date' => $this->payment_due_date?->toIso8601String(),
            'days_outstanding' => $this->days_outstanding,
            
            // Collections & follow-up
            'statement_count' => $this->statement_count,
            'last_statement_sent_at' => $this->last_statement_sent_at?->toIso8601String(),
            'sent_to_collections_at' => $this->sent_to_collections_at?->toIso8601String(),
            'collections_agency' => $this->collections_agency,
            
            // Dispute management
            'is_disputed' => (bool) $this->is_disputed,
            'dispute_reason' => $this->dispute_reason,
            'dispute_opened_at' => $this->dispute_opened_at?->toIso8601String(),
            'dispute_resolved_at' => $this->dispute_resolved_at?->toIso8601String(),
            
            // Audit trail
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            
            'created_by_staff_id' => $this->created_by_staff_id,
            'updated_by_staff_id' => $this->updated_by_staff_id,
            
            // Created by relationship
            'created_by' => $this->whenLoaded('createdBy', function () {
                return new StaffResource($this->createdBy);
            }),
            
            // Updated by relationship
            'updated_by' => $this->whenLoaded('updatedBy', function () {
                return new StaffResource($this->updatedBy);
            }),
            
            'metadata' => $this->metadata,
            
            // Calculated fields
            'total_paid' => (float) $this->insurance_payment_received + (float) $this->patient_payment_received,
            'outstanding_amount' => max(0, 
                (float) $this->net_amount - 
                ((float) $this->insurance_payment_received + (float) $this->patient_payment_received)
            ),
            'is_overdue' => $this->isOverdue(),
            'is_fully_paid' => $this->isFullyPaid(),
        ];
    }

    /**
     * Get cycle type label for display
     *
     * @return string
     */
    private function getCycleTypeLabel(): string
    {
        return match ($this->cycle_type) {
            'visit_based' => 'Visit Based',
            'admission_discharge' => 'Admission/Discharge',
            'daily_inpatient' => 'Daily Inpatient',
            'weekly_inpatient' => 'Weekly Inpatient',
            'procedure_based' => 'Procedure Based',
            'bundled_payment' => 'Bundled Payment',
            'subscription' => 'Subscription',
            default => ucfirst(str_replace('_', ' ', $this->cycle_type)),
        };
    }

    /**
     * Get billing status label for display
     *
     * @return string
     */
    private function getBillingStatusLabel(): string
    {
        return match ($this->billing_status) {
            'pending_review' => 'Pending Review',
            'pending_submission' => 'Pending Submission',
            'submitted_to_insurance' => 'Submitted to Insurance',
            'partially_paid' => 'Partially Paid',
            'paid_in_full' => 'Paid in Full',
            'payment_plan' => 'Payment Plan',
            'written_off' => 'Written Off',
            'charity_care' => 'Charity Care',
            default => ucfirst(str_replace('_', ' ', $this->billing_status)),
        };
    }
}