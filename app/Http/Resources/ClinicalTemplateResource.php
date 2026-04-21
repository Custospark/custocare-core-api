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
            'created_by' => $this->when($this->creator, function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'full_name' => $this->getCreatorFullName(),
                    'display_name' => $this->getCreatorDisplayName(),
                ];
            }),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get the creator's full name (first name + last name)
     */
    private function getCreatorFullName(): string
    {
        if (!$this->creator) {
            return '';
        }

        $firstName = $this->creator->first_name ?? '';
        $lastName = $this->creator->last_name ?? '';
        
        return trim('Dr. '.$firstName . ' ' . $lastName);
    }

    /**
     * Get the creator's display name with "Dr." prefix
     */
    private function getCreatorDisplayName(): string
    {
        if (!$this->creator) {
            return 'Unknown';
        }

        $firstName = $this->creator->first_name ?? '';
        $lastName = $this->creator->last_name ?? '';
        $fullName = trim($firstName . ' ' . $lastName);
        
        // If we have a full name, prefix with Dr.
        if (!empty($fullName)) {
            return 'Dr. ' . $fullName;
        }
        
        // Fallback to regular name if available
        if ($this->creator->name) {
            return 'Dr. ' . $this->creator->name;
        }
        
        return 'Dr. Unknown';
    }
}