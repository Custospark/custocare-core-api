<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalkInSessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'facility_id' => $this['facility_id'] ?? null,
            'walkin' => [
                'facility_id' => $this['walkin']['facility_id'] ?? null,
                'system_user_id' => $this['walkin']['system_user_id'] ?? null,
                'patient_id' => $this['walkin']['patient_id'] ?? null,
                'patient_uuid' => $this['walkin']['patient_uuid'] ?? null,
                'display_name' => $this['walkin']['display_name'] ?? null,
                'mode' => $this['walkin']['mode'] ?? null,
            ],
            'visit' => [
                'id' => $this['visit']->id ?? null,
                'visit_uuid' => $this['visit']->visit_uuid ?? null,
                'facility_id' => $this['visit']->facility_id ?? null,
                'patient_id' => $this['visit']->patient_id ?? null,
                'visit_type' => $this['visit']->visit_type ?? null,
                'acuity_score' => $this['visit']->acuity_score ?? null,
                'chief_complaints' => $this['visit']->chief_complaints ? json_decode($this['visit']->chief_complaints) : [],
                'arrived_at' => $this['visit']->arrived_at ?? null,
                'current_phase' => $this['visit']->current_phase ?? null,
                'is_walk_in' => $this['visit']->is_walk_in ?? null,
                'status' => $this['visit']->status ?? null,
                'created_at' => $this['visit']->created_at ?? null,
                'updated_at' => $this['visit']->updated_at ?? null,
            ],
            'billing' => [
                'id' => $this['billing']->id ?? null,
                'billing_cycle_uuid' => $this['billing']->billing_cycle_uuid ?? null,
                'facility_id' => $this['billing']->facility_id ?? null,
                'visit_id' => $this['billing']->visit_id ?? null,
                'patient_id' => $this['billing']->patient_id ?? null,
                'cycle_type' => $this['billing']->cycle_type ?? null,
                'period_start' => $this['billing']->period_start ?? null,
                'billing_status' => $this['billing']->billing_status ?? null,
                'total_amount_charged' => $this['billing']->total_amount_charged ?? 0,
                'total_adjustments' => $this['billing']->total_adjustments ?? 0,
                'net_amount' => $this['billing']->net_amount ?? 0,
                'patient_responsibility_amount' => $this['billing']->patient_responsibility_amount ?? 0,
                'patient_payment_received' => $this['billing']->patient_payment_received ?? 0,
                'created_by_staff_id' => $this['billing']->created_by_staff_id ?? null,
                'updated_by_staff_id' => $this['billing']->updated_by_staff_id ?? null,
                'created_at' => $this['billing']->created_at ?? null,
                'updated_at' => $this['billing']->updated_at ?? null,
            ],
            'ui_next' => $this['ui_next'] ?? [],
        ];
    }
}