<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Patient;
use App\Models\Staff;
use App\Models\InventoryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;

class PrescriptionControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $patient;
    protected $provider;
    protected $inventoryItem;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user with appropriate roles/permissions
        $this->user = User::factory()->create([
            'email' => 'provider@test.com',
            'facility_id' => 1,
        ]);
        $this->user->assignRole('provider');
        $this->user->givePermissionTo(['create prescriptions', 'view prescriptions', 'edit prescriptions']);
        
        // Create test patient
        $this->patient = Patient::factory()->create(['facility_id' => 1]);
        
        // Create test provider (staff)
        $this->provider = Staff::factory()->create(['user_id' => $this->user->id]);
        
        // Create test inventory item
        $this->inventoryItem = InventoryItem::factory()->create();
        
        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function it_can_list_prescriptions()
    {
        Prescription::factory()->count(3)->create([
            'prescribing_provider_staff_id' => $this->provider->id,
            'facility_id' => 1,
        ]);
        
        $response = $this->getJson('/api/v1/prescriptions');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'prescription_uuid',
                        'medication_name',
                        'dosage_strength',
                        'status',
                        'dispense_status',
                    ]
                ],
                'meta' => [
                    'current_page',
                    'total_pages',
                    'total_items',
                ]
            ]);
    }

    /** @test */
    public function it_can_create_a_prescription()
    {
        $prescriptionData = [
            'patient_id' => $this->patient->id,
            'prescribing_provider_staff_id' => $this->provider->id,
            'inventory_item_id' => $this->inventoryItem->id,
            'medication_name' => 'Amoxicillin 500mg',
            'generic_name' => 'Amoxicillin',
            'dosage_strength' => '500mg',
            'dosage_form' => 'Capsule',
            'route' => 'Oral',
            'sig_instructions' => 'Take one capsule every 8 hours',
            'quantity_prescribed' => 21,
            'quantity_unit' => 'capsules',
            'valid_from' => now()->format('Y-m-d'),
            'valid_to' => now()->addDays(10)->format('Y-m-d'),
            'refills_allowed' => 0,
            'refills_remaining' => 0,
            'is_electronic_prescription' => true,
            'status' => 'active',
            'dispense_status' => 'pending',
        ];
        
        $response = $this->postJson('/api/v1/prescriptions', $prescriptionData);
        
        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'prescription_uuid',
                    'medication_name',
                    'patient',
                    'prescribing_provider',
                ]
            ]);
        
        $this->assertDatabaseHas('prescriptions', [
            'medication_name' => 'Amoxicillin 500mg',
            'patient_id' => $this->patient->id,
        ]);
    }

    /** @test */
    public function it_validates_prescription_creation()
    {
        $invalidData = [
            // Missing required fields
            'medication_name' => 'Test Medication',
        ];
        
        $response = $this->postJson('/api/v1/prescriptions', $invalidData);
        
        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors'
            ]);
    }

    /** @test */
    public function it_can_show_a_prescription()
    {
        $prescription = Prescription::factory()->create([
            'prescribing_provider_staff_id' => $this->provider->id,
            'facility_id' => 1,
        ]);
        
        $response = $this->getJson("/api/v1/prescriptions/{$prescription->prescription_uuid}");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'prescription_uuid',
                    'medication_name',
                    'patient',
                    'prescribing_provider',
                ]
            ]);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_prescription()
    {
        $response = $this->getJson('/api/v1/prescriptions/nonexistent-uuid');
        
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Prescription not found.',
            ]);
    }

    /** @test */
    public function it_can_update_a_prescription()
    {
        $prescription = Prescription::factory()->create([
            'prescribing_provider_staff_id' => $this->provider->id,
            'facility_id' => 1,
            'dispense_status' => 'pending', // Can only update if not transmitted
        ]);
        
        $updateData = [
            'medication_name' => 'Updated Medication Name',
            'sig_instructions' => 'Updated instructions',
        ];
        
        $response = $this->putJson("/api/v1/prescriptions/{$prescription->prescription_uuid}", $updateData);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Prescription updated successfully.',
            ]);
        
        $this->assertDatabaseHas('prescriptions', [
            'id' => $prescription->id,
            'medication_name' => 'Updated Medication Name',
        ]);
    }

    /** @test */
    public function it_cannot_update_transmitted_prescription()
    {
        $prescription = Prescription::factory()->create([
            'prescribing_provider_staff_id' => $this->provider->id,
            'facility_id' => 1,
            'dispense_status' => 'transmitted',
            'transmitted_at' => now(),
        ]);
        
        $updateData = [
            'medication_name' => 'Attempted Update',
        ];
        
        $response = $this->putJson("/api/v1/prescriptions/{$prescription->prescription_uuid}", $updateData);
        
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    /** @test */
    public function it_can_delete_a_prescription()
    {
        $prescription = Prescription::factory()->create([
            'prescribing_provider_staff_id' => $this->provider->id,
            'facility_id' => 1,
            'dispense_status' => 'pending',
            'status' => 'active',
        ]);
        
        $response = $this->deleteJson("/api/v1/prescriptions/{$prescription->prescription_uuid}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Prescription deleted successfully.',
            ]);
        
        $this->assertSoftDeleted('prescriptions', ['id' => $prescription->id]);
    }

    /** @test */
    public function it_cannot_delete_transmitted_prescription()
    {
        $prescription = Prescription::factory()->create([
            'prescribing_provider_staff_id' => $this->provider->id,
            'facility_id' => 1,
            'dispense_status' => 'transmitted',
            'transmitted_at' => now(),
        ]);
        
        $response = $this->deleteJson("/api/v1/prescriptions/{$prescription->prescription_uuid}");
        
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
        
        $this->assertDatabaseHas('prescriptions', ['id' => $prescription->id, 'deleted_at' => null]);
    }

    /** @test */
    public function it_can_process_prescription_refill()
    {
        $prescription = Prescription::factory()->create([
            'prescribing_provider_staff_id' => $this->provider->id,
            'facility_id' => 1,
            'refills_allowed' => 2,
            'refills_remaining' => 2,
            'status' => 'active',
            'dispense_status' => 'dispensed',
        ]);
        
        $refillData = [
            'pharmacy_ncpdp_id' => '1234567890',
        ];
        
        $response = $this->postJson("/api/v1/prescriptions/{$prescription->prescription_uuid}/refill", $refillData);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Prescription refill processed successfully.',
            ]);
        
        $this->assertDatabaseHas('prescriptions', [
            'id' => $prescription->id,
            'refills_remaining' => 1,
        ]);
    }

    /** @test */
    public function it_can_update_dispense_status()
    {
        $prescription = Prescription::factory()->create([
            'prescribing_provider_staff_id' => $this->provider->id,
            'facility_id' => 1,
            'dispense_status' => 'pending',
        ]);
        
        $statusData = [
            'status' => 'transmitted',
        ];
        
        $response = $this->patchJson("/api/v1/prescriptions/{$prescription->prescription_uuid}/dispense-status", $statusData);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Dispense status updated successfully.',
            ]);
        
        $this->assertDatabaseHas('prescriptions', [
            'id' => $prescription->id,
            'dispense_status' => 'transmitted',
        ]);
    }

    /** @test */
    public function it_can_discontinue_prescription()
    {
        $prescription = Prescription::factory()->create([
            'prescribing_provider_staff_id' => $this->provider->id,
            'facility_id' => 1,
            'status' => 'active',
        ]);
        
        $discontinueData = [
            'reason' => 'Patient experienced adverse reaction',
        ];
        
        $response = $this->postJson("/api/v1/prescriptions/{$prescription->prescription_uuid}/discontinue", $discontinueData);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Prescription discontinued successfully.',
            ]);
        
        $this->assertDatabaseHas('prescriptions', [
            'id' => $prescription->id,
            'status' => 'discontinued',
        ]);
    }

    /** @test */
    public function it_checks_refill_eligibility()
    {
        $prescription = Prescription::factory()->create([
            'prescribing_provider_staff_id' => $this->provider->id,
            'facility_id' => 1,
            'refills_allowed' => 2,
            'refills_remaining' => 2,
            'status' => 'active',
            'valid_to' => now()->addDays(30),
        ]);
        
        $response = $this->getJson("/api/v1/prescriptions/{$prescription->prescription_uuid}/refill-eligibility");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'is_eligible',
                    'refills_remaining',
                ]
            ]);
    }

    /** @test */
    public function it_requires_authentication()
    {
        // Clear authentication
        Sanctum::actingAs(User::factory()->create());
        
        $response = $this->getJson('/api/v1/prescriptions');
        
        $response->assertStatus(403);
    }

    /** @test */
    public function it_respects_authorization()
    {
        // Create user without prescription permissions
        $unauthorizedUser = User::factory()->create();
        $unauthorizedUser->assignRole('receptionist');
        
        Sanctum::actingAs($unauthorizedUser);
        
        $response = $this->getJson('/api/v1/prescriptions');
        
        $response->assertStatus(403);
    }

    /** @test */
    public function it_can_get_prescription_statistics()
    {
        // Create test prescriptions
        Prescription::factory()->count(5)->create([
            'facility_id' => 1,
            'status' => 'active',
        ]);
        
        Prescription::factory()->count(3)->create([
            'facility_id' => 1,
            'status' => 'completed',
        ]);
        
        $response = $this->getJson('/api/v1/prescriptions/statistics?facility_id=1');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_prescriptions',
                    'active_prescriptions',
                    'by_status',
                ]
            ]);
    }

    /** @test */
    public function it_can_get_prescriptions_needing_transmission()
    {
        // Create prescriptions needing transmission
        Prescription::factory()->count(3)->create([
            'facility_id' => 1,
            'is_electronic_prescription' => true,
            'transmitted_at' => null,
            'status' => 'active',
            'dispense_status' => 'pending',
        ]);
        
        $response = $this->getJson('/api/v1/prescriptions/needs-transmission?facility_id=1');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'prescription_uuid',
                        'needs_transmission',
                    ]
                ],
                'meta'
            ]);
    }
}