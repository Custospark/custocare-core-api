<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\AiAssessment;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\ClinicalEncounter;
use App\Models\Visit;
use App\Enums\ModelType;
use App\Enums\HumanReviewStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class AiAssessmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $clinicianUser;
    private User $aiSpecialistUser;
    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->facility = Facility::factory()->create();
        
        $this->adminUser = User::factory()->create([
            'facility_id' => $this->facility->id,
        ]);
        $this->adminUser->assignRole('admin');
        
        $this->clinicianUser = User::factory()->create([
            'facility_id' => $this->facility->id,
        ]);
        $this->clinicianUser->assignRole('clinician');
        
        $this->aiSpecialistUser = User::factory()->create([
            'facility_id' => $this->facility->id,
        ]);
        $this->aiSpecialistUser->assignRole('ai_specialist');
    }

    /** @test */
    public function it_can_list_ai_assessments_for_authenticated_user()
    {
        Sanctum::actingAs($this->adminUser);
        
        AiAssessment::factory()->count(3)->create([
            'facility_id' => $this->facility->id,
        ]);
        
        $response = $this->getJson('/api/ai-assessments');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'assessment_uuid',
                            'ai_model' => [
                                'name',
                                'version',
                                'type'
                            ]
                        ]
                    ]
                ],
                'message'
            ]);
    }

    /** @test */
    public function it_cannot_list_ai_assessments_for_unauthenticated_user()
    {
        $response = $this->getJson('/api/ai-assessments');
        
        $response->assertStatus(401);
    }

    /** @test */
    public function it_can_create_ai_assessment_with_valid_data()
    {
        Sanctum::actingAs($this->aiSpecialistUser);
        
        $patient = Patient::factory()->create(['facility_id' => $this->facility->id]);
        $encounter = ClinicalEncounter::factory()->create(['facility_id' => $this->facility->id]);
        $visit = Visit::factory()->create(['facility_id' => $this->facility->id]);
        
        $data = [
            'facility_id' => $this->facility->id,
            'clinical_encounter_id' => $encounter->id,
            'visit_id' => $visit->id,
            'patient_id' => $patient->id,
            'ai_model_name' => 'Diagnostic Assistant Pro',
            'ai_model_version' => '2.1.0',
            'model_type' => ModelType::DIAGNOSTIC_ASSISTANT->value,
            'input_features' => [
                'patient_age' => 45,
                'patient_gender' => 'male',
                'timestamp' => now()->toDateTimeString(),
                'symptoms' => ['fever', 'cough', 'fatigue'],
                'duration' => 5,
                'severity' => 'moderate'
            ],
            'output_predictions' => ['diagnosis' => 'influenza'],
            'output_confidence_scores' => ['diagnosis' => 0.87],
            'recommendations' => ['rest', 'hydration', 'antiviral medication'],
            'assessed_at' => now()->toDateTimeString(),
        ];
        
        $response = $this->postJson('/api/ai-assessments', $data);
        
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'AI assessment created successfully.'
            ])
            ->assertJsonStructure([
                'data' => [
                    'assessment_uuid',
                    'ai_model',
                    'input_data',
                    'output'
                ]
            ]);
        
        $this->assertDatabaseHas('ai_assessments', [
            'ai_model_name' => 'Diagnostic Assistant Pro',
            'patient_id' => $patient->id,
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_assessment()
    {
        Sanctum::actingAs($this->aiSpecialistUser);
        
        $data = [
            'ai_model_name' => 'Test Model',
            // Missing required fields
        ];
        
        $response = $this->postJson('/api/ai-assessments', $data);
        
        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors'
            ]);
    }

    /** @test */
    public function it_can_retrieve_specific_ai_assessment()
    {
        Sanctum::actingAs($this->adminUser);
        
        $assessment = AiAssessment::factory()->create([
            'facility_id' => $this->facility->id,
        ]);
        
        $response = $this->getJson("/api/ai-assessments/{$assessment->assessment_uuid}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'assessment_uuid' => $assessment->assessment_uuid
                ]
            ]);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_assessment()
    {
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->getJson('/api/ai-assessments/nonexistent-uuid');
        
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error_code' => 'ASSESSMENT_NOT_FOUND'
            ]);
    }

    /** @test */
    public function it_can_update_ai_assessment()
    {
        Sanctum::actingAs($this->adminUser);
        
        $assessment = AiAssessment::factory()->create([
            'facility_id' => $this->facility->id,
            'human_review_status' => HumanReviewStatus::PENDING_REVIEW
        ]);
        
        $updateData = [
            'review_notes' => 'Updated review notes',
            'human_review_status' => HumanReviewStatus::ACCEPTED->value
        ];
        
        $response = $this->putJson("/api/ai-assessments/{$assessment->assessment_uuid}", $updateData);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'AI assessment updated successfully.'
            ]);
        
        $this->assertDatabaseHas('ai_assessments', [
            'id' => $assessment->id,
            'review_notes' => 'Updated review notes',
            'human_review_status' => HumanReviewStatus::ACCEPTED->value
        ]);
    }

    /** @test */
    public function it_cannot_update_assessment_without_permission()
    {
        Sanctum::actingAs($this->clinicianUser); // Clinician doesn't have update permission by default
        
        $assessment = AiAssessment::factory()->create([
            'facility_id' => $this->facility->id,
        ]);
        
        $updateData = ['review_notes' => 'Unauthorized update'];
        
        $response = $this->putJson("/api/ai-assessments/{$assessment->assessment_uuid}", $updateData);
        
        $response->assertStatus(403);
    }

    /** @test */
    public function it_can_delete_ai_assessment()
    {
        Sanctum::actingAs($this->adminUser);
        
        $assessment = AiAssessment::factory()->create([
            'facility_id' => $this->facility->id,
            'clinical_outcome_recorded' => false
        ]);
        
        $response = $this->deleteJson("/api/ai-assessments/{$assessment->assessment_uuid}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'AI assessment deleted successfully.'
            ]);
        
        $this->assertSoftDeleted('ai_assessments', ['id' => $assessment->id]);
    }

    /** @test */
    public function it_can_review_ai_assessment()
    {
        Sanctum::actingAs($this->clinicianUser);
        $this->clinicianUser->givePermissionTo('review ai_assessments');
        
        $assessment = AiAssessment::factory()->create([
            'facility_id' => $this->facility->id,
            'human_review_status' => HumanReviewStatus::PENDING_REVIEW
        ]);
        
        $reviewData = [
            'status' => HumanReviewStatus::ACCEPTED->value,
            'review_notes' => 'Assessment looks accurate.'
        ];
        
        $response = $this->postJson("/api/ai-assessments/{$assessment->assessment_uuid}/review", $reviewData);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'AI assessment reviewed successfully.'
            ]);
    }

    /** @test */
    public function it_can_record_clinical_outcome()
    {
        Sanctum::actingAs($this->clinicianUser);
        
        $assessment = AiAssessment::factory()->create([
            'facility_id' => $this->facility->id,
            'clinical_outcome_recorded' => false
        ]);
        
        $outcomeData = [
            'outcome' => ['actual_diagnosis' => 'confirmed_influenza'],
            'accuracy' => 0.95
        ];
        
        $response = $this->postJson("/api/ai-assessments/{$assessment->assessment_uuid}/record-outcome", $outcomeData);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Clinical outcome recorded successfully.'
            ]);
    }

    /** @test */
    public function it_can_flag_adverse_event()
    {
        Sanctum::actingAs($this->clinicianUser);
        
        $assessment = AiAssessment::factory()->create([
            'facility_id' => $this->facility->id,
            'adverse_event_flagged' => false
        ]);
        
        $eventData = [
            'description' => 'Patient experienced unexpected side effects',
            'severity' => 'medium'
        ];
        
        $response = $this->postJson("/api/ai-assessments/{$assessment->assessment_uuid}/flag-adverse-event", $eventData);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Adverse event flagged successfully.'
            ]);
    }

    /** @test */
    public function it_can_get_assessments_by_patient()
    {
        Sanctum::actingAs($this->adminUser);
        
        $patient = Patient::factory()->create(['facility_id' => $this->facility->id]);
        AiAssessment::factory()->count(2)->create([
            'facility_id' => $this->facility->id,
            'patient_id' => $patient->id
        ]);
        
        $response = $this->getJson("/api/ai-assessments/patient/{$patient->id}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Patient AI assessments retrieved successfully.'
            ])
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function it_can_get_model_statistics()
    {
        Sanctum::actingAs($this->adminUser);
        $this->adminUser->givePermissionTo('view ai_statistics');
        
        AiAssessment::factory()->count(5)->create([
            'facility_id' => $this->facility->id,
            'model_type' => ModelType::DIAGNOSTIC_ASSISTANT
        ]);
        
        $response = $this->getJson("/api/ai-assessments/statistics?facility_id={$this->facility->id}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Model statistics retrieved successfully.'
            ])
            ->assertJsonStructure([
                'data' => [
                    'total_assessments',
                    'by_model_type',
                    'review_stats'
                ]
            ]);
    }
}