<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\VisitEvent;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class VisitEventControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $adminUser;
    protected $visit;
    protected $visitEvent;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->user = User::factory()->create([
            'role' => 'clinician',
            'facility_id' => 1,
        ]);

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
            'facility_id' => 1,
        ]);

        // Create a visit
        $this->visit = Visit::factory()->create([
            'facility_id' => 1,
            'patient_id' => 1,
        ]);

        // Create a visit event
        $this->visitEvent = VisitEvent::factory()->create([
            'facility_id' => 1,
            'visit_id' => $this->visit->id,
            'event_type' => 'patient_arrived',
            'event_uuid' => Str::uuid()->toString(),
        ]);
    }

    /** @test */
    public function authenticated_user_can_list_visit_events()
    {
        VisitEvent::factory()->count(5)->create([
            'facility_id' => 1,
            'visit_id' => $this->visit->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/visit-events');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'event_uuid',
                        'facility_id',
                        'visit_id',
                        'event_type',
                    ]
                ],
                'meta' => [
                    'pagination',
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Events retrieved successfully',
            ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_list_visit_events()
    {
        $response = $this->getJson('/api/v1/visit-events');

        $response->assertStatus(401);
    }

    /** @test */
    public function user_can_view_specific_visit_event()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/visit-events/{$this->visitEvent->event_uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'event_uuid',
                    'facility_id',
                    'visit_id',
                    'event_type',
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Visit event retrieved successfully',
                'data' => [
                    'event_uuid' => $this->visitEvent->event_uuid,
                ]
            ]);
    }

    /** @test */
    public function user_cannot_view_non_existent_visit_event()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/visit-events/non-existent-uuid');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Event not found',
            ]);
    }

    /** @test */
    public function user_can_create_visit_event_with_valid_data()
    {
        $eventData = [
            'facility_id' => 1,
            'visit_id' => $this->visit->id,
            'event_type' => 'patient_registered',
            'event_payload' => [
                'schema_version' => '1.0',
                'registration_data' => [
                    'patient_id' => 1,
                    'registered_by' => $this->user->id,
                ]
            ],
            'actor_type' => 'staff',
            'actor_id' => $this->user->id,
            'event_occurred_at' => now()->subMinutes(10)->toISOString(),
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/visit-events', $eventData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'event_uuid',
                    'event_type',
                ],
                'event_metadata'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Visit event recorded successfully',
            ]);

        $this->assertDatabaseHas('visit_events', [
            'visit_id' => $this->visit->id,
            'event_type' => 'patient_registered',
        ]);
    }

    /** @test */
    public function user_cannot_create_visit_event_with_invalid_data()
    {
        $invalidEventData = [
            'facility_id' => 'invalid',
            'event_type' => 'invalid_event_type',
            // Missing required fields
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/visit-events', $invalidEventData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors'
            ])
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed',
            ]);
    }

    /** @test */
    public function admin_can_update_visit_event_metadata()
    {
        $updateData = [
            'metadata' => [
                'updated_by' => $this->adminUser->id,
                'update_reason' => 'Test update',
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/visit-events/{$this->visitEvent->event_uuid}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Visit event metadata updated successfully',
            ]);

        $this->assertDatabaseHas('visit_events', [
            'id' => $this->visitEvent->id,
            'metadata->updated_by' => $this->adminUser->id,
        ]);
    }

    /** @test */
    public function non_admin_cannot_update_visit_event()
    {
        $updateData = [
            'metadata' => ['test' => 'data'],
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/visit-events/{$this->visitEvent->event_uuid}", $updateData);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You are not authorized to update this visit event',
            ]);
    }

    /** @test */
    public function user_cannot_delete_visit_event()
    {
        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/visit-events/{$this->visitEvent->event_uuid}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Visit events are immutable and cannot be deleted',
            ]);

        $this->assertDatabaseHas('visit_events', ['id' => $this->visitEvent->id]);
    }

    /** @test */
    public function user_can_get_clinical_timeline_for_visit()
    {
        // Create clinical events
        VisitEvent::factory()->create([
            'visit_id' => $this->visit->id,
            'event_type' => 'triage_started',
            'facility_id' => 1,
        ]);

        VisitEvent::factory()->create([
            'visit_id' => $this->visit->id,
            'event_type' => 'vitals_recorded',
            'facility_id' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/visits/{$this->visit->id}/clinical-timeline");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Clinical timeline retrieved successfully',
            ]);
    }

    /** @test */
    public function user_can_verify_event_chain_integrity()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/visits/{$this->visit->id}/verify-chain");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'verified',
                    'total_events',
                    'failed_events',
                    'failed_count',
                ]
            ]);
    }

    /** @test */
    public function admin_can_recalculate_integrity_hash()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/visit-events/{$this->visitEvent->id}/recalculate-hash");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);
    }

    /** @test */
    public function non_admin_cannot_recalculate_integrity_hash()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/visit-events/{$this->visitEvent->id}/recalculate-hash");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You are not authorized to recalculate integrity hashes',
            ]);
    }

    /** @test */
    public function user_can_get_facility_event_report()
    {
        $from = now()->subDays(7)->toDateString();
        $to = now()->toDateString();

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/facilities/1/event-report?from={$from}&to={$to}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);
    }

    /** @test */
    public function user_can_get_event_statistics()
    {
        $from = now()->subDays(7)->toDateString();
        $to = now()->toDateString();

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/facilities/1/event-statistics?from={$from}&to={$to}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);
    }

    /** @test */
//     public function user_from_different_facility_cannot_access_events()
//     {
//         $differentFacilityUser = User::factory()->create([
//             'role' => 'clinician',
//             'facility_id' => 2, // Different facility
//         ]);

//         $response = $this->actingAs($differentFacilityUser)
//             ->getJson("/api/v1/visit-events/{$this->visitEvent->event_uuid}");

//         $response->assertStatus(403)
//             ->assertJson([
//                 'success' => false,
//                 'message' => 'You are not authorized to view this visit event',
//             ]);
//     }
// 
}