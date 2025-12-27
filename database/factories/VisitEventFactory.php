<?php

namespace Database\Factories;

use App\Models\VisitEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class VisitEventFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = VisitEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventTypes = [
            'visit_created',
            'patient_arrived',
            'patient_registered',
            'triage_started',
            'triage_completed',
            'vitals_recorded',
            'routed_to_department',
            'provider_assigned',
            'consultation_started',
            'consultation_completed',
            'diagnostic_ordered',
            'diagnostic_completed',
            'medication_ordered',
            'medication_administered',
            'procedure_started',
            'procedure_completed',
            'condition_changed',
            'admission_ordered',
            'transfer_initiated',
            'discharge_ordered',
            'discharge_completed',
            'visit_cancelled',
            'patient_left_ama',
            'patient_lwbs',
            'clinical_note_added',
            'billing_updated',
            'insurance_verified',
            'consent_obtained',
            'alert_triggered',
            'escalation_required'
        ];

        $actorTypes = ['staff', 'patient', 'system', 'device', 'external_system'];
        
        $eventOccurredAt = $this->faker->dateTimeBetween('-1 month', 'now');
        $eventRecordedAt = clone $eventOccurredAt;
        $eventRecordedAt->modify('+' . rand(10, 1000) . ' milliseconds');

        return [
            'event_uuid' => Str::uuid()->toString(),
            'facility_id' => $this->faker->numberBetween(1, 10),
            'visit_id' => $this->faker->numberBetween(1, 100),
            'event_type' => $this->faker->randomElement($eventTypes),
            'event_payload' => [
                'schema_version' => '1.0',
                'timestamp' => $eventOccurredAt->format('c'),
                'data' => [
                    'sample_field' => $this->faker->word,
                ],
            ],
            'payload_schema_version' => '1.0',
            'actor_type' => $this->faker->randomElement($actorTypes),
            'actor_id' => $this->faker->optional()->numberBetween(1, 100),
            'actor_identifier' => $this->faker->optional()->word,
            'department_id_at_time' => $this->faker->optional()->numberBetween(1, 20),
            'system_component' => $this->faker->optional()->word,
            'client_ip' => $this->faker->optional()->ipv4,
            'client_user_agent' => $this->faker->optional()->userAgent,
            'preceding_event_id' => null,
            'integrity_hash' => hash('sha256', Str::random(40)),
            'event_occurred_at' => $eventOccurredAt,
            'event_recorded_at' => $eventRecordedAt,
            'processing_latency_ms' => $eventRecordedAt->getTimestamp() - $eventOccurredAt->getTimestamp(),
            'created_at' => now(),
            'metadata' => $this->faker->optional()->randomElement([
                null,
                ['source' => 'test_factory'],
            ]),
        ];
    }

    /**
     * Indicate that the event is a clinical event.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function clinical(): Factory
    {
        $clinicalEvents = VisitEvent::CLINICAL_EVENTS;

        return $this->state(function (array $attributes) use ($clinicalEvents) {
            return [
                'event_type' => $this->faker->randomElement($clinicalEvents),
                'actor_type' => 'staff',
                'actor_id' => $this->faker->numberBetween(1, 50),
            ];
        });
    }

    /**
     * Indicate that the event is a visit state event.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function visitState(): Factory
    {
        $stateEvents = VisitEvent::VISIT_STATE_EVENTS;

        return $this->state(function (array $attributes) use ($stateEvents) {
            return [
                'event_type' => $this->faker->randomElement($stateEvents),
            ];
        });
    }

    /**
     * Indicate that the event was created by system.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function system(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'actor_type' => 'system',
                'actor_id' => null,
                'actor_identifier' => 'background-job-' . $this->faker->word,
                'system_component' => $this->faker->randomElement(['scheduler', 'api', 'integration']),
            ];
        });
    }

    /**
     * Indicate that the event has high processing latency.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function highLatency(): Factory
    {
        return $this->state(function (array $attributes) {
            $eventOccurredAt = $this->faker->dateTimeBetween('-1 hour', 'now');
            $eventRecordedAt = clone $eventOccurredAt;
            $eventRecordedAt->modify('+' . rand(5000, 30000) . ' milliseconds'); // 5-30 seconds latency

            return [
                'event_occurred_at' => $eventOccurredAt,
                'event_recorded_at' => $eventRecordedAt,
                'processing_latency_ms' => $eventRecordedAt->getTimestamp() - $eventOccurredAt->getTimestamp(),
            ];
        });
    }

    /**
     * Indicate that the event has metadata.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function withMetadata(array $metadata = []): Factory
    {
        return $this->state(function (array $attributes) use ($metadata) {
            return [
                'metadata' => array_merge(
                    [
                        'source' => 'test',
                        'environment' => $this->faker->randomElement(['development', 'staging', 'production']),
                        'generated_by' => 'factory',
                    ],
                    $metadata
                ),
            ];
        });
    }
}