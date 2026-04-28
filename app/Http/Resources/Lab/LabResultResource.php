<?php

namespace App\Http\Resources\Lab;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabResultResource extends JsonResource
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
            'result_uuid' => $this->result_uuid,
            'lab_request_item_id' => $this->lab_request_item_id,
            'template_field_id' => $this->template_field_id,
            'value' => $this->value,
            'unit' => $this->unit,
            'numeric_value' => $this->numeric_value,
            'flag' => $this->flag,
            'reference_min' => $this->reference_min,
            'reference_max' => $this->reference_max,
            'interpretation' => $this->interpretation,
            'comments' => $this->comments,
            'recorded_by_staff_id' => $this->recorded_by_staff_id,
            'verified_by_staff_id' => $this->verified_by_staff_id,
            'verified_at' => $this->verified_at?->toISOString(),
            'recorded_at' => $this->recorded_at?->toISOString(),
            'updated_at_value' => $this->updated_at_value?->toISOString(),
            'is_abnormal_flagged' => $this->is_abnormal_flagged,
            'is_critical_alert_sent' => $this->is_critical_alert_sent,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            
            // Relationships
            'lab_request_item' => new LabRequestItemResource($this->whenLoaded('labRequestItem')),
            'template_field' => new LabTemplateFieldResource($this->whenLoaded('templateField')),
            'recorded_by' => $this->whenLoaded('recordedBy', function () {
                return [
                    'id' => $this->recordedBy->id,
                    'staff_uuid' => $this->recordedBy->staff_uuid,
                    'name' => $this->recordedBy->user->full_name ?? null,
                ];
            }),
            'verified_by' => $this->whenLoaded('verifiedBy', function () {
                return [
                    'id' => $this->verifiedBy->id,
                    'staff_uuid' => $this->verifiedBy->staff_uuid,
                    'name' => $this->verifiedBy->user->full_name ?? null,
                ];
            }),
            
            // Helper attributes
            'formatted_value' => $this->formatted_value,
            'reference_range' => $this->reference_range,
            'flag_label' => $this->flag_label,
            'flag_badge_color' => $this->flag_badge_color,
            'flag_icon' => $this->flag_icon,
            'is_pending' => $this->isPending(),
            'is_normal' => $this->isNormal(),
            'is_low' => $this->isLow(),
            'is_high' => $this->isHigh(),
            'is_critical' => $this->isCritical(),
            'is_abnormal' => $this->isAbnormal(),
            'is_verified' => $this->isVerified(),
            'needs_verification' => $this->needsVerification(),
            'age_in_hours' => $this->age_in_hours,
            'verification_delay_hours' => $this->verification_delay_hours,
            'is_within_reference_range' => $this->isValueInReferenceRange($this->value),
            
            // URLs
            // 'urls' => [
            //     'self' => route('api.lab-results.show', $this->result_uuid),
            //     'item' => route('api.lab-request-items.show', $this->labRequestItem->item_uuid ?? ''),
            //     'field' => route('api.lab-template-fields.show', $this->templateField->field_uuid ?? ''),
            // ],
        ];
    }
}