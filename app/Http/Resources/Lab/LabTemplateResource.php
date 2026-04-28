<?php

namespace App\Http\Resources\Lab;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabTemplateResource extends JsonResource
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
            'template_uuid' => $this->template_uuid,
            'name' => $this->name,
            'description' => $this->description,
            'facility_id' => $this->facility_id,
            'is_shared' => $this->is_shared,
            'structure_type' => $this->structure_type,
            'is_active' => $this->is_active,
            'status' => $this->is_active ? 'active' : 'inactive',
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            
            // Relationships
            'facility' => $this->whenLoaded('facility', function () {
                return [
                    'id' => $this->facility->id,
                    'facility_uuid' => $this->facility->facility_uuid,
                    'facility_name' => $this->facility->facility_name,
                ];
            }),
            
            'tests' => LabTestResource::collection($this->whenLoaded('tests')),
            'fields' => LabTemplateFieldResource::collection($this->whenLoaded('fields')),
            
            // Statistics
            'tests_count' => $this->whenCounted('tests'),
            'fields_count' => $this->whenCounted('fields'),
            
            // Helper attributes
            'is_standard' => $this->isStandard(),
            'is_simple' => $this->isSimple(),
            'is_panel' => $this->isPanel(),
            'structure_type_label' => ucfirst($this->structure_type),
            
            // URLs
            'urls' => [
                'self' => route('api.lab-templates.show', $this->template_uuid),
                'tests' => route('api.lab-templates.tests.index', $this->template_uuid),
                'fields' => route('api.lab-templates.fields.index', $this->template_uuid),
            ],
        ];
    }
}