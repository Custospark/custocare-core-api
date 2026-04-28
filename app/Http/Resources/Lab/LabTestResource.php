<?php

namespace App\Http\Resources\Lab;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabTestResource extends JsonResource
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
            'test_uuid' => $this->test_uuid,
            'name' => $this->name,
            'code' => $this->code,
            'template_id' => $this->template_id,
            'facility_id' => $this->facility_id,
            'is_shared' => $this->is_shared,
            'category' => $this->category,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'requires_fasting' => $this->requires_fasting,
            'turnaround_time_hours' => $this->turnaround_time_hours,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            
            // Relationships
            'template' => new LabTemplateResource($this->whenLoaded('template')),
            'facility' => $this->whenLoaded('facility', function () {
                return [
                    'id' => $this->facility->id,
                    'facility_uuid' => $this->facility->facility_uuid,
                    'facility_name' => $this->facility->facility_name,
                ];
            }),
            
            // Statistics
            'request_count' => $this->whenCounted('requestItems'),
            
            // Helper attributes
            'status' => $this->is_active ? 'active' : 'inactive',
            'formatted_turnaround_time' => $this->formatted_turnaround_time,
            'fasting_required' => $this->requires_fasting,
            'fasting_instruction' => $this->requires_fasting ? 'Fasting required for this test' : 'No fasting required',
            
            // URLs
            'urls' => [
                'self' => route('api.lab-tests.show', $this->test_uuid),
                'template' => route('api.lab-templates.show', $this->template->template_uuid ?? ''),
            ],
        ];
    }
}