<?php

namespace Database\Factories;

use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FacilityFactory extends Factory
{
    protected $model = Facility::class;

    public function definition(): array
    {
        return [
            'facility_uuid' => Str::uuid()->toString(),
            'facility_code' => 'FAC-' . $this->faker->unique()->bothify('####'),
            'facility_name' => $this->faker->company() . ' ' . $this->faker->randomElement(['Clinic', 'Hospital', 'Medical Center']),
            'legal_entity_name' => $this->faker->company(),
            'facility_type' => 'clinic',
            'facility_tier' => 'primary',
            'address_line1' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state_province' => $this->faker->stateAbbr(),
            'postal_code' => $this->faker->postcode(),
            'country_code' => 'USA',
            'timezone' => 'America/New_York',
            'operating_hours' => ['mon-fri' => '9:00-17:00'],
            'available_services' => ['general_consultation'],
            'data_residency_region' => 'US',
            'primary_database_shard' => 'shard_01',
            'operational_status' => 'fully_operational',
            'currency' => 'USD',
        ];
    }
}
