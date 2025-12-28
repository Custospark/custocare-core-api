<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicalDocumentResource extends JsonResource
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
            'document_uuid' => $this->document_uuid,
            'patient_id' => $this->patient_id,
            'facility_id' => $this->facility_id,
            'visit_id' => $this->visit_id,
            
            // Document classification
            'document_type' => $this->document_type,
            'document_type_label' => $this->getDocumentTypeLabel(),
            
            // File information
            'document_name' => $this->document_name,
            'document_description' => $this->document_description,
            'file_mime_type' => $this->file_mime_type,
            'file_extension' => $this->file_extension,
            'file_size_bytes' => $this->file_size_bytes,
            'file_size_human' => $this->human_file_size,
            
            // Metadata
            'document_date' => $this->document_date?->toDateString(),
            'authored_by_staff_id' => $this->authored_by_staff_id,
            'external_author' => $this->external_author,
            
            // Status
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            
            // Audit
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'uploaded_by_staff_id' => $this->uploaded_by_staff_id,
            'metadata' => $this->metadata,
            
            // Relationships (loaded only when requested)
            'patient' => $this->whenLoaded('patient', function () {
                return new PatientResource($this->patient);
            }),
            
            'visit' => $this->whenLoaded('visit', function () {
                return new VisitResource($this->visit);
            }),
            
            'facility' => $this->whenLoaded('facility', function () {
                return new FacilityResource($this->facility);
            }),
            
            'uploader' => $this->whenLoaded('uploader', function () {
                return new StaffResource($this->uploader);
            }),
            
            'author' => $this->whenLoaded('author', function () {
                return new StaffResource($this->author);
            }),
            
            // Links
            'links' => [
                'self' => route('clinical-documents.show', $this->id),
                'download' => route('clinical-documents.download', $this->id),
                'verify_integrity' => route('clinical-documents.verify', $this->id),
            ],
        ];
    }

    /**
     * Get document type label for display
     *
     * @return string
     */
    private function getDocumentTypeLabel(): string
    {
        $labels = [
            'lab_report' => 'Lab Report',
            'radiology_report' => 'Radiology Report',
            'pathology_report' => 'Pathology Report',
            'operative_note' => 'Operative Note',
            'discharge_summary' => 'Discharge Summary',
            'consultation_letter' => 'Consultation Letter',
            'referral_letter' => 'Referral Letter',
            'consent_form' => 'Consent Form',
            'advance_directive' => 'Advance Directive',
            'insurance_card' => 'Insurance Card',
            'identification' => 'Identification',
            'medical_record_request' => 'Medical Record Request',
            'other' => 'Other',
        ];
        
        return $labels[$this->document_type] ?? $this->document_type;
    }

    /**
     * Get status label for display
     *
     * @return string
     */
    private function getStatusLabel(): string
    {
        $labels = [
            'active' => 'Active',
            'superseded' => 'Superseded',
            'entered_in_error' => 'Entered in Error',
        ];
        
        return $labels[$this->status] ?? $this->status;
    }
}