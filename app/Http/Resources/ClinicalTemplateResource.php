<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicalTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'category' => $this->category,
            'default_diagnosis' => $this->default_diagnosis,
            'default_notes' => $this->default_notes,
            'patient_instructions' => $this->patient_instructions,
            'default_medications' => $this->getFormattedMedications(),
            'usage_count' => $this->usage_count,
            'is_active' => $this->is_active,
            'visibility' => $this->visibility,
            'created_by' => $this->creator?->name,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}