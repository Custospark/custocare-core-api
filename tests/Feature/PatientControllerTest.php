<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Patient;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;

class PatientControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private $adminUser;
    private $doctorUser;
    private $nurseUser;
    private $regularUser;
    private $patient;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users with different roles
        $this->adminUser = User::factory()->create();
        $this->doctorUser = User::factory()->create();
        $this->nurseUser = User::factory()->create();
        $this->regularUser = User::factory()->create();
        
        // Create staff records for medical personnel
        Staff::factory()->create(['user_id' => $this->doctorUser->id, 'role' => 'doctor']);
        Staff::factory()->create(['user_id' => $this->nurseUser->id, 'role' => 'nurse']);
        
        // Create a test patient
        $this->patient = Patient::factory()->create([
            'user_id' => $this->regularUser->id,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function admin_can_view_all_patients()
    {
        Sanctum::actingAs($this->adminUser);
        
        Patient::factory()->count(5)->create();
        
        $response = $this->getJson('/api/patients');
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         '*' => [
                             'patient_uuid',
                             'user_id',
                             'date_of_birth',
                             'biological_sex',
                             'status',
                         ]
                     ],
                     'meta' => [
                         'current_page',
                         'total',
                     ]
                 ]);
    }

    /** @test */
    public function doctor_can_view_all_patients()
    {
        Sanctum::actingAs($this->doctorUser);
        
        $response = $this->getJson('/api/patients');
        
        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
    }

    /** @test */
    public function regular_user_cannot_view_all_patients()
    {
        Sanctum::actingAs($this->regularUser);
        
        $response = $this->getJson('/api/patients');
        
        $response->assertStatus(403);
    }

    /** @test */
    public function user_can_view_own_patient_record()
    {
        Sanctum::actingAs($this->regularUser);
        
        $response = $this->getJson("/api/patients/{$this->patient->patient_uuid}");
        
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'patient_uuid' => $this->patient->patient_uuid,
                         'user_id' => $this->regularUser->id,
                     ]
                 ]);
    }

    /** @test */
    public function doctor_can_view_any_patient_record()
    {
        Sanctum::actingAs($this->doctorUser);
        
        $response = $this->getJson("/api/patients/{$this->patient->patient_uuid}");
        
        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
    }

    /** @test */
    public function admin_can_create_patient()
    {
        Sanctum::actingAs($this->adminUser);
        
        $newUser = User::factory()->create();
        $patientData = [
            'user_id' => $newUser->id,
            'medical_record_number_hash' => 'test_hash_' . $this->faker->uuid,
            'medical_record_number_encrypted' => 'encrypted_mrn_data',
            'date_of_birth' => '1985-05-15',
            'biological_sex' => 'female',
            'gender_identity' => 'female',
            'default_consent_level' => 'full',
            'payment_responsibility' => 'insurance',
            'preferred_communication_method' => 'email',
        ];
        
        $response = $this->postJson('/api/patients', $patientData);
        
        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Patient created successfully.',
                 ]);
        
        $this->assertDatabaseHas('patients', [
            'user_id' => $newUser->id,
            'biological_sex' => 'female',
        ]);
    }

    /** @test */
    public function cannot_create_patient_for_user_with_existing_record()
    {
        Sanctum::actingAs($this->adminUser);
        
        $patientData = [
            'user_id' => $this->regularUser->id, // User already has a patient
            'medical_record_number_hash' => 'new_hash',
            'medical_record_number_encrypted' => 'encrypted_mrn',
            'date_of_birth' => '1990-01-01',
            'biological_sex' => 'male',
            'default_consent_level' => 'full',
            'payment_responsibility' => 'insurance',
            'preferred_communication_method' => 'email',
        ];
        
        $response = $this->postJson('/api/patients', $patientData);
        
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['user_id']);
    }

    /** @test */
    public function admin_can_update_patient()
    {
        Sanctum::actingAs($this->adminUser);
        
        $updateData = [
            'blood_type' => 'O+',
            'ethnicity' => 'Test Ethnicity',
        ];
        
        $response = $this->putJson("/api/patients/{$this->patient->patient_uuid}", $updateData);
        
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Patient updated successfully.',
                 ]);
        
        $this->assertDatabaseHas('patients', [
            'patient_uuid' => $this->patient->patient_uuid,
            'blood_type' => 'O+',
        ]);
    }

    /** @test */
    public function user_can_update_own_basic_information()
    {
        Sanctum::actingAs($this->regularUser);
        
        $updateData = [
            'preferred_language' => 'es',
            'preferred_communication_method' => 'sms',
        ];
        
        $response = $this->putJson("/api/patients/{$this->patient->patient_uuid}", $updateData);
        
        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
    }

    /** @test */
    public function user_cannot_update_medical_information()
    {
        Sanctum::actingAs($this->regularUser);
        
        $updateData = [
            'chronic_conditions' => ['diabetes'],
            'known_allergies' => ['penicillin'],
        ];
        
        $response = $this->putJson("/api/patients/{$this->patient->patient_uuid}", $updateData);
        
        // This might return 403 (forbidden) or 422 (validation error)
        // depending on how permissions are implemented
        $response->assertStatus(403);
    }

    /** @test */
    public function doctor_can_update_medical_information()
    {
        Sanctum::actingAs($this->doctorUser);
        
        $updateData = [
            'chronic_conditions' => ['diabetes', 'hypertension'],
            'known_allergies' => ['penicillin'],
            'acuity_baseline' => 3,
        ];
        
        $response = $this->putJson("/api/patients/{$this->patient->patient_uuid}", $updateData);
        
        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
    }

    /** @test */
    public function admin_can_delete_patient()
    {
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->deleteJson("/api/patients/{$this->patient->patient_uuid}");
        
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Patient deleted successfully.',
                 ]);
        
        $this->assertSoftDeleted('patients', [
            'patient_uuid' => $this->patient->patient_uuid,
        ]);
    }

    /** @test */
    public function cannot_delete_deceased_patient()
    {
        $deceasedPatient = Patient::factory()->create([
            'status' => 'deceased',
            'deceased_at' => now(),
        ]);
        
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->deleteJson("/api/patients/{$deceasedPatient->patient_uuid}");
        
        $response->assertStatus(400);
    }

    /** @test */
    public function admin_can_update_patient_status()
    {
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->postJson("/api/patients/{$this->patient->patient_uuid}/status", [
            'status' => 'inactive',
        ]);
        
        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
        
        $this->assertDatabaseHas('patients', [
            'patient_uuid' => $this->patient->patient_uuid,
            'status' => 'inactive',
        ]);
    }

    /** @test */
    public function doctor_can_mark_patient_as_deceased()
    {
        Sanctum::actingAs($this->doctorUser);
        
        $response = $this->postJson("/api/patients/{$this->patient->patient_uuid}/status", [
            'status' => 'deceased',
        ]);
        
        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
        
        $this->assertDatabaseHas('patients', [
            'patient_uuid' => $this->patient->patient_uuid,
            'status' => 'deceased',
        ]);
    }

    /** @test */
    public function nurse_cannot_mark_patient_as_deceased()
    {
        Sanctum::actingAs($this->nurseUser);
        
        $response = $this->postJson("/api/patients/{$this->patient->patient_uuid}/status", [
            'status' => 'deceased',
        ]);
        
        $response->assertStatus(403);
    }

    /** @test */
    public function can_search_patients_by_criteria()
    {
        Sanctum::actingAs($this->adminUser);
        
        // Create test patients with specific criteria
        Patient::factory()->create([
            'biological_sex' => 'female',
            'blood_type' => 'A+',
        ]);
        
        Patient::factory()->create([
            'biological_sex' => 'male',
            'blood_type' => 'O-',
        ]);
        
        $response = $this->getJson('/api/patients/search?biological_sex=female&blood_type=A+');
        
        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonStructure([
                     'data',
                     'meta' => ['total', 'criteria'],
                 ]);
    }

    /** @test */
    public function can_get_patient_statistics()
    {
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->getJson('/api/patients/statistics');
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'total_patients',
                         'active_patients',
                         'deceased_patients',
                         'patients_requiring_isolation',
                         'blood_type_distribution',
                         'consent_level_distribution',
                         'average_acuity',
                     ]
                 ]);
    }

    /** @test */
    public function can_export_patient_data_with_permission()
    {
        $patientWithConsent = Patient::factory()->create([
            'data_sharing_allowed' => true,
            'default_consent_level' => 'full',
        ]);
        
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->getJson("/api/patients/{$patientWithConsent->patient_uuid}/export");
        
        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonStructure([
                     'data',
                     'meta' => ['exported_at', 'consent_level', 'data_sharing_allowed'],
                 ]);
    }

    /** @test */
    public function cannot_export_patient_data_without_sharing_permission()
    {
        $patientWithoutConsent = Patient::factory()->create([
            'data_sharing_allowed' => false,
        ]);
        
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->getJson("/api/patients/{$patientWithoutConsent->patient_uuid}/export");
        
        $response->assertStatus(400);
    }

    /** @test */
    public function can_get_patients_by_blood_type()
    {
        Sanctum::actingAs($this->adminUser);
        
        Patient::factory()->count(3)->create(['blood_type' => 'O+']);
        Patient::factory()->create(['blood_type' => 'A+']);
        
        $response = $this->getJson('/api/patients/blood-type/O+');
        
        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonPath('meta.blood_type', 'O+');
    }

    /** @test */
    public function can_get_patients_requiring_isolation()
    {
        Sanctum::actingAs($this->adminUser);
        
        Patient::factory()->count(2)->create(['requires_isolation' => true]);
        Patient::factory()->create(['requires_isolation' => false]);
        
        $response = $this->getJson('/api/patients/requiring-isolation');
        
        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonPath('meta.total', 2);
    }

    /** @test */
    public function can_restore_soft_deleted_patient()
    {
        Sanctum::actingAs($this->adminUser);
        
        $patient = Patient::factory()->create();
        $patient->delete();
        
        $response = $this->postJson("/api/patients/{$patient->patient_uuid}/restore");
        
        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
        
        $this->assertDatabaseHas('patients', [
            'patient_uuid' => $patient->patient_uuid,
            'deleted_at' => null,
        ]);
    }
}