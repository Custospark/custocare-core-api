<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrescriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'prescription_number' => $this->prescription_number,
            'prescription_date' => $this->prescription_date->format('Y-m-d'),
            'valid_until' => $this->valid_until?->format('Y-m-d'),
            'status' => $this->status,
            'prescription_type' => $this->prescription_type,
            'priority' => $this->priority,
            'diagnosis' => $this->diagnosis,
            'clinical_notes' => $this->clinical_notes,
            'special_instructions' => $this->special_instructions,
            'patient_education_notes' => $this->patient_education_notes,
            'follow_up_instructions' => $this->follow_up_instructions,
            'follow_up_date' => $this->follow_up_date?->format('Y-m-d'),
            
            // Relationships
            'patient' => [
                'id' => $this->patient->id,
                'name' => $this->patient->name ?? 'Unknown',
            ],
          'prescribed_by' => [
            'id' => $this->prescribedBy->id,
            'name' => $this->prescribedBy ? 'Dr. ' . ($this->prescribedBy->first_name . ' ' . $this->prescribedBy->last_name) : 'Unknown',
            'type' => $this->prescriber_type,
        ],
            'clinical_template' => $this->clinical_template_id ? [
                'id' => $this->clinicalTemplate->id,
                'name' => $this->clinicalTemplate->name,
            ] : null,
            
            // Items
            'items' => PrescriptionItemResource::collection($this->whenLoaded('items')),
            
            // Statistics
            'total_items' => $this->items->count(),
            'total_quantity' => $this->items->sum('total_quantity'),
            
            // Timestamps
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            
            // Dispensing info
            'dispensing_info' => [
                'dispensed_at' => $this->dispensed_at?->format('Y-m-d H:i:s'),
                'dispensed_by_name' => $this->dispensed_by_name,
                'dispensed_pharmacy' => $this->dispensed_pharmacy,
                'dispensing_location' => $this->dispensing_location,
            ],
            
            // Cancellation info
            'cancellation_info' => $this->cancelled_at ? [
                'cancelled_at' => $this->cancelled_at->format('Y-m-d H:i:s'),
                'reason' => $this->cancellation_reason,
                'notes' => $this->cancellation_notes,
            ] : null,
        ];
    }
}