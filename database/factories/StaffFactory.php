<?php

namespace Database\Factories;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class StaffFactory extends Factory
{
    protected $model = Staff::class;

    public function definition(): array
    {
        return [
            'staff_uuid' => Str::uuid()->toString(),
            'user_id' => \App\Models\User::factory(),
            'employee_id' => 'EMP' . $this->faker->unique()->numerify('#######'),
            'professional_title' => $this->faker->randomElement([
                'Medical Doctor',
                'Registered Nurse',
                'Physician Assistant',
                'Nurse Practitioner',
                'Pharmacist',
                'Therapist'
            ]),
            'professional_license_number_encrypted' => null,
            'professional_license_number_hash' => null,
            'license_issuing_state' => $this->faker->stateAbbr(),
            'license_issuing_country' => 'USA',
            'license_expiry_date' => $this->faker->dateTimeBetween('now', '+2 years')->format('Y-m-d'),
            
            'specialization_codes' => ['207R00000X'], // Internal Medicine
            'board_certifications' => null,
            'additional_certifications' => null,
            'npi_number' => $this->faker->numerify('##########'),
            'dea_number_encrypted' => null,
            'dea_expiry_date' => $this->faker->dateTimeBetween('now', '+2 years')->format('Y-m-d'),
            
            'employment_status' => $this->faker->randomElement(['active', 'active', 'active', 'on_leave']),
            'employment_type' => $this->faker->randomElement(['full_time', 'part_time']),
            'hire_date' => $this->faker->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'termination_date' => null,
            'termination_reason' => null,
            
            'clinical_privileges' => ['routine_exams', 'prescribe_medication', 'order_labs'],
            'prescribing_authority' => ['Schedule II', 'Schedule III', 'Schedule IV'],
            'can_supervise_trainees' => $this->faker->boolean(30),
            'can_order_controlled_substances' => $this->faker->boolean(70),
            'can_sign_death_certificates' => $this->faker->boolean(50),
            
            'global_role_level' => $this->faker->randomElement([
                'attending_physician',
                'nurse_practitioner',
                'physician_assistant',
                'registered_nurse',
                'pharmacist'
            ]),
            'reports_to_staff_id' => null,
            
            'default_schedule' => [
                'monday' => ['start' => '08:00', 'end' => '17:00'],
                'tuesday' => ['start' => '08:00', 'end' => '17:00'],
                'wednesday' => ['start' => '08:00', 'end' => '17:00'],
                'thursday' => ['start' => '08:00', 'end' => '17:00'],
                'friday' => ['start' => '08:00', 'end' => '17:00']
            ],
            'max_concurrent_patients' => $this->faker->numberBetween(5, 20),
            'average_appointment_duration_minutes' => $this->faker->numberBetween(15, 60),
            'accepts_new_patients' => $this->faker->boolean(80),
            
            'patient_satisfaction_score' => $this->faker->randomFloat(2, 3.5, 5.0),
            'total_patients_treated' => $this->faker->numberBetween(100, 5000),
            'quality_metrics' => ['readmission_rate' => '2.5%', 'patient_followup' => '95%'],
            'last_peer_review_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'last_competency_assessment_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            
            'background_check_completed' => true,
            'background_check_date' => $this->faker->dateTimeBetween('-2 years', '-6 months')->format('Y-m-d'),
            'drug_screening_completed' => true,
            'drug_screening_date' => $this->faker->dateTimeBetween('-2 years', '-6 months')->format('Y-m-d'),
            'immunization_records' => ['flu_shot' => '2023-10-15', 'covid_vaccine' => '2023-01-20'],
            'tb_test_records' => ['last_test' => '2023-06-01', 'result' => 'negative'],
            'hipaa_training_completed' => true,
            'hipaa_training_date' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'hipaa_training_expiry' => $this->faker->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            
            'work_phone_encrypted' => null,
            'work_email_encrypted' => null,
            'emergency_contact_encrypted' => null,
            
            'system_permissions' => ['view_patient_records', 'write_patient_notes', 'order_tests'],
            'accessible_facility_ids' => [1, 2],
            'accessible_department_ids' => [101, 102],
            
            'created_by_staff_id' => null,
            'updated_by_staff_id' => null,
            'metadata' => null,
        ];
    }

    public function active(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'employment_status' => 'active',
            ];
        });
    }

    public function terminated(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'employment_status' => 'terminated',
                'termination_date' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
                'termination_reason' => $this->faker->sentence(),
            ];
        });
    }

    public function withExpiredLicense(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'license_expiry_date' => $this->faker->dateTimeBetween('-1 year', '-1 day')->format('Y-m-d'),
            ];
        });
    }

    public function withExpiringLicense(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'license_expiry_date' => $this->faker->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            ];
        });
    }

    public function physician(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'professional_title' => 'Medical Doctor',
                'global_role_level' => 'attending_physician',
                'can_order_controlled_substances' => true,
                'can_sign_death_certificates' => true,
            ];
        });
    }

    public function nurse(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'professional_title' => 'Registered Nurse',
                'global_role_level' => 'registered_nurse',
                'can_order_controlled_substances' => false,
                'can_sign_death_certificates' => false,
            ];
        });
    }

    public function admin(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'global_role_level' => 'facility_admin',
                'system_permissions' => ['all_access'],
            ];
        });
    }
}