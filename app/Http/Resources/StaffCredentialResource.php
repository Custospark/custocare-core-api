<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffCredentialResource extends JsonResource
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
            'credential_uuid' => $this->credential_uuid,
            'staff_id' => $this->staff_id,
            'credential_type' => $this->credential_type,
            'credential_name' => $this->credential_name,
            'credential_number_masked' => $this->maskCredentialNumber(),
            'issuing_authority' => $this->issuing_authority,
            'issuing_authority_contact' => $this->issuing_authority_contact,
            'issuing_state_country' => $this->issuing_state_country,
            'issued_date' => $this->issued_date ? $this->issued_date->format('Y-m-d') : null,
            'valid_from' => $this->valid_from ? $this->valid_from->format('Y-m-d') : null,
            'valid_to' => $this->valid_to ? $this->valid_to->format('Y-m-d') : null,
            'requires_renewal' => $this->requires_renewal,
            'renewal_reminder_date' => $this->renewal_reminder_date ? $this->renewal_reminder_date->format('Y-m-d') : null,
            'verification_status' => $this->verification_status,
            'verified_at' => $this->verified_at ? $this->verified_at->format('Y-m-d H:i:s') : null,
            'verification_method' => $this->verification_method,
            'verification_notes' => $this->verification_notes,
            'document_mime_type' => $this->document_mime_type,
            'document_size_bytes' => $this->document_size_bytes,
            'snapshot_taken_at' => $this->snapshot_taken_at ? $this->snapshot_taken_at->format('Y-m-d H:i:s') : null,
            'is_current' => $this->is_current,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
            'deleted_at' => $this->deleted_at ? $this->deleted_at->format('Y-m-d H:i:s') : null,
            
            // Relationships
            'staff' => $this->whenLoaded('staff', function () {
                return new StaffResource($this->staff);
            }),
            
            'verified_by' => $this->whenLoaded('verifiedBy', function () {
                return new StaffResource($this->verifiedBy);
            }),
            
            'created_by' => $this->whenLoaded('createdBy', function () {
                return new StaffResource($this->createdBy);
            }),
            
            'superseded_by' => $this->whenLoaded('supersededBy', function () {
                return new StaffCredentialResource($this->supersededBy);
            }),
            
            'supersedes' => $this->whenLoaded('supersedes', function () {
                return StaffCredentialResource::collection($this->supersedes);
            }),
            
            // Status flags
            'is_expired' => $this->isExpired(),
            'requires_renewal_soon' => $this->requiresRenewal(),
            'days_until_expiry' => $this->getDaysUntilExpiry(),
            
            // Metadata
            'metadata' => $this->metadata,
            
            // Document access
            'document_url' => $this->when($this->document_storage_path, function () {
                return $this->getDocumentUrl();
            }),
        ];
    }

    /**
     * Mask credential number for security
     */
    private function maskCredentialNumber(): ?string
    {
        if (!$this->credential_number_hash) {
            return null;
        }
        
        // Show last 4 characters only
        $hash = $this->credential_number_hash;
        if (strlen($hash) > 4) {
            return '****' . substr($hash, -4);
        }
        
        return '****';
    }

    /**
     * Get document URL (implementation depends on your storage setup)
     */
    private function getDocumentUrl(): ?string
    {
        if (!$this->document_storage_path) {
            return null;
        }
        
        // This is a placeholder - implement based on your storage system
        // For S3: return Storage::disk('s3')->temporaryUrl($this->document_storage_path, now()->addMinutes(30));
        // For local: return Storage::url($this->document_storage_path);
        
        return route('api.credentials.document', ['credential' => $this->credential_uuid]);
    }

    /**
     * Calculate days until expiry
     */
    private function getDaysUntilExpiry(): ?int
    {
        if (!$this->valid_to) {
            return null;
        }
        
        return now()->diffInDays($this->valid_to, false);
    }
}