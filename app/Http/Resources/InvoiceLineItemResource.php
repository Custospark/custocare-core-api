<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceLineItemResource extends JsonResource
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
            'line_item_uuid' => $this->line_item_uuid,
            'billing_cycle_id' => $this->billing_cycle_id,
            'visit_id' => $this->visit_id,
            'service_version_id' => $this->service_version_id,
            'service_version_snapshot' => $this->service_version_snapshot,
            'service_code' => $this->service_code,
            'service_description' => $this->service_description,
            'quantity' => (float) $this->quantity,
            'unit_of_measure' => $this->unit_of_measure,
            'unit_price_at_time' => (float) $this->unit_price_at_time,
            'line_total_amount' => (float) $this->line_total_amount,
            'applied_discount_percentage' => (float) $this->applied_discount_percentage,
            'discount_amount' => (float) $this->discount_amount,
            'adjustment_amount' => (float) $this->adjustment_amount,
            'adjustment_reason' => $this->adjustment_reason,
            'net_amount' => (float) $this->net_amount,
            'department_id' => $this->department_id,
            'staff_performed_id' => $this->staff_performed_id,
            'service_performed_at' => $this->service_performed_at?->toIso8601String(),
            'service_duration_minutes' => $this->service_duration_minutes,
            'diagnosis_codes' => $this->diagnosis_codes,
            'medical_necessity_notes' => $this->medical_necessity_notes,
            'modifier_codes' => $this->modifier_codes,
            'revenue_code' => $this->revenue_code,
            'procedure_code' => $this->procedure_code,
            'insurance_specific_codes' => $this->insurance_specific_codes,
            'preauthorization_number' => $this->preauthorization_number,
            'requires_review' => (bool) $this->requires_review,
            'coding_reviewed' => (bool) $this->coding_reviewed,
            'reviewed_by_staff_id' => $this->reviewed_by_staff_id,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'line_item_status' => $this->line_item_status,
            'audit_trail_hash' => $this->audit_trail_hash,
            'created_by_staff_id' => $this->created_by_staff_id,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            
            // Relationships (only include when loaded)
            'billing_cycle' => $this->whenLoaded('billingCycle', function () {
                return new BillingCycleResource($this->billingCycle);
            }),
            
            'service_version' => $this->whenLoaded('serviceVersion', function () {
                return new ServiceVersionResource($this->serviceVersion);
            }),
            
            'staff_performed' => $this->whenLoaded('staffPerformed', function () {
                return new StaffResource($this->staffPerformed);
            }),
            
            'reviewed_by' => $this->whenLoaded('reviewedBy', function () {
                return new StaffResource($this->reviewedBy);
            }),
            
            'created_by' => $this->whenLoaded('createdBy', function () {
                return new StaffResource($this->createdBy);
            }),
            
            // Computed properties
            'is_billable' => $this->when(
                isset($this->isBillable),
                $this->isBillable ?? $this->isBillable()
            ),
            
            'requires_coding_review' => $this->when(
                isset($this->requiresCodingReview),
                $this->requiresCodingReview ?? $this->requiresCodingReview()
            ),
            
            'audit_trail_valid' => $this->when(
                isset($this->isAuditTrailValid),
                $this->isAuditTrailValid ?? $this->isAuditTrailValid()
            ),
        ];
    }

    /**
     * Customize the outgoing response for the resource.
     */
    public function with(Request $request): array
    {
        return [
            'success' => true,
            'message' => 'Invoice line item retrieved successfully',
        ];
    }

    /**
     * Customize the pagination information for the resource.
     */
    public function paginationInformation($request, $paginated, $default): array
    {
        return [
            'success' => true,
            'message' => 'Invoice line items retrieved successfully',
            'data' => [
                'line_items' => $default['data'],
                'pagination' => [
                    'total' => $paginated['total'],
                    'per_page' => $paginated['per_page'],
                    'current_page' => $paginated['current_page'],
                    'last_page' => $paginated['last_page'],
                    'from' => $paginated['from'],
                    'to' => $paginated['to'],
                ]
            ]
        ];
    }
}