<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrescriptionItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'medication_name' => $this->medication_name,
            'brand_name' => $this->brand_name,
            'strength' => $this->strength,
            'full_name' => $this->full_name,
            'dosage_form' => $this->dosage_form,
            'dosage_quantity' => $this->dosage_quantity,
            'dosage_unit' => $this->dosage_unit,
            'dosage_text' => $this->dosage_text,
            'frequency' => $this->frequency,
            'duration_value' => $this->duration_value,
            'duration_unit' => $this->duration_unit ?? null,
            'duration_text' => $this->duration_text,
            'total_quantity' => $this->total_quantity,
            'route' => $this->route,
            'instructions' => $this->instructions,
            'patient_instructions' => $this->getPatientInstructions(),
            'as_needed' => $this->as_needed,
            'as_needed_reason' => $this->as_needed_reason,
            'administration_instructions' => $this->administration_instructions,
            'refills' => $this->refills,
            'refill_instructions' => $this->getRefillInstructionsText(),
            'medication_type' => $this->medication_type,
            'monitoring_required' => $this->monitoring_required,
            'common_side_effects' => $this->common_side_effects,
            'clinical_reasoning' => $this->clinical_reasoning,
            'substitution' => $this->substitution,
        ];
    }
}