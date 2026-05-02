<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VitalResource extends JsonResource
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
            'facility_id' => $this->facility_id,
            'visit_id' => $this->visit_id,
            'patient_id' => $this->patient_id,
            'staff_id' => $this->staff_id,
            
            // Core Vital Signs
            'temperature' => $this->temperature,
            'temperature_unit' => $this->temperature_unit,
            'heart_rate' => $this->heart_rate,
            'respiratory_rate' => $this->respiratory_rate,
            'systolic_bp' => $this->systolic_bp,
            'diastolic_bp' => $this->diastolic_bp,
            'bp_position' => $this->bp_position,
            'bp_location' => $this->bp_location,
            
            // Calculated BP values
            'map' => $this->map,
            'pulse_pressure' => $this->pulse_pressure,
            'is_hypertensive' => $this->isHypertensive(),
            
            // Advanced Vitals
            'oxygen_saturation' => $this->oxygen_saturation,
            'oxygen_flow_rate' => $this->oxygen_flow_rate,
            'oxygen_delivery_device' => $this->oxygen_delivery_device,
            'height' => $this->height,
            'height_unit' => $this->height_unit,
            'weight' => $this->weight,
            'weight_unit' => $this->weight_unit,
            'bmi' => $this->bmi,
            'bmi_category' => $this->bmi_category,
            'pain_score' => $this->pain_score,
            'pain_scale_type' => $this->pain_scale_type,
            'pain_location' => $this->pain_location,
            
            // Pediatric Vitals
            'head_circumference' => $this->head_circumference,
            'length' => $this->length,
            
            // Measurement Context
            'measured_at' => $this->measured_at?->toISOString(),
            'measurement_method' => $this->measurement_method,
            'device_id' => $this->device_id,
            'consciousness_level' => $this->consciousness_level,
            'general_appearance' => $this->general_appearance,
            
            // Custom Fields
            'custom_fields' => $this->custom_fields,
            'percentiles' => $this->percentiles,
            
            // Flagging & Alerts
            'flag_status' => $this->flag_status,
            'clinical_alert' => $this->clinical_alert,
            
            // Status Flags
            'has_fever' => $this->hasFever(),
            'is_hypothermic' => $this->isHypothermic(),
            'is_hypoxic' => $this->isHypoxic(),
            'is_tachycardic' => $this->isTachycardic(),
            'is_bradycardic' => $this->isBradycardic(),
            'is_tachypneic' => $this->isTachypneic(),
            
            // Formatted Display
            'formatted_vitals' => $this->formatted_vitals,
            
            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships (when loaded)
            'facility' => $this->whenLoaded('facility', function () {
                return [
                    'id' => $this->facility->id,
                    'name' => $this->facility->facility_name,
                    'code' => $this->facility->facility_code,
                ];
            }),
            
            'visit' => $this->whenLoaded('visit', function () {
                return [
                    'id' => $this->visit->id,
                    'visit_date_time' => $this->visit->visit_date_time?->toISOString(),
                ];
            }),
            
            'patient' => $this->whenLoaded('patient', function () {
                return [
                    'id' => $this->patient->id,
                    'first_name' => $this->patient->user->first_name ?? null,
                    'last_name' => $this->patient->user->last_name ?? null,
                    'full_name' => ($this->patient->user->first_name ?? '') . ' ' . ($this->patient->user->last_name ?? ''),
                ];
            }),
            
            'staff' => $this->whenLoaded('staff', function () {
                return [
                    'id' => $this->staff->id,
                    'first_name' => $this->staff->user->first_name ?? null,
                    'last_name' => $this->staff->user->last_name ?? null,
                    'full_name' => ($this->staff->user->first_name ?? '') . ' ' . ($this->staff->user->last_name ?? ''),
                ];
            }),
        ];
    }
}