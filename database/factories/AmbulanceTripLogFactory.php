<?php

namespace Database\Factories;

use App\Models\AmbulanceTripLog;
use App\Models\AmbulanceTrip;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

class AmbulanceTripLogFactory extends Factory
{
    protected $model = AmbulanceTripLog::class;

    public function definition(): array
    {
        return [
            'trip_id' => AmbulanceTrip::factory(),
            'event_type' => $this->faker->randomElement(['status_change', 'patient_condition', 'note', 'handoff']),
            'description' => $this->faker->sentence(),
            'recorded_at' => $this->faker->dateTimeBetween('-2 days', 'now'),
            'recorded_by_staff_id' => Staff::factory(),
            'metadata' => null,
        ];
    }
}
