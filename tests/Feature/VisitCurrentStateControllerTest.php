<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\VisitCurrentState;
use App\Models\User;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class VisitCurrentStateControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $facility;
    protected $visit;
    protected $patient;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->facility = Facility::factory()->create();
        $this->patient = Patient::factory()->create(['facility_id' => $this->facility->id]);
        $this->visit = Visit::factory()->create([
            'facility_id' => $this->facility->id,
            'patient_id' => $this->patient->id
        ]);
        
        $this->user = User::factory()->create([
            'facility_id' => $this->facility->id,
            'role' => 'admin'
        ]);
        
        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function it_can_list_visit_current_states()
    {
        VisitCurrentState::factory()->count(3)->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id
        ]);
        
        $response = $this->getJson('/api/visit-current-states');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'visit_id',
                        'facility_id',
                        'patient_id',
                        'current_phase',
                        'acuity_score'
                    ]
                ],
                'meta',
                'message'
            ])
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_can_create_a_visit_current_state()
    {
        $data = [
            'visit_id' => $this->visit->id,
            'facility_id' => $this->facility->id,
            'patient_id' => $this->patient->id,
            'current_phase' => 'registration',
            'acuity_score' => 3,
            'waiting_since' => now()->toDateTimeString()
        ];
        
        $response = $this->postJson('/api/visit-current-states', $data);
        
        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'visit_id',
                    'facility_id',
                    'current_phase'
                ],
                'message'
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'visit_id' => $this->visit->id,
                    'current_phase' => 'registration'
                ]
            ]);
        
        $this->assertDatabaseHas('visit_current_states', [
            'visit_id' => $this->visit->id,
            'current_phase' => 'registration'
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_creating()
    {
        $response = $this->postJson('/api/visit-current-states', []);
        
        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'visit_id',
                    'facility_id',
                    'patient_id',
                    'current_phase',
                    'acuity_score'
                ]
            ]);
    }

    /** @test */
    public function it_can_show_a_visit_current_state()
    {
        $visitCurrentState = VisitCurrentState::factory()->create([
            'visit_id' => $this->visit->id,
            'facility_id' => $this->facility->id,
            'patient_id' => $this->patient->id
        ]);
        
        $response = $this->getJson("/api/visit-current-states/{$visitCurrentState->id}");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'visit_id',
                    'facility_id',
                    'patient_id',
                    'current_phase',
                    'acuity_score',
                    'current_phase_label'
                ],
                'message'
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $visitCurrentState->id,
                    'visit_id' => $visitCurrentState->visit_id
                ]
            ]);
    }

    /** @test */
    public function it_returns_404_when_visit_current_state_not_found()
    {
        $response = $this->getJson('/api/visit-current-states/999');
        
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Visit current state not found.'
            ]);
    }

    /** @test */
    public function it_can_update_a_visit_current_state()
    {
        $visitCurrentState = VisitCurrentState::factory()->create([
            'visit_id' => $this->visit->id,
            'facility_id' => $this->facility->id,
            'patient_id' => $this->patient->id,
            'current_phase' => 'registration'
        ]);
        
        $data = [
            'current_phase' => 'triage',
            'acuity_score' => 4
        ];
        
        $response = $this->putJson("/api/visit-current-states/{$visitCurrentState->id}", $data);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'current_phase' => 'triage',
                    'acuity_score' => 4
                ]
            ]);
        
        $this->assertDatabaseHas('visit_current_states', [
            'id' => $visitCurrentState->id,
            'current_phase' => 'triage',
            'acuity_score' => 4
        ]);
    }

    /** @test */
    public function it_can_delete_a_visit_current_state()
    {
        $visitCurrentState = VisitCurrentState::factory()->create([
            'visit_id' => $this->visit->id,
            'facility_id' => $this->facility->id,
            'patient_id' => $this->patient->id
        ]);
        
        $response = $this->deleteJson("/api/visit-current-states/{$visitCurrentState->id}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Visit current state deleted successfully.'
            ]);
        
        $this->assertDatabaseMissing('visit_current_states', ['id' => $visitCurrentState->id]);
    }

    /** @test */
    public function it_can_get_visit_current_state_by_visit_id()
    {
        $visitCurrentState = VisitCurrentState::factory()->create([
            'visit_id' => $this->visit->id,
            'facility_id' => $this->facility->id,
            'patient_id' => $this->patient->id
        ]);
        
        $response = $this->getJson("/api/visit-current-states/visit/{$this->visit->id}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'visit_id' => $this->visit->id
                ]
            ]);
    }

    /** @test */
    public function it_can_get_visit_current_states_by_facility()
    {
        VisitCurrentState::factory()->count(3)->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id
        ]);
        
        $response = $this->getJson("/api/visit-current-states/facility/{$this->facility->id}");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [],
                'meta' => [
                    'facility_id',
                    'count'
                ],
                'message'
            ])
            ->assertJson([
                'success' => true,
                'meta' => [
                    'facility_id' => $this->facility->id,
                    'count' => 3
                ]
            ]);
    }

    /** @test */
    public function it_can_get_critical_alerts()
    {
        VisitCurrentState::factory()->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'has_critical_alerts' => true,
            'critical_alerts' => ['high_fever', 'low_bp']
        ]);
        
        $response = $this->getJson("/api/visit-current-states/facility/{$this->facility->id}/critical-alerts");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'meta' => [
                    'count' => 1
                ]
            ]);
    }

    /** @test */
    public function it_can_get_dashboard_stats()
    {
        VisitCurrentState::factory()->count(5)->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'has_critical_alerts' => false
        ]);
        
        VisitCurrentState::factory()->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'has_critical_alerts' => true
        ]);
        
        $response = $this->getJson("/api/visit-current-states/facility/{$this->facility->id}/dashboard-stats");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_visits',
                    'critical_alerts_count'
                ],
                'meta',
                'message'
            ])
            ->assertJson(['success' => true]);
    }
}