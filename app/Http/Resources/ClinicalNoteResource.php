<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicalNoteResource extends JsonResource
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
            'uuid' => $this->uuid ?? null,
            'facility_id' => $this->facility_id,
            'visit_id' => $this->visit_id,
            'patient_id' => $this->patient_id,
            'staff_id' => $this->staff_id,
            
            // Clinical Content
            'subjective' => $this->subjective,
            'objective' => $this->objective,
            'assessment' => $this->assessment,
            'plan' => $this->plan,
            'review_of_systems' => $this->review_of_systems,
            'past_medical_history' => $this->past_medical_history,
            
            // Metadata
            'note_type' => $this->note_type,
            'note_status' => $this->note_status,
            'noted_at' => $this->noted_at?->toISOString(),
            'signature' => $this->signature,
            
            // JSON Fields
            'custom_fields' => $this->custom_fields,
            'structured_data' => $this->structured_data,
            
            // Revision Tracking
            'parent_note_id' => $this->parent_note_id,
            'is_amendment' => $this->isAmendment(),
            'is_draft' => $this->isDraft(),
            'is_final' => $this->isFinal(),
            'is_cancelled' => $this->isCancelled(),
            
            // Full Text
            'full_note_text' => $this->full_note_text,
            
            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            
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
            
            'parent_note' => $this->whenLoaded('parentNote', function () {
                return [
                    'id' => $this->parentNote->id,
                    'noted_at' => $this->parentNote->noted_at?->toISOString(),
                ];
            }),
            
            'child_notes' => $this->whenLoaded('childNotes', function () {
                return ClinicalNoteResource::collection($this->childNotes);
            }),
        ];
    }
}