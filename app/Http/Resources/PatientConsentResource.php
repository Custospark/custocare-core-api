<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PatientConsentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'consent_uuid' => $this->consent_uuid,
            'patient_id' => $this->patient_id,
            'patient' => $this->whenLoaded('patient', function () {
                return new PatientResource($this->patient);
            }),
            'consent_type' => $this->consent_type,
            'consent_type_label' => $this->getConsentTypes()[$this->consent_type] ?? $this->consent_type,
            'scope_facility_ids' => $this->whenNotNull($this->scope_facility_ids),
            'scope_department_ids' => $this->whenNotNull($this->scope_department_ids),
            'scope_staff_ids' => $this->whenNotNull($this->scope_staff_ids),
            'scope_service_categories' => $this->whenNotNull($this->scope_service_categories),
            'scope_limitations' => $this->scope_limitations,
            'legal_basis' => $this->legal_basis,
            'legal_basis_label' => $this->getLegalBasisOptions()[$this->legal_basis] ?? $this->legal_basis,
            'granted_at' => $this->granted_at?->toISOString(),
            'effective_from' => $this->effective_from?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'revoked_at' => $this->revoked_at?->toISOString(),
            'revocation_reason' => $this->revocation_reason,
            'revoked_by_staff_id' => $this->revoked_by_staff_id,
            'revoker' => $this->whenLoaded('revoker', function () {
                return new StaffResource($this->revoker);
            }),
            'witnessed_by_staff_id' => $this->witnessed_by_staff_id,
            'witness' => $this->whenLoaded('witness', function () {
                return new StaffResource($this->witness);
            }),
            'signature_method' => $this->signature_method,
            'consent_ip_address' => $this->when($request->user()?->can('view_sensitive_data'), $this->consent_ip_address),
            'consent_user_agent' => $this->when($request->user()?->can('view_sensitive_data'), $this->consent_user_agent),
            'consent_form_version' => $this->consent_form_version,
            'consent_document_storage_path' => $this->consent_document_storage_path,
            'consent_document_metadata' => $this->consent_document_metadata,
            'consent_language' => $this->consent_language,
            'interpreter_used' => $this->interpreter_used,
            'interpreter_language' => $this->interpreter_language,
            'capacity_confirmed' => $this->capacity_confirmed,
            'legal_guardian_id' => $this->legal_guardian_id,
            'legal_guardian' => $this->whenLoaded('legalGuardian', function () {
                return new PatientResource($this->legalGuardian);
            }),
            'status' => $this->status,
            'is_active' => $this->isActive(),
            'is_expired' => $this->isExpired(),
            'is_revoked' => $this->isRevoked(),
            'superseded_by_consent_id' => $this->superseded_by_consent_id,
            'superseded_by' => $this->whenLoaded('supersededBy', function () {
                return new PatientConsentResource($this->supersededBy);
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->when($request->user()?->can('view_deleted_consents'), $this->deleted_at?->toISOString()),
            'audit_trail' => $this->when($request->user()?->can('view_audit_trail'), $this->audit_trail),
            'metadata' => $this->metadata,
            
            // Computed properties
            'days_until_expiry' => $this->expires_at?->diffInDays(now()),
            'is_expiring_soon' => $this->expires_at?->isFuture() && $this->expires_at?->diffInDays(now()) <= 30,
        ];
    }

    /**
     * Customize the outgoing response for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\Response  $response
     * @return void
     */
    public function withResponse($request, $response)
    {
        $response->header('X-Consent-Version', '1.0');
        $response->header('X-Consent-Compliance', 'GDPR-HIPAA-21CFR11');
    }
}