<?php

namespace Database\Factories;

use App\Models\AmbulanceCrewMember;
use App\Models\Ambulance;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

class AmbulanceCrewMemberFactory extends Factory
{
    protected $model = AmbulanceCrewMember::class;

    public function definition(): array
    {
        return [
            'ambulance_id' => Ambulance::factory(),
            'staff_id' => Staff::factory(),
            'role' => $this->faker->randomElement(['driver', 'paramedic', 'emt', 'attendant', 'nurse']),
            'is_primary_driver' => false,
            'certification_expiry' => $this->faker->optional()->dateTimeBetween('now', '+2 years'),
            'active' => true,
            'assigned_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'unassigned_at' => null,
            'metadata' => null,
        ];
    }

    public function driver(): static
    {
        return $this->state(fn() => ['role' => 'driver', 'is_primary_driver' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn() => [
            'active' => false,
            'unassigned_at' => now(),
        ]);
    }
}
