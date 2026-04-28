<?php

namespace App\Http\Resources\Lab;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabTemplateFieldResource extends JsonResource
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
            'field_uuid' => $this->field_uuid,
            'template_id' => $this->template_id,
            'name' => $this->name,
            'code' => $this->code,
            'data_type' => $this->data_type,
            'unit' => $this->unit,
            'reference_min' => $this->reference_min,
            'reference_max' => $this->reference_max,
            'display_order' => $this->display_order,
            'is_required' => $this->is_required,
            'is_active' => $this->is_active,
            'is_critical' => $this->is_critical,
            'clinical_notes' => $this->clinical_notes,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            
            // Relationships
            'template' => new LabTemplateResource($this->whenLoaded('template')),
            
            // Helper attributes
            'data_type_label' => ucfirst($this->data_type),
            'status' => $this->is_active ? 'active' : 'inactive',
            'is_number_type' => $this->isNumberType(),
            'is_text_type' => $this->isTextType(),
            'is_boolean_type' => $this->isBooleanType(),
            'is_select_type' => $this->isSelectType(),
            'formatted_reference_range' => $this->formatted_reference_range,
            'has_reference_range' => $this->reference_min !== null || $this->reference_max !== null,
            
            // URLs
            'urls' => [
                'self' => route('api.lab-template-fields.show', $this->field_uuid),
                'template' => route('api.lab-templates.show', $this->template->template_uuid ?? ''),
            ],
        ];
    }
}