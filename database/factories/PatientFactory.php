<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        return [
            'patient_uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'medical_record_number_hash' => hash('sha256', $this->faker->unique()->uuid),
            'medical_record_number_encrypted' => $this->faker->sha256,
            'date_of_birth' => $this->faker->dateTimeBetween('-80 years', '-18 years')->format('Y-m-d'),
            'biological_sex' => $this->faker->randomElement(['male', 'female', 'intersex', 'unknown']),
            'gender_identity' => $this->faker->randomElement(['male', 'female', 'non_binary', 'prefer_not_to_say', 'other']),
            'blood_type' => $this->faker->randomElement(['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-', null]),
            'ethnicity' => $this->faker->randomElement(['Caucasian', 'African American', 'Hispanic', 'Asian', 'Other', null]),
            'genetic_markers' => $this->faker->optional()->randomElements(['BRCA1', 'BRCA2', 'APOE', 'HLA-B27'], 2),
            'emergency_contact_chain_encrypted' => $this->faker->optional()->randomElements([
                ['name' => $this->faker->name, 'relationship' => 'Spouse', 'phone' => $this->faker->phoneNumber],
                ['name' => $this->faker->name, 'relationship' => 'Parent', 'phone' => $this->faker->phoneNumber],
            ], 2),
            'known_allergies' => $this->faker->optional()->randomElements(['Penicillin', 'Peanuts', 'Dust', 'Latex'], 2),
            'chronic_conditions' => $this->faker->optional()->randomElements(['Hypertension', 'Diabetes', 'Asthma', 'Arthritis'], 2),
            'active_medications' => $this->faker->optional()->randomElements(['Lisinopril', 'Metformin', 'Albuterol', 'Atorvastatin'], 2),
            'is_organ_donor' => $this->faker->boolean(30),
            'advance_directives' => $this->faker->optional()->randomElements(['DNR', 'Living Will', 'Healthcare Proxy'], 2),
            'acuity_baseline' => $this->faker->numberBetween(1, 5),
            'risk_factors' => $this->faker->optional()->randomElements(['Fall Risk', 'Infection Risk', 'Pressure Ulcer Risk'], 2),
            'requires_isolation' => $this->faker->boolean(10),
            'isolation_type' => $this->faker->optional()->randomElement(['Contact', 'Droplet', 'Airborne']),
            'default_consent_level' => $this->faker->randomElement(['full', 'restricted', 'minimal', 'none']),
            'privacy_flags' => $this->faker->optional()->randomElements(['right_to_erasure_requested', 'data_portability'], 1),
            'research_participation_allowed' => $this->faker->boolean(40),
            'data_sharing_allowed' => $this->faker->boolean(60),
            'primary_insurance_provider' => $this->faker->optional()->company,
            'primary_insurance_id_encrypted' => $this->faker->optional()->sha256,
            'secondary_insurance_provider' => $this->faker->optional()->company,
            'secondary_insurance_id_encrypted' => $this->faker->optional()->sha256,
            'payment_responsibility' => $this->faker->randomElement(['self_pay', 'insurance', 'government', 'charity']),
            'primary_care_provider_staff_id' => $this->faker->optional()->numberBetween(1, 100),
            'primary_care_facility_id' => $this->faker->optional()->numberBetween(1, 50),
            'last_wellness_visit_at' => $this->faker->optional()->dateTimeBetween('-1 year', 'now'),
            'next_scheduled_appointment_at' => $this->faker->optional()->dateTimeBetween('now', '+1 year'),
            'portal_access_enabled' => true,
            'portal_terms_accepted_at' => $this->faker->optional()->dateTimeBetween('-1 year', 'now'),
            'preferred_language' => 'en',
            'preferred_communication_method' => $this->faker->randomElement(['email', 'sms', 'phone', 'postal']),
            'status' => 'active',
            'created_by_staff_id' => $this->faker->optional()->numberBetween(1, 100),
            'updated_by_staff_id' => $this->faker->optional()->numberBetween(1, 100),
            'metadata' => $this->faker->optional()->randomElements(['key1' => 'value1', 'key2' => 'value2'], 2),
        ];
    }

    public function deceased(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'deceased',
                'deceased_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            ];
        });
    }

    public function inactive(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'inactive',
            ];
        });
    }

    public function testPatient(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'test_patient',
            ];
        });
    }

    public function withFullConsent(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'default_consent_level' => 'full',
                'data_sharing_allowed' => true,
                'research_participation_allowed' => true,
            ];
        });
    }

    public function withRestrictedConsent(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'default_consent_level' => 'restricted',
                'data_sharing_allowed' => false,
                'research_participation_allowed' => false,
            ];
        });
    }

    public function requiringIsolation(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'requires_isolation' => true,
                'isolation_type' => $this->faker->randomElement(['Contact', 'Droplet', 'Airborne']),
            ];
        });
    }
}