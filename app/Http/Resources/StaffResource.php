<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffResource extends JsonResource
{
    /**
     * Build display name from user profile safely.
     */
    private function buildDisplayName($user): ?string
    {
        if (!$user) {
            return null;
        }

        $name = $user->display_name
            ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

        return $name !== '' ? $name : null;
    }

    /**
     * Generic "valid/expired/not_provided" status for expiry fields.
     */
    private function dateStatus($date): string
    {
        if (!$date) return 'not_provided';
        return $date < now() ? 'expired' : 'valid';
    }

    /**
     * HIPAA training status is different wording in your API.
     */
    private function hipaaStatus($date): string
    {
        if (!$date) return 'not_completed';
        return $date < now() ? 'expired' : 'valid';
    }

    /**
     * Credential renewal within the next 60 days.
     */
    private function requiresCredentialRenewal(): bool
    {
        $threshold = now()->addDays(60);

        $licenseExpiring = $this->license_expiry_date && $this->license_expiry_date <= $threshold;
        $deaExpiring     = $this->dea_expiry_date && $this->dea_expiry_date <= $threshold;
        $hipaaExpiring   = $this->hipaa_training_expiry && $this->hipaa_training_expiry <= $threshold;

        return $licenseExpiring || $deaExpiring || $hipaaExpiring;
    }

    public function toArray(Request $request): array
    {
        /**
         * ✅ Eager-loading safe:
         * - whenLoaded('user') returns MissingValue if not loaded
         * - so we must guard before accessing properties
         */
        $user = $this->relationLoaded('user') ? $this->user : null;

        $staffName = $this->buildDisplayName($user);

        // ✅ Facility assignment summary (appended, legacy-safe)
        $facilityId = $request->integer('facility_id');
        $assignment = null;

        if ($facilityId && $this->relationLoaded('facilityStaffRoles')) {
            $assignment = $this->facilityStaffRoles->firstWhere('facility_id', $facilityId);
        }

        return [
            // ===== Staff fields (keep stable) =====
            'id' => $this->id,
            'staff_uuid' => $this->staff_uuid,
            'user_id' => $this->user_id,
            'employee_id' => $this->employee_id,
            'professional_title' => $this->professional_title,

            'license_issuing_state' => $this->license_issuing_state,
            'license_issuing_country' => $this->license_issuing_country,
            'license_expiry_date' => $this->license_expiry_date,
            'license_status' => $this->dateStatus($this->license_expiry_date),

            'specialization_codes' => $this->specialization_codes,
            'board_certifications' => $this->board_certifications,
            'additional_certifications' => $this->additional_certifications,
            'npi_number' => $this->npi_number,

            'dea_expiry_date' => $this->dea_expiry_date,
            'dea_status' => $this->dateStatus($this->dea_expiry_date),

            'employment_status' => $this->employment_status,
            'employment_type' => $this->employment_type,
            'hire_date' => $this->hire_date,
            'termination_date' => $this->termination_date,
            'termination_reason' => $this->termination_reason,

            'clinical_privileges' => $this->clinical_privileges,
            'prescribing_authority' => $this->prescribing_authority,
            'can_supervise_trainees' => (bool) $this->can_supervise_trainees,
            'can_order_controlled_substances' => (bool) $this->can_order_controlled_substances,
            'can_sign_death_certificates' => (bool) $this->can_sign_death_certificates,

            'global_role_level' => $this->global_role_level,
            'reports_to_staff_id' => $this->reports_to_staff_id,

            'default_schedule' => $this->default_schedule,
            'max_concurrent_patients' => $this->max_concurrent_patients,
            'average_appointment_duration_minutes' => $this->average_appointment_duration_minutes,
            'accepts_new_patients' => (bool) $this->accepts_new_patients,

            'patient_satisfaction_score' => $this->patient_satisfaction_score,
            'total_patients_treated' => $this->total_patients_treated,
            'quality_metrics' => $this->quality_metrics,
            'last_peer_review_date' => $this->last_peer_review_date,
            'last_competency_assessment_date' => $this->last_competency_assessment_date,

            'background_check_completed' => (bool) $this->background_check_completed,
            'background_check_date' => $this->background_check_date,
            'drug_screening_completed' => (bool) $this->drug_screening_completed,
            'drug_screening_date' => $this->drug_screening_date,

            'immunization_records' => $this->immunization_records,
            'tb_test_records' => $this->tb_test_records,

            'hipaa_training_completed' => (bool) $this->hipaa_training_completed,
            'hipaa_training_date' => $this->hipaa_training_date,
            'hipaa_training_expiry' => $this->hipaa_training_expiry,
            'hipaa_status' => $this->hipaaStatus($this->hipaa_training_expiry),

            'system_permissions' => $this->system_permissions,
            'accessible_facility_ids' => $this->accessible_facility_ids,
            'accessible_department_ids' => $this->accessible_department_ids,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,

            // ===== ✅ Selected user fields (stable, from your users table) =====
            // NOTE: these are safe only if user is loaded
            'global_user_uuid' => $user?->global_user_uuid,
            'staff_name' => $staffName,
            'user_title' => $user?->title,
            'user_gender' => $user?->gender,
            'user_identity_state' => $user?->identity_state,

            // ===== ✅ Appended (legacy-safe): facility assignment summary =====
            'facility_role_summary' => $assignment
                ? new FacilityStaffRoleSummaryResource($assignment)
                : null,

            // ===== Relationships (kept) =====
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),

            'subordinates' => $this->whenLoaded('subordinates', function () {
                return StaffSimpleResource::collection($this->subordinates);
            }),

            'created_by' => $this->whenLoaded('createdBy', fn () => new UserResource($this->createdBy)),
            'updated_by' => $this->whenLoaded('updatedBy', fn () => new UserResource($this->updatedBy)),

            // ===== Computed properties (kept) =====
            'is_active' => $this->employment_status === 'active',
            'can_prescribe' => $this->canPrescribe(),
            'has_expired_license' => $this->hasExpiredLicense(),
            'has_expired_dea' => $this->hasExpiredDEA(),
            'requires_credential_renewal' => $this->requiresCredentialRenewal(),
        ];
    }
}

/**
 * Simple staff resource for nested relationships.
 */
class StaffSimpleResource extends JsonResource
{
    private function buildDisplayName($user): ?string
    {
        if (!$user) return null;

        $name = $user->display_name
            ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

        return $name !== '' ? $name : null;
    }

    public function toArray(Request $request): array
    {
        $user = $this->relationLoaded('user') ? $this->user : null;

        return [
            'id' => $this->id,
            'staff_uuid' => $this->staff_uuid,
            'employee_id' => $this->employee_id,
            'professional_title' => $this->professional_title,
            'global_role_level' => $this->global_role_level,
            'employment_status' => $this->employment_status,

            // ✅ Selected user fields
            'global_user_uuid' => $user?->global_user_uuid,
            'staff_name' => $this->buildDisplayName($user),
            'user_identity_state' => $user?->identity_state,
        ];
    }
}
