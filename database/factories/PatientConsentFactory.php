<?php

namespace Database\Factories;

use App\Models\PatientConsent;
use Illuminate\Database\Eloquent\Factories\Factory;

class PatientConsentFactory extends Factory
{
    protected $model = PatientConsent::class;

    public function definition()
    {
        return [
            'consent_uuid' => $this->faker->uuid,
            'patient_id' => \App\Models\Patient::factory(),
            'consent_type' => $this->faker->randomElement([
                'treatment', 'procedures', 'anesthesia', 'blood_transfusion',
                'research', 'data_sharing', 'marketing', 'photography',
                'teaching', 'organ_donation', 'release_of_info'
            ]),
            'scope_facility_ids' => null,
            'scope_department_ids' => null,
            'scope_staff_ids' => null,
            'scope_service_categories' => null,
            'scope_limitations' => $this->faker->optional()->sentence,
            'legal_basis' => 'explicit_consent',
            'granted_at' => $this->faker->dateTimeBetween('-1 year', '-1 day'),
            'effective_from' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'expires_at' => $this->faker->optional()->dateTimeBetween('now', '+1 year'),
            'revoked_at' => null,
            'revocation_reason' => null,
            'revoked_by_staff_id' => null,
            'witnessed_by_staff_id' => \App\Models\Staff::factory(),
            'witness_signature_hash' => $this->faker->sha256,
            'patient_signature_hash' => $this->faker->sha256,
            'signature_method' => $this->faker->randomElement(['digital', 'wet_signature', 'verbal', 'implied']),
            'consent_ip_address' => $this->faker->optional()->ipv4,
            'consent_user_agent' => $this->faker->optional()->userAgent,
            'consent_device_fingerprint' => $this->faker->optional()->sha256,
            'consent_geolocation' => $this->faker->optional()->latitude . ',' . $this->faker->optional()->longitude,
            'consent_form_version' => '1.' . $this->faker->randomDigit,
            'consent_document_hash' => $this->faker->sha256,
            'consent_document_storage_path' => $this->faker->optional()->filePath(),
            'consent_document_metadata' => null,
            'consent_language' => 'en',
            'interpreter_used' => false,
            'interpreter_language' => null,
            'capacity_confirmed' => true,
            'legal_guardian_id' => null,
            'status' => 'active',
            'superseded_by_consent_id' => null,
            'audit_trail' => null,
            'metadata' => null,
        ];
    }

    public function active()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'active',
                'revoked_at' => null,
                'expires_at' => $this->faker->dateTimeBetween('+1 month', '+1 year'),
            ];
        });
    }

    public function expired()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'expired',
                'expires_at' => $this->faker->dateTimeBetween('-1 year', '-1 day'),
            ];
        });
    }

    public function revoked()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'revoked',
                'revoked_at' => $this->faker->dateTimeBetween('-1 month', '-1 day'),
                'revocation_reason' => $this->faker->sentence,
                'revoked_by_staff_id' => \App\Models\Staff::factory(),
            ];
        });
    }

    public function superseded()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'superseded',
            ];
        });
    }

    public function withFacilityScope(array $facilityIds)
    {
        return $this->state(function (array $attributes) use ($facilityIds) {
            return [
                'scope_facility_ids' => $facilityIds,
            ];
        });
    }

    public function withStaffScope(array $staffIds)
    {
        return $this->state(function (array $attributes) use ($staffIds) {
            return [
                'scope_staff_ids' => $staffIds,
            ];
        });
    }
}