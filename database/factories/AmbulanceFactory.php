<?php

namespace Database\Factories;

use App\Models\Ambulance;
use App\Models\Facility;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AmbulanceFactory extends Factory
{
    protected $model = Ambulance::class;

    public function definition(): array
    {
        $facility = Facility::inRandomOrder()->first() ?? Facility::factory()->create();

        return [
            'ambulance_uuid' => (string) Str::uuid(),
            'facility_id' => $facility->id,
            'crew_team_lead_staff_id' => Staff::inRandomOrder()->first()?->id ?? Staff::factory()->create()->id,
            'vehicle_identifier' => strtoupper($this->faker->randomLetter() . $this->faker->randomLetter()) . '-' . $this->faker->randomNumber(4),
            'vehicle_type' => $this->faker->randomElement(['bls', 'als', 'critical_care', 'patient_transport', 'type_ii']),
            'equipment_level' => $this->faker->randomElement(['basic', 'advanced', 'critical']),
            'status' => $this->faker->randomElement(['available', 'in_service', 'out_of_service', 'maintenance']),
            'last_service_date' => $this->faker->optional()->dateTimeBetween('-1 year', '-1 month'),
            'next_service_due_date' => $this->faker->optional()->dateTimeBetween('now', '+6 months'),
            'current_mileage' => $this->faker->numberBetween(5000, 150000),
            'capacity' => $this->faker->numberBetween(1, 2),
            'features' => $this->faker->optional()->randomElements(['stretcher', 'defibrillator', 'oxygen', 'suction'], 3),
            'metadata' => null,
            'created_by_staff_id' => Staff::factory(),
            'updated_by_staff_id' => Staff::factory(),
        ];
    }

    public function available(): static
    {
        return $this->state(fn() => ['status' => 'available']);
    }

    public function bls(): static
    {
        return $this->state(fn() => ['vehicle_type' => 'bls', 'equipment_level' => 'basic']);
    }

    public function als(): static
    {
        return $this->state(fn() => ['vehicle_type' => 'als', 'equipment_level' => 'advanced']);
    }
}
