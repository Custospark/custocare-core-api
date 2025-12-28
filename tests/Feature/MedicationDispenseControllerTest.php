<?php

namespace Tests\Feature;

use App\Models\MedicationDispense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MedicationDispenseControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $pharmacyTechnician;
    private User $pharmacist;
    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users with different roles
        $this->pharmacyTechnician = User::factory()->create([
            'email' => 'tech@example.com',
            'password' => Hash::make('password'),
        ]);
        $this->pharmacyTechnician->assignRole('pharmacy_technician');

        $this->pharmacist = User::factory()->create([
            'email' => 'pharmacist@example.com',
            'password' => Hash::make('password'),
        ]);
        $this->pharmacist->assignRole('pharmacist');

        $this->administrator = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);
        $this->administrator->assignRole('administrator');

        // Seed necessary related data
        $this->seed([
            // \Database\Seeders\FacilitySeeder::class,
            // \Database\Seeders\PatientSeeder::class,
            // \Database\Seeders\PrescriptionSeeder::class,
            // \Database\Seeders\StaffSeeder::class,
        ]);
    }

    /** @test */
    public function it_can_list_dispenses()
    {
        Sanctum::actingAs($this->pharmacyTechnician);

        MedicationDispense::factory()->count(5)->create([
            'dispensed_by_staff_id' => $this->pharmacyTechnician->id,
            'facility_id' => $this->pharmacyTechnician->facility_id,
        ]);

        $response = $this->getJson('/api/medication-dispenses');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'dispenses',
                    'pagination'
                ]
            ]);
    }

    /** @test */
    public function it_requires_authentication_to_list_dispenses()
    {
        $response = $this->getJson('/api/medication-dispenses');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_can_create_dispense()
    {
        Sanctum::actingAs($this->pharmacyTechnician);

        $data = [
            'facility_id' => $this->pharmacyTechnician->facility_id,
            'prescription_id' => 1,
            'patient_id' => 1,
            'prescription_details_snapshot' => [
                'medication_id' => 1,
                'medication_name' => 'Test Medication',
                'dosage' => '10mg',
                'frequency' => 'Once daily',
                'route' => 'oral'
            ],
            'quantity_dispensed' => 30,
            'quantity_unit' => 'tablets',
            'dispensed_by_staff_id' => $this->pharmacyTechnician->id,
            'safety_checks_performed' => [
                'allergy_check' => ['passed' => true],
                'interaction_check' => ['passed' => true]
            ],
            'all_safety_checks_passed' => true,
            'override_justification' => null
        ];

        $response = $this->postJson('/api/medication-dispenses', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'dispense_uuid',
                    'facility_id',
                    'patient_id',
                    'quantity_dispensed'
                ]
            ]);

        $this->assertDatabaseHas('medication_dispenses', [
            'facility_id' => $data['facility_id'],
            'patient_id' => $data['patient_id'],
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_dispense()
    {
        Sanctum::actingAs($this->pharmacyTechnician);

        $response = $this->postJson('/api/medication-dispenses', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors'
            ]);
    }

    /** @test */
    public function it_can_show_dispense()
    {
        Sanctum::actingAs($this->pharmacyTechnician);

        $dispense = MedicationDispense::factory()->create([
            'dispensed_by_staff_id' => $this->pharmacyTechnician->id,
            'facility_id' => $this->pharmacyTechnician->facility_id,
        ]);

        $response = $this->getJson("/api/medication-dispenses/{$dispense->dispense_uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'dispense_uuid',
                    'facility_id',
                    'patient_id'
                ]
            ]);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_dispense()
    {
        Sanctum::actingAs($this->pharmacyTechnician);

        $response = $this->getJson('/api/medication-dispenses/non-existent-uuid');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Dispense not found'
            ]);
    }

    /** @test */
    public function it_can_update_dispense()
    {
        Sanctum::actingAs($this->pharmacyTechnician);

        $dispense = MedicationDispense::factory()->create([
            'dispensed_by_staff_id' => $this->pharmacyTechnician->id,
            'facility_id' => $this->pharmacyTechnician->facility_id,
            'checked_by_staff_id' => null, // Not verified
        ]);

        $updateData = [
            'quantity_dispensed' => 60,
            'patient_education_topics' => 'Proper storage and administration'
        ];

        $response = $this->putJson(
            "/api/medication-dispenses/{$dispense->dispense_uuid}",
            $updateData
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Dispense updated successfully'
            ]);

        $this->assertDatabaseHas('medication_dispenses', [
            'id' => $dispense->id,
            'quantity_dispensed' => 60,
        ]);
    }

    /** @test */
    public function it_cannot_update_verified_dispense_without_override()
    {
        Sanctum::actingAs($this->pharmacyTechnician);

        $dispense = MedicationDispense::factory()->create([
            'dispensed_by_staff_id' => $this->pharmacyTechnician->id,
            'facility_id' => $this->pharmacyTechnician->facility_id,
            'checked_by_staff_id' => $this->pharmacist->id, // Verified
            'checked_at' => now(),
        ]);

        $updateData = ['quantity_dispensed' => 60];

        $response = $this->putJson(
            "/api/medication-dispenses/{$dispense->dispense_uuid}",
            $updateData
        );

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot update verified dispense without override reason'
            ]);
    }

    /** @test */
    public function it_can_delete_unverified_dispense()
    {
        Sanctum::actingAs($this->pharmacyTechnician);

        $dispense = MedicationDispense::factory()->create([
            'dispensed_by_staff_id' => $this->pharmacyTechnician->id,
            'facility_id' => $this->pharmacyTechnician->facility_id,
            'checked_by_staff_id' => null, // Not verified
            'picked_up_at' => null, // Not picked up
        ]);

        $response = $this->deleteJson("/api/medication-dispenses/{$dispense->dispense_uuid}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Dispense deleted successfully'
            ]);

        $this->assertDatabaseMissing('medication_dispenses', [
            'id' => $dispense->id
        ]);
    }

    /** @test */
    public function it_cannot_delete_verified_dispense()
    {
        Sanctum::actingAs($this->pharmacyTechnician);

        $dispense = MedicationDispense::factory()->create([
            'dispensed_by_staff_id' => $this->pharmacyTechnician->id,
            'facility_id' => $this->pharmacyTechnician->facility_id,
            'checked_by_staff_id' => $this->pharmacist->id, // Verified
            'checked_at' => now(),
        ]);

        $response = $this->deleteJson("/api/medication-dispenses/{$dispense->dispense_uuid}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot delete verified dispense'
            ]);
    }

    /** @test */
    public function it_can_verify_dispense()
    {
        Sanctum::actingAs($this->pharmacist);

        $dispense = MedicationDispense::factory()->create([
            'dispensed_by_staff_id' => $this->pharmacyTechnician->id, // Different from pharmacist
            'facility_id' => $this->pharmacist->facility_id,
            'checked_by_staff_id' => null, // Not verified
        ]);

        $verificationData = [
            'pharmacist_notes' => 'Verified successfully',
            'safety_confirmation' => true
        ];

        $response = $this->postJson(
            "/api/medication-dispenses/{$dispense->dispense_uuid}/verify",
            $verificationData
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Dispense verified successfully'
            ]);

        $this->assertDatabaseHas('medication_dispenses', [
            'id' => $dispense->id,
            'checked_by_staff_id' => $this->pharmacist->id,
            'pharmacist_notes' => 'Verified successfully'
        ]);
    }

    /** @test */
    public function it_cannot_verify_own_dispense()
    {
        Sanctum::actingAs($this->pharmacist);

        $dispense = MedicationDispense::factory()->create([
            'dispensed_by_staff_id' => $this->pharmacist->id, // Same as pharmacist
            'facility_id' => $this->pharmacist->facility_id,
            'checked_by_staff_id' => null,
        ]);

        $verificationData = ['safety_confirmation' => true];

        $response = $this->postJson(
            "/api/medication-dispenses/{$dispense->dispense_uuid}/verify",
            $verificationData
        );

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot verify your own dispense'
            ]);
    }

    /** @test */
    public function it_can_mark_dispense_as_picked_up()
    {
        Sanctum::actingAs($this->pharmacyTechnician);

        $dispense = MedicationDispense::factory()->create([
            'dispensed_by_staff_id' => $this->pharmacyTechnician->id,
            'facility_id' => $this->pharmacyTechnician->facility_id,
            'checked_by_staff_id' => $this->pharmacist->id, // Verified
            'checked_at' => now(),
            'picked_up_at' => null,
        ]);

        $pickupData = [
            'picked_up_by_name' => 'John Doe',
            'pickup_id_verified' => 'DL123456'
        ];

        $response = $this->postJson(
            "/api/medication-dispenses/{$dispense->dispense_uuid}/mark-picked-up",
            $pickupData
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Dispense marked as picked up successfully'
            ]);

        $this->assertDatabaseHas('medication_dispenses', [
            'id' => $dispense->id,
            'picked_up_at' => now(),
            'picked_up_by_name' => 'John Doe'
        ]);
    }

    /** @test */
    public function it_cannot_mark_unverified_dispense_as_picked_up()
    {
        Sanctum::actingAs($this->pharmacyTechnician);

        $dispense = MedicationDispense::factory()->create([
            'dispensed_by_staff_id' => $this->pharmacyTechnician->id,
            'facility_id' => $this->pharmacyTechnician->facility_id,
            'checked_by_staff_id' => null, // Not verified
            'picked_up_at' => null,
        ]);

        $pickupData = [
            'picked_up_by_name' => 'John Doe',
            'pickup_id_verified' => 'DL123456'
        ];

        $response = $this->postJson(
            "/api/medication-dispenses/{$dispense->dispense_uuid}/mark-picked-up",
            $pickupData
        );

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Dispense must be verified before marking as picked up'
            ]);
    }

    /** @test */
    public function it_can_update_dispense_status()
    {
        Sanctum::actingAs($this->pharmacist);

        $dispense = MedicationDispense::factory()->create([
            'dispensed_by_staff_id' => $this->pharmacyTechnician->id,
            'facility_id' => $this->pharmacist->facility_id,
            'picked_up_at' => now(), // Already picked up
            'status' => 'dispensed',
        ]);

        $statusData = [
            'status' => 'returned',
            'reason' => 'Patient allergic reaction'
        ];

        $response = $this->patchJson(
            "/api/medication-dispenses/{$dispense->dispense_uuid}/status",
            $statusData
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Dispense status updated successfully'
            ]);

        $this->assertDatabaseHas('medication_dispenses', [
            'id' => $dispense->id,
            'status' => 'returned'
        ]);
    }

    /** @test */
    public function it_can_get_dispenses_by_prescription()
    {
        Sanctum::actingAs($this->pharmacyTechnician);

        $prescriptionId = 1;
        
        MedicationDispense::factory()->count(3)->create([
            'prescription_id' => $prescriptionId,
            'dispensed_by_staff_id' => $this->pharmacyTechnician->id,
            'facility_id' => $this->pharmacyTechnician->facility_id,
        ]);

        $response = $this->getJson("/api/medication-dispenses/prescription/{$prescriptionId}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'dispense_uuid',
                        'prescription_id'
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_can_get_dispenses_by_patient()
    {
        Sanctum::actingAs($this->pharmacyTechnician);

        $patientId = 1;
        
        MedicationDispense::factory()->count(2)->create([
            'patient_id' => $patientId,
            'dispensed_by_staff_id' => $this->pharmacyTechnician->id,
            'facility_id' => $this->pharmacyTechnician->facility_id,
        ]);

        $response = $this->getJson("/api/medication-dispenses/patient/{$patientId}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'dispenses',
                    'pagination'
                ]
            ]);
    }

    /** @test */
    public function it_can_get_facility_statistics()
    {
        Sanctum::actingAs($this->pharmacist);
        $this->pharmacist->assignRole('pharmacy_manager'); // Need this role for statistics

        $facilityId = $this->pharmacist->facility_id;
        
        MedicationDispense::factory()->count(10)->create([
            'facility_id' => $facilityId,
            'dispensed_at' => now()->subDays(15),
        ]);

        $response = $this->getJson("/api/medication-dispenses/facility/{$facilityId}/statistics", [
            'start_date' => now()->subDays(30)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);
    }

    /** @test */
    public function it_enforces_role_based_access_control()
    {
        // Test that a user without proper role cannot access endpoints
        $regularUser = User::factory()->create();
        Sanctum::actingAs($regularUser);

        $response = $this->getJson('/api/medication-dispenses');

        $response->assertStatus(403);
    }

    /** @test */
    public function it_enforces_facility_based_access_control()
    {
        Sanctum::actingAs($this->pharmacyTechnician);

        // Create dispense in different facility
        $differentFacilityDispense = MedicationDispense::factory()->create([
            'facility_id' => 999, // Different from user's facility
        ]);

        $response = $this->getJson("/api/medication-dispenses/{$differentFacilityDispense->dispense_uuid}");

        $response->assertStatus(403);
    }
}