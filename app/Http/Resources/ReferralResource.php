<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReferralResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'referral_uuid' => $this->referral_uuid,
            'patient_id' => $this->patient_id,
            'facility_id' => $this->facility_id,
            'referring_staff_id' => $this->referring_staff_id,
            'receiving_staff_id' => $this->receiving_staff_id,
            'referral_type' => $this->referral_type,
            'referral_type_text' => $this->referral_type_text,
            'referral_reason' => $this->referral_reason,
            'clinical_notes' => $this->clinical_notes,
            'external_referral_id' => $this->external_referral_id,
            'status' => $this->status,
            'status_text' => $this->status_text,
            'priority' => $this->priority,
            'priority_text' => $this->priority_text,
            'referral_date' => $this->referral_date?->toISOString(),
            'response_date' => $this->response_date?->toISOString(),
            'completed_date' => $this->completed_date?->toISOString(),
            'expiry_date' => $this->expiry_date?->toISOString(),
            'metadata' => $this->metadata,
            'created_by_staff_id' => $this->created_by_staff_id,
            'updated_by_staff_id' => $this->updated_by_staff_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            
            // Relationships
            'patient' => fn() => $this->whenLoaded('patient', fn() => [
                'id' => $this->patient->id,
                'patient_uuid' => $this->patient->patient_uuid,
                'medical_record_number_hash' => $this->patient->medical_record_number_hash,
                'first_name' => $this->patient->user?->first_name ?? null,
                'last_name' => $this->patient->user?->last_name ?? null,
                'date_of_birth' => $this->patient->date_of_birth?->toDateString(),
                'gender_identity' => $this->patient->gender_identity,
            ]),
            'facility' => fn() => $this->whenLoaded('facility', fn() => [
                'id' => $this->facility->id,
                'facility_uuid' => $this->facility->facility_uuid,
                'facility_name' => $this->facility->facility_name,
                'facility_code' => $this->facility->facility_code,
            ]),
            'referring_staff' => fn() => $this->whenLoaded('referringStaff', fn() => [
                'id' => $this->referringStaff->id,
                'first_name' => $this->referringStaff->first_name,
                'last_name' => $this->referringStaff->last_name,
                'email' => $this->referringStaff->email,
            ]),
            'receiving_staff' => fn() => $this->whenLoaded('receivingStaff', fn() => [
                'id' => $this->receivingStaff->id,
                'first_name' => $this->receivingStaff->first_name,
                'last_name' => $this->receivingStaff->last_name,
                'email' => $this->receivingStaff->email,
            ]),
            'created_by' => fn() => $this->whenLoaded('createdBy', fn() => [
                'id' => $this->createdBy->id,
                'first_name' => $this->createdBy->first_name,
                'last_name' => $this->createdBy->last_name,
                'email' => $this->createdBy->email,
            ]),
            'updated_by' => fn() => $this->whenLoaded('updatedBy', fn() => [
                'id' => $this->updatedBy->id,
                'first_name' => $this->updatedBy->first_name,
                'last_name' => $this->updatedBy->last_name,
                'email' => $this->updatedBy->email,
            ]),
        ];
    }
}