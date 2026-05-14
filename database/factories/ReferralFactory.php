<?php

namespace Database\Factories;

use App\Models\Referral;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ReferralFactory extends Factory
{
    protected $model = Referral::class;

    public function definition(): array
    {
        $facility = Facility::inRandomOrder()->first() ?? Facility::factory()->create();
        $patient = Patient::inRandomOrder()->first() ?? Patient::factory()->create();
        $referringStaff = Staff::inRandomOrder()->first() ?? Staff::factory()->create();
        $receivingStaff = Staff::inRandomOrder()->first() ?? Staff::factory()->create();

        // 50% chance this is a cross-facility referral
        $receivingFacility = $this->faker->boolean(50)
            ? (Facility::inRandomOrder()->where('id', '!=', $facility->id)->first() ?? Facility::factory()->create())
            : null;

        return [
            'referral_uuid' => (string) Str::uuid(),
            'patient_id' => $patient->id,
            'facility_id' => $facility->id,
            'receiving_facility_id' => $receivingFacility?->id,
            'referring_staff_id' => $this->faker->boolean(80) ? $referringStaff->id : null,
            'receiving_staff_id' => $this->faker->boolean(60) ? $receivingStaff->id : null,
            'referral_type' => $receivingFacility ? 'external' : 'internal',
            'referral_reason' => $this->faker->sentence(),
            'clinical_notes' => $this->faker->optional()->paragraph(),
            'external_referral_id' => $receivingFacility ? $this->faker->optional()->uuid() : null,
            'status' => $this->faker->randomElement(['pending', 'accepted', 'rejected', 'completed', 'cancelled']),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'urgent']),
            'referral_date' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'response_date' => $this->faker->optional()->dateTimeBetween('-5 months', 'now'),
            'completed_date' => $this->faker->optional()->dateTimeBetween('-4 months', 'now'),
            'expiry_date' => $this->faker->optional()->dateTimeBetween('now', '+6 months'),
            'metadata' => $this->faker->optional()->randomElement([
                ['notes' => 'Additional information'],
                ['priority_notes' => 'Urgent follow-up needed'],
                [],
            ]),
            'created_by_staff_id' => $referringStaff->id,
            'updated_by_staff_id' => $referringStaff->id,
        ];
    }

    /**
     * Same-facility referral (internal).
     */
    public function sameFacility(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'receiving_facility_id' => $attributes['facility_id'] ?? null,
                'referral_type' => 'internal',
                'external_referral_id' => null,
            ];
        });
    }

    /**
     * Cross-facility referral (external) with a different receiving facility.
     */
    public function crossFacility(): static
    {
        $receivingFacility = Facility::factory()->create();

        return $this->state(function (array $attributes) use ($receivingFacility) {
            return [
                'receiving_facility_id' => $receivingFacility->id,
                'referral_type' => 'external',
            ];
        });
    }

    /**
     * Facility-to-facility (no specific staff on either end).
     */
    public function facilityToFacility(): static
    {
        $receivingFacility = Facility::factory()->create();

        return $this->state(function (array $attributes) use ($receivingFacility) {
            return [
                'receiving_facility_id' => $receivingFacility->id,
                'referring_staff_id' => null,
                'receiving_staff_id' => null,
                'referral_type' => 'external',
            ];
        });
    }

    public function pending(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'pending',
                'response_date' => null,
                'completed_date' => null,
            ];
        });
    }

    public function accepted(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'accepted',
            ];
        });
    }

    public function rejected(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'rejected',
                'metadata' => array_merge(
                    $attributes['metadata'] ?? [],
                    ['rejection_reason' => 'Not appropriate for our services']
                ),
            ];
        });
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'completed',
                'completed_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
            ];
        });
    }

    public function cancelled(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'cancelled',
                'metadata' => array_merge(
                    $attributes['metadata'] ?? [],
                    ['cancellation_reason' => 'Patient no longer needs referral']
                ),
            ];
        });
    }

    public function internal(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'referral_type' => 'internal',
                'external_referral_id' => null,
            ];
        });
    }

    public function external(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'referral_type' => 'external',
            ];
        });
    }

    public function urgent(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'priority' => 'urgent',
            ];
        });
    }
}