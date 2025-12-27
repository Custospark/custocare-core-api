<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Visit;
use App\Models\User;
use App\Models\Facility;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

class VisitControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var Facility
     */
    protected $facility;

    /**
     * @var Patient
     */
    protected $patient;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->facility = Facility::factory()->create();
        $this->patient = Patient::factory()->create();
        
        // Assign permissions to user
        $this->user->givePermissionTo([
            'view_visits',
            'create_visits',
            'update_visits',
            'delete_visits',
        ]);

        // Passport::actingAs($this->user);
    }

    /**
     * Test get all visits.
     */
    public function testGetAllVisits()
    {
        Visit::factory()->count(5)->create();

        $response = $this->getJson('/api/visits');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'visit_uuid',
                            'visit_type',
                            'acuity_score',
                            'status',
                        ]
                    ]
                ],
                'message'
            ]);
    }

    /**
     * Test create visit successfully.
     */
    public function testCreateVisitSuccessfully()
    {
        $visitData = [
            'facility_id' => $this->facility->id,
            'patient_id' => $this->patient->id,
            'visit_type' => 'outpatient',
            'arrived_at' => now()->toDateTimeString(),
            'chief_complaints' => ['Headache', 'Fever'],
            'acuity_score' => 3,
        ];

        $response = $this->postJson('/api/visits', $visitData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'visit_uuid',
                    'visit_type',
                    'acuity_score',
                ],
                'message'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Visit created successfully.',
            ]);
    }

    /**
     * Test create visit validation error.
     */
    public function testCreateVisitValidationError()
    {
        $invalidData = [
            'facility_id' => $this->facility->id,
            // Missing required fields
        ];

        $response = $this->postJson('/api/visits', $invalidData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors'
            ])
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed.',
            ]);
    }

    /**
     * Test get specific visit.
     */
    public function testGetVisit()
    {
        $visit = Visit::factory()->create();

        $response = $this->getJson("/api/visits/{$visit->visit_uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'visit_uuid',
                    'visit_type',
                    'acuity_score',
                    'status',
                ],
                'message'
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'visit_uuid' => $visit->visit_uuid,
                ]
            ]);
    }

    /**
     * Test get non-existent visit.
     */
    public function testGetNonExistentVisit()
    {
        $response = $this->getJson('/api/visits/non-existent-uuid');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Visit not found.',
            ]);
    }

    /**
     * Test update visit.
     */
    public function testUpdateVisit()
    {
        $visit = Visit::factory()->create(['status' => 'active']);
        $updateData = [
            'acuity_score' => 2,
            'patient_reported_history' => 'Updated history',
        ];

        $response = $this->putJson("/api/visits/{$visit->visit_uuid}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'message'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Visit updated successfully.',
                'data' => [
                    'acuity_score' => 2,
                ]
            ]);
    }

    /**
     * Test delete visit.
     */
    public function testDeleteVisit()
    {
        $visit = Visit::factory()->create(['status' => 'completed']);

        $response = $this->deleteJson("/api/visits/{$visit->visit_uuid}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Visit deleted successfully.',
            ]);

        $this->assertSoftDeleted('visits', ['id' => $visit->id]);
    }

    /**
     * Test update visit phase.
     */
    public function testUpdateVisitPhase()
    {
        $visit = Visit::factory()->create(['status' => 'active']);
        
        // User needs permission to update phase
        $this->user->givePermissionTo('update_visit_phase');

        $response = $this->postJson("/api/visits/{$visit->visit_uuid}/phase", [
            'phase' => 'consultation',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Visit phase updated successfully.',
            ]);
    }

    /**
     * Test discharge visit.
     */
    public function testDischargeVisit()
    {
        $visit = Visit::factory()->create(['status' => 'active']);
        
        // User needs permission and role to discharge
        $this->user->givePermissionTo('discharge_visits');
        $this->user->assignRole('clinical_staff');

        $response = $this->postJson("/api/visits/{$visit->visit_uuid}/discharge", [
            'discharge_disposition' => 'home',
            'discharge_instructions' => 'Rest and take medications as prescribed',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Visit discharged successfully.',
            ]);
    }

    /**
     * Test get visits by facility.
     */
    public function testGetVisitsByFacility()
    {
        $visit = Visit::factory()->create(['facility_id' => $this->facility->id]);

        $response = $this->getJson("/api/visits/facility/{$this->facility->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'message'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Facility visits retrieved successfully.',
            ]);
    }

    /**
     * Test get visits by patient.
     */
    public function testGetVisitsByPatient()
    {
        $visit = Visit::factory()->create(['patient_id' => $this->patient->id]);

        $response = $this->getJson("/api/visits/patient/{$this->patient->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'message'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Patient visits retrieved successfully.',
            ]);
    }

    /**
     * Test get long waiting visits.
     */
    public function testGetLongWaitingVisits()
    {
        $response = $this->getJson('/api/visits/reports/long-waiting?minutes_threshold=30');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'message'
            ]);
    }

    /**
     * Test get visit statistics.
     */
    public function testGetVisitStatistics()
    {
        $response = $this->getJson('/api/visits/reports/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'message'
            ]);
    }

    /**
     * Test unauthorized access.
     */
    public function testUnauthorizedAccess()
    {
        // Remove permissions
        $this->user->revokePermissionTo(['view_visits', 'create_visits', 'update_visits', 'delete_visits']);

        $visit = Visit::factory()->create();

        // Test unauthorized view
        $response = $this->getJson('/api/visits');
        $response->assertStatus(403);

        // Test unauthorized create
        $response = $this->postJson('/api/visits', []);
        $response->assertStatus(403);

        // Test unauthorized update
        $response = $this->putJson("/api/visits/{$visit->visit_uuid}", []);
        $response->assertStatus(403);

        // Test unauthorized delete
        $response = $this->deleteJson("/api/visits/{$visit->visit_uuid}");
        $response->assertStatus(403);
    }
}