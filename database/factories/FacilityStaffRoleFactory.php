<?php

namespace Database\Factories;

use App\Models\FacilityStaffRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FacilityStaffRoleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = FacilityStaffRole::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $roleCodes = FacilityStaffRole::ROLE_CODES;
        $shiftTypes = [
            FacilityStaffRole::SHIFT_DAY,
            FacilityStaffRole::SHIFT_NIGHT,
            FacilityStaffRole::SHIFT_ROTATING,
            FacilityStaffRole::SHIFT_ON_CALL,
            FacilityStaffRole::SHIFT_FLEXIBLE
        ];
        
        $statuses = [
            FacilityStaffRole::STATUS_ACTIVE,
            FacilityStaffRole::STATUS_ON_LEAVE,
            FacilityStaffRole::STATUS_SUSPENDED,
            FacilityStaffRole::STATUS_TERMINATED
        ];
        
        return [
            'assignment_uuid' => Str::uuid()->toString(),
            'facility_id' => \App\Models\Facility::factory(),
            'staff_id' => \App\Models\Staff::factory(),
            'role_code' => $this->faker->randomElement($roleCodes),
            'department_ids' => json_encode([1, 2, 3]),
            'is_primary_facility' => $this->faker->boolean(30),
            'privileges_bitmask' => json_encode(['prescribing', 'admitting', 'surgery']),
            'accessible_patient_populations' => json_encode(['adult', 'pediatric']),
            'prescribing_authority_at_facility' => json_encode(['schedule_ii', 'schedule_iii']),
            'shift_schedule' => json_encode(['monday' => ['start' => '08:00', 'end' => '17:00']]),
            'shift_type' => $this->faker->randomElement($shiftTypes),
            'hours_per_week' => $this->faker->numberBetween(20, 60),
            'effective_from' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'effective_to' => $this->faker->optional(0.3)->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'assignment_status' => $this->faker->randomElement($statuses),
            'credentialing_completed_at' => $this->faker->optional()->dateTime(),
            'credentialed_by_staff_id' => $this->faker->optional()->randomDigitNotNull,
            'privileging_approved_at' => $this->faker->optional()->dateTime(),
            'next_reappointment_date' => $this->faker->optional()->dateTimeBetween('+6 months', '+2 years'),
            'patients_treated_at_facility' => $this->faker->numberBetween(0, 1000),
            'facility_satisfaction_score' => $this->faker->optional()->randomFloat(2, 1, 5),
            'created_by_staff_id' => $this->faker->optional()->randomDigitNotNull,
            'metadata' => json_encode(['notes' => $this->faker->sentence()])
        ];
    }

    /**
     * Indicate that the assignment is active.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function active()
    {
        return $this->state(function (array $attributes) {
            return [
                'assignment_status' => FacilityStaffRole::STATUS_ACTIVE,
                'effective_from' => $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
                'effective_to' => $this->faker->optional(0.5)->dateTimeBetween('+1 month', '+1 year')->format('Y-m-d'),
            ];
        });
    }

    /**
     * Indicate that the assignment is for primary facility.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function primary()
    {
        return $this->state(function (array $attributes) {
            return [
                'is_primary_facility' => true,
            ];
        });
    }

    /**
     * Indicate that the assignment is for attending physician.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function attendingPhysician()
    {
        return $this->state(function (array $attributes) {
            return [
                'role_code' => 'attending_physician',
            ];
        });
    }

    /**
     * Indicate that the assignment is for registered nurse.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function registeredNurse()
    {
        return $this->state(function (array $attributes) {
            return [
                'role_code' => 'registered_nurse',
            ];
        });
    }

    /**
     * Indicate that the assignment is expiring soon.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function expiringSoon()
    {
        return $this->state(function (array $attributes) {
            return [
                'assignment_status' => FacilityStaffRole::STATUS_ACTIVE,
                'effective_to' => now()->addDays(15)->format('Y-m-d'),
            ];
        });
    }
}