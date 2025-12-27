<?php

namespace Tests\Feature;

use App\Models\ClinicalEncounter;
use App\Models\Patient;
use App\Models\Staff;
use App\Models\Visit;
use App\Models\Facility;
use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use App\Models\User;

class ClinicalEncounterControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var Staff
     */
    protected $provider;

    /**
     * @var Facility
     */
    protected $facility;

    /**
     * @var Department
     */
    protected $department;

    /**
     * @var Patient
     */
    protected $patient;

    /**
     * @var Visit
     */
    protected $visit;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->facility = Facility::factory()->create();
        $this->department = Department::factory()->create(['facility_id' => $this->facility->id]);
        $this->provider = Staff::factory()->create([
            'department_id' => $this->department->id,
            'role' => 'provider'
        ]);
        $this->patient = Patient::factory()->create();
        $this->visit = Visit::factory()->create([
            'patient_id' => $this->patient->id,
            'facility_id' => $this->facility->id,
        ]);

        // Create authenticated user
        $this->user = User::factory()->create();
        $this->user->staff_id = $this->provider->id;
        $this->user->save();

        Sanctum::actingAs($this->user);
    }

    /**
     * Test index method returns clinical encounters.
     */
    public function test_can_list_clinical_encounters(): void
    {
        ClinicalEncounter::factory()->count(3)->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'primary_provider_staff_id' => $this->provider->id,
            'department_id' => $this->department->id,
        ]);

        $response = $this->getJson('/api/v1/clinical-encounters');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'uuid',
                        'encounter_type',
                        'documentation_status',
                        'patient',
                        'primary_provider',
                        'department',
                    ]
                ],
                'meta' => [
                    'current_page',
                    'total',
                ]
            ]);
    }

    /**
     * Test store method creates a clinical encounter.
     */
    public function test_can_create_clinical_encounter(): void
    {
        $data = [
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'encounter_type' => 'initial_consultation',
            'primary_provider_staff_id' => $this->provider->id,
            'department_id' => $this->department->id,
            'assessment_diagnosis_codes' => [
                ['code' => 'I10', 'description' => 'Essential hypertension', 'primary' => true]
            ],
            'clinical_impression' => $this->faker->paragraph(),
            'treatment_plan' => $this->faker->paragraph(),
        ];

        $response = $this->postJson('/api/v1/clinical-encounters', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'uuid',
                    'encounter_type',
                    'documentation_status',
                ]
            ]);

        $this->assertDatabaseHas('clinical_encounters', [
            'encounter_type' => 'initial_consultation',
            'facility_id' => $this->facility->id,
        ]);
    }

    /**
     * Test validation fails for invalid data.
     */
    public function test_validation_fails_for_invalid_data(): void
    {
        $data = [
            'encounter_type' => 'invalid_type', // Invalid enum value
            // Missing required fields
        ];

        $response = $this->postJson('/api/v1/clinical-encounters', $data);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors'
            ]);
    }

    /**
     * Test show method returns a clinical encounter.
     */
    public function test_can_retrieve_clinical_encounter(): void
    {
        $encounter = ClinicalEncounter::factory()->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'primary_provider_staff_id' => $this->provider->id,
            'department_id' => $this->department->id,
        ]);

        $response = $this->getJson("/api/v1/clinical-encounters/{$encounter->encounter_uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'uuid',
                    'encounter_type',
                    'patient',
                    'primary_provider',
                ]
            ]);
    }

    /**
     * Test update method updates a clinical encounter.
     */
    public function test_can_update_clinical_encounter(): void
    {
        $encounter = ClinicalEncounter::factory()->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'primary_provider_staff_id' => $this->provider->id,
            'department_id' => $this->department->id,
            'documentation_status' => 'in_progress',
        ]);

        $data = [
            'clinical_impression' => 'Updated clinical impression',
            'treatment_plan' => 'Updated treatment plan',
            'documentation_status' => 'completed',
        ];

        $response = $this->putJson("/api/v1/clinical-encounters/{$encounter->encounter_uuid}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Clinical encounter updated successfully.',
            ]);

        $this->assertDatabaseHas('clinical_encounters', [
            'id' => $encounter->id,
            'clinical_impression' => 'Updated clinical impression',
            'documentation_status' => 'completed',
        ]);
    }

    /**
     * Test cannot update signed encounter without amendment.
     */
    public function test_cannot_update_signed_encounter_without_amendment(): void
    {
        $encounter = ClinicalEncounter::factory()->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'primary_provider_staff_id' => $this->provider->id,
            'department_id' => $this->department->id,
            'documentation_status' => 'signed',
            'signed_at' => now(),
        ]);

        $data = [
            'clinical_impression' => 'Attempted update without amendment',
        ];

        $response = $this->putJson("/api/v1/clinical-encounters/{$encounter->encounter_uuid}", $data);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test delete method soft deletes a clinical encounter.
     */
    public function test_can_delete_clinical_encounter(): void
    {
        $encounter = ClinicalEncounter::factory()->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'primary_provider_staff_id' => $this->provider->id,
            'department_id' => $this->department->id,
            'documentation_status' => 'in_progress',
        ]);

        $response = $this->deleteJson("/api/v1/clinical-encounters/{$encounter->encounter_uuid}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Clinical encounter deleted successfully.',
            ]);

        $this->assertSoftDeleted('clinical_encounters', ['id' => $encounter->id]);
    }

    /**
     * Test cannot delete signed encounter.
     */
    public function test_cannot_delete_signed_encounter(): void
    {
        $encounter = ClinicalEncounter::factory()->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'primary_provider_staff_id' => $this->provider->id,
            'department_id' => $this->department->id,
            'documentation_status' => 'signed',
            'signed_at' => now(),
        ]);

        $response = $this->deleteJson("/api/v1/clinical-encounters/{$encounter->encounter_uuid}");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test can sign a completed encounter.
     */
    public function test_can_sign_completed_encounter(): void
    {
        $encounter = ClinicalEncounter::factory()->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'primary_provider_staff_id' => $this->provider->id,
            'department_id' => $this->department->id,
            'documentation_status' => 'completed',
            'subjective_assessment' => 'Test assessment',
            'objective_findings' => 'Test findings',
            'clinical_impression' => 'Test impression',
            'treatment_plan' => 'Test plan',
            'assessment_diagnosis_codes' => [['code' => 'I10', 'description' => 'Hypertension']],
        ]);

        $data = [
            'electronic_signature_hash' => hash('sha256', 'test_signature'),
        ];

        $response = $this->postJson("/api/v1/clinical-encounters/{$encounter->encounter_uuid}/sign", $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Clinical encounter signed successfully.',
            ]);

        $this->assertDatabaseHas('clinical_encounters', [
            'id' => $encounter->id,
            'documentation_status' => 'signed',
        ]);
    }

    /**
     * Test can create amendment for signed encounter.
     */
    public function test_can_create_amendment(): void
    {
        $original = ClinicalEncounter::factory()->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'primary_provider_staff_id' => $this->provider->id,
            'department_id' => $this->department->id,
            'documentation_status' => 'signed',
            'signed_at' => now(),
        ]);

        $data = [
            'amendment_data' => [
                'clinical_impression' => 'Amended clinical impression',
                'amendment_reason' => 'Correction of diagnosis',
            ],
            'amendment_reason' => 'Correction of diagnosis',
        ];

        $response = $this->postJson("/api/v1/clinical-encounters/{$original->encounter_uuid}/amend", $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);

        $this->assertDatabaseHas('clinical_encounters', [
            'amended_from_encounter_id' => $original->id,
            'amendment_reason' => 'Correction of diagnosis',
        ]);
    }

    /**
     * Test can get encounters requiring attention.
     */
    public function test_can_get_encounters_requiring_attention(): void
    {
        ClinicalEncounter::factory()->create([
            'facility_id' => $this->facility->id,
            'requires_immediate_attention' => true,
            'documentation_status' => 'in_progress',
        ]);

        $response = $this->getJson("/api/v1/clinical-encounters/requiring-attention?facility_id={$this->facility->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta'
            ]);
    }

    /**
     * Test unauthorized access is prevented.
     */
    public function test_unauthorized_access_is_prevented(): void
    {
        // Create user without permissions
        $unauthorizedUser = User::factory()->create();
        Sanctum::actingAs($unauthorizedUser);

        $response = $this->getJson('/api/v1/clinical-encounters');

        $response->assertStatus(403);
    }
}