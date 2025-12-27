<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\VisitActor;
use App\Models\Visit;
use App\Models\Staff;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class VisitActorControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var VisitActor
     */
    protected $visitActor;

    /**
     * @var array
     */
    protected $headers = [
        'Accept' => 'application/json',
    ];

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // Create related models
        $facility = Facility::factory()->create();
        $visit = Visit::factory()->create(['facility_id' => $facility->id]);
        $staff = Staff::factory()->create(['facility_id' => $facility->id]);

        // Create test visit actor
        $this->visitActor = VisitActor::factory()->create([
            'facility_id' => $facility->id,
            'visit_id' => $visit->id,
            'staff_id' => $staff->id,
            'participation_type' => 'primary_provider',
            'participation_started_at' => now()->subHour(),
        ]);

        // Authenticate user
        Sanctum::actingAs($this->user, ['*']);
    }

    /**
     * Test getting all visit actors.
     */
    public function testGetAllVisitActors(): void
    {
        $response = $this->withHeaders($this->headers)
            ->getJson('/api/v1/visit-actors');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'facility_id',
                        'visit_id',
                        'staff_id',
                        'role_at_time',
                        'participation_type',
                        'participation_started_at',
                        'created_at',
                        'updated_at',
                    ]
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ]
            ]);
    }

    /**
     * Test getting a specific visit actor.
     */
    public function testGetVisitActor(): void
    {
        $response = $this->withHeaders($this->headers)
            ->getJson("/api/v1/visit-actors/{$this->visitActor->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->visitActor->id,
                    'facility_id' => $this->visitActor->facility_id,
                    'participation_type' => $this->visitActor->participation_type,
                ]
            ]);
    }

    /**
     * Test getting non-existent visit actor.
     */
    public function testGetNonExistentVisitActor(): void
    {
        $response = $this->withHeaders($this->headers)
            ->getJson('/api/v1/visit-actors/9999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Visit actor not found.',
            ]);
    }

    /**
     * Test creating a new visit actor.
     */
    public function testCreateVisitActor(): void
    {
        $facility = Facility::factory()->create();
        $visit = Visit::factory()->create(['facility_id' => $facility->id]);
        $staff = Staff::factory()->create(['facility_id' => $facility->id]);

        $data = [
            'facility_id' => $facility->id,
            'visit_id' => $visit->id,
            'staff_id' => $staff->id,
            'role_at_time' => 'Senior Physician',
            'participation_type' => 'consulting_provider',
            'participation_started_at' => now()->toDateTimeString(),
            'is_billable_provider' => true,
            'provider_charge_amount' => 250.00,
        ];

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v1/visit-actors', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Visit actor created successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'facility_id',
                    'visit_id',
                    'staff_id',
                    'role_at_time',
                    'participation_type',
                    'is_billable',
                    'is_active',
                ]
            ]);
    }

    /**
     * Test creating visit actor with invalid data.
     */
    public function testCreateVisitActorInvalidData(): void
    {
        $data = [
            'facility_id' => 'invalid',
            'participation_type' => 'invalid_type',
        ];

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v1/visit-actors', $data);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed',
            ])
            ->assertJsonStructure([
                'errors' => [
                    'facility_id',
                    'visit_id',
                    'staff_id',
                    'role_at_time',
                    'participation_type',
                    'participation_started_at',
                ]
            ]);
    }

    /**
     * Test updating a visit actor.
     */
    public function testUpdateVisitActor(): void
    {
        $data = [
            'role_at_time' => 'Updated Role',
            'is_teaching_case' => true,
        ];

        $response = $this->withHeaders($this->headers)
            ->putJson("/api/v1/visit-actors/{$this->visitActor->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Visit actor updated successfully',
                'data' => [
                    'role_at_time' => 'Updated Role',
                    'is_teaching_case' => true,
                ]
            ]);
    }

    /**
     * Test updating non-existent visit actor.
     */
    public function testUpdateNonExistentVisitActor(): void
    {
        $data = ['role_at_time' => 'Updated Role'];

        $response = $this->withHeaders($this->headers)
            ->putJson('/api/v1/visit-actors/9999', $data);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Visit actor not found',
            ]);
    }

    /**
     * Test deleting a visit actor.
     */
    public function testDeleteVisitActor(): void
    {
        // Create a non-billable visit actor for deletion
        $facility = Facility::factory()->create();
        $visit = Visit::factory()->create(['facility_id' => $facility->id]);
        $staff = Staff::factory()->create(['facility_id' => $facility->id]);
        
        $visitActor = VisitActor::factory()->create([
            'facility_id' => $facility->id,
            'visit_id' => $visit->id,
            'staff_id' => $staff->id,
            'is_billable_provider' => false,
            'provider_charge_amount' => null,
        ]);

        $response = $this->withHeaders($this->headers)
            ->deleteJson("/api/v1/visit-actors/{$visitActor->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Visit actor deleted successfully',
            ]);

        // Verify deletion
        $this->assertDatabaseMissing('visit_actors', ['id' => $visitActor->id]);
    }

    /**
     * Test ending participation for a visit actor.
     */
    public function testEndParticipation(): void
    {
        $data = [
            'participation_ended_at' => now()->toDateTimeString(),
            'metadata' => ['notes' => 'Participation completed'],
        ];

        $response = $this->withHeaders($this->headers)
            ->postJson("/api/v1/visit-actors/{$this->visitActor->id}/end-participation", $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Participation ended successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'participation_ended_at',
                    'time_involvement_minutes',
                ]
            ]);
    }

    /**
     * Test getting visit actors by visit ID.
     */
    public function testGetVisitActorsByVisit(): void
    {
        $response = $this->withHeaders($this->headers)
            ->getJson("/api/v1/visit-actors/visit/{$this->visitActor->visit_id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'meta' => [
                    'visit_id' => $this->visitActor->visit_id,
                ]
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'visit_id',
                        'staff_id',
                        'participation_type',
                    ]
                ]
            ]);
    }

    /**
     * Test getting active participations for staff.
     */
    public function testGetActiveStaffParticipations(): void
    {
        $response = $this->withHeaders($this->headers)
            ->getJson("/api/v1/visit-actors/staff/{$this->visitActor->staff_id}/active");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'meta' => [
                    'staff_id' => $this->visitActor->staff_id,
                    'is_active' => true,
                ]
            ]);
    }

    /**
     * Test unauthorized access.
     */
    public function testUnauthorizedAccess(): void
    {
        // Create a user without permissions
        $unauthorizedUser = User::factory()->create();
        Sanctum::actingAs($unauthorizedUser);

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v1/visit-actors', []);

        // This will depend on your authorization setup
        $response->assertStatus(403); // or 401
    }
}