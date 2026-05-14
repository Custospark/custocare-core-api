<?php

namespace Database\Factories;

use App\Models\AmbulanceTrip;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\Ambulance;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AmbulanceTripFactory extends Factory
{
    protected $model = AmbulanceTrip::class;

    public function definition(): array
    {
        $facility = Facility::inRandomOrder()->first() ?? Facility::factory()->create();
        $patient = Patient::inRandomOrder()->first() ?? Patient::factory()->create();
        $ambulance = Ambulance::inRandomOrder()->first() ?? Ambulance::factory()->create();
        $dispatchedAt = $this->faker->dateTimeBetween('-2 days', 'now');

        return [
            'trip_uuid' => (string) Str::uuid(),
            'facility_id' => $facility->id,
            'patient_id' => $patient->id,
            'visit_id' => null,
            'ambulance_id' => $ambulance->id,
            'dispatch_staff_id' => Staff::inRandomOrder()->first()?->id ?? Staff::factory()->create()->id,
            'requesting_staff_id' => Staff::inRandomOrder()->first()?->id ?? Staff::factory()->create()->id,
            'trip_type' => $this->faker->randomElement(['emergency', 'non_emergency', 'inter_facility_transfer']),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'urgent']),
            'status' => 'completed',
            'pickup_location' => $this->faker->address(),
            'destination_location' => $this->faker->address(),
            'dispatch_notes' => $this->faker->optional()->sentence(),
            'trip_notes' => $this->faker->optional()->paragraph(),
            'mileage' => $this->faker->optional()->randomFloat(2, 1, 100),
            'estimated_duration_minutes' => $this->faker->numberBetween(15, 120),
            'dispatched_at' => $dispatchedAt,
            'en_route_at' => (clone $dispatchedAt)->modify('+5 minutes'),
            'on_scene_at' => (clone $dispatchedAt)->modify('+15 minutes'),
            'patient_contact_at' => (clone $dispatchedAt)->modify('+20 minutes'),
            'depart_scene_at' => (clone $dispatchedAt)->modify('+30 minutes'),
            'at_destination_at' => (clone $dispatchedAt)->modify('+50 minutes'),
            'completed_at' => (clone $dispatchedAt)->modify('+60 minutes'),
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'metadata' => null,
            'created_by_staff_id' => Staff::factory(),
            'updated_by_staff_id' => Staff::factory(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn() => [
            'status' => 'requested',
            'ambulance_id' => null,
            'dispatched_at' => null,
            'en_route_at' => null,
            'on_scene_at' => null,
            'patient_contact_at' => null,
            'depart_scene_at' => null,
            'at_destination_at' => null,
            'completed_at' => null,
        ]);
    }

    public function emergency(): static
    {
        return $this->state(fn() => ['trip_type' => 'emergency', 'priority' => 'urgent']);
    }

    public function interFacility(): static
    {
        $pickupFacility = Facility::factory()->create();
        $destFacility = Facility::factory()->create();

        return $this->state(fn() => [
            'trip_type' => 'inter_facility_transfer',
            'pickup_facility_id' => $pickupFacility->id,
            'pickup_location' => $pickupFacility->facility_name . ', ' . $pickupFacility->city,
            'destination_facility_id' => $destFacility->id,
            'destination_location' => $destFacility->facility_name . ', ' . $destFacility->city,
        ]);
    }
}
