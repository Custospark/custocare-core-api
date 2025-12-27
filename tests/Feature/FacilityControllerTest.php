<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Facility;
use App\Models\User;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;

/**
 * Class FacilityControllerTest
 * 
 * Feature tests for FacilityController API endpoints.
 */
class FacilityControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private $adminUser;
    private $facilityManagerUser;
    private $regionalDirectorUser;
    private $regularUser;
    
    private $facilityData;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users with different roles
        $this->adminUser = User::factory()->create([
            'email' => 'admin@test.com',
        ]);
        $this->adminUser->assignRole('system_administrator');
        
        $this->facilityManagerUser = User::factory()->create([
            'email' => 'manager@test.com',
        ]);
        $this->facilityManagerUser->assignRole('facility_manager');
        
        $this->regionalDirectorUser = User::factory()->create([
            'email' => 'director@test.com',
            'region' => 'us-east',
        ]);
        $this->regionalDirectorUser->assignRole('regional_director');
        
        $this->regularUser = User::factory()->create([
            'email' => 'user@test.com',
        ]);
        
        // Create staff records for users
        Staff::factory()->create(['user_id' => $this->adminUser->id]);
        Staff::factory()->create(['user_id' => $this->facilityManagerUser->id]);
        Staff::factory()->create(['user_id' => $this->regionalDirectorUser->id]);
        Staff::factory()->create(['user_id' => $this->regularUser->id]);
        
        // Sample facility data for testing
        $this->facilityData = [
            'facility_code' => 'TEST001',
            'facility_name' => 'Test Hospital',
            'legal_entity_name' => 'Test Hospital LLC',
            'facility_type' => 'hospital',
            'facility_tier' => 'tertiary',
            'address_line1' => '123 Test Street',
            'city' => 'Test City',
            'state_province' => 'Test State',
            'postal_code' => '12345',
            'country_code' => 'USA',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'timezone' => 'America/New_York',
            'main_phone' => '123-456-7890',
            'emergency_phone' => '123-456-7891',
            'email' => 'info@testhospital.com',
            'website' => 'https://testhospital.com',
            'operating_hours' => [
                'monday' => ['open' => '08:00', 'close' => '18:00'],
                'tuesday' => ['open' => '08:00', 'close' => '18:00'],
                'wednesday' => ['open' => '08:00', 'close' => '18:00'],
                'thursday' => ['open' => '08:00', 'close' => '18:00'],
                'friday' => ['open' => '08:00', 'close' => '18:00'],
                'saturday' => ['open' => '09:00', 'close' => '14:00'],
                'sunday' => ['closed' => true],
            ],
            'is_24_7' => false,
            'available_services' => ['emergency', 'surgery', 'imaging', 'lab'],
            'specialty_services' => ['cardiology', 'neurology'],
            'has_emergency_department' => true,
            'has_trauma_center' => true,
            'trauma_center_level' => 2,
            'has_intensive_care' => true,
            'has_neonatal_icu' => false,
            'has_cardiac_cath_lab' => true,
            'data_residency_region' => 'us-east',
            'primary_database_shard' => 'shard-01',
            'operational_status' => 'fully_operational',
            'bed_capacity' => 200,
            'participates_in_medicare' => true,
            'participates_in_medicaid' => true,
        ];
    }

    /** @test */
    public function it_can_list_facilities()
    {
        Facility::factory()->count(3)->create();
        
        $response = $this->actingAs($this->regularUser)
            ->getJson('/api/facilities');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'facility_uuid',
                        'facility_code',
                        'facility_name',
                        'facility_type',
                        'address' => [
                            'line1',
                            'city',
                            'state_province',
                            'country_code',
                            'full_address',
                        ],
                        'operations' => [
                            'operational_status',
                            'is_24_7',
                        ],
                        'links',
                    ]
                ],
                'meta',
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Facilities retrieved successfully',
            ]);
    }

    /** @test */
    public function it_can_filter_facilities_by_type()
    {
        Facility::factory()->create(['facility_type' => 'hospital']);
        Facility::factory()->create(['facility_type' => 'clinic']);
        
        $response = $this->actingAs($this->regularUser)
            ->getJson('/api/facilities?facility_type=hospital');
        
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.facility_type', 'hospital');
    }

    /** @test */
    public function it_can_create_facility_as_admin()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/facilities', $this->facilityData);
        
        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'facility_uuid',
                    'facility_code',
                    'facility_name',
                    'address',
                    'contact',
                    'operations',
                    'capabilities',
                    'links',
                ],
                'errors',
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Facility created successfully',
            ]);
        
        $this->assertDatabaseHas('facilities', [
            'facility_code' => 'TEST001',
            'facility_name' => 'Test Hospital',
        ]);
    }

    /** @test */
    public function it_prevents_unauthorized_users_from_creating_facilities()
    {
        $response = $this->actingAs($this->regularUser)
            ->postJson('/api/facilities', $this->facilityData);
        
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You are not authorized to create a facility.',
            ]);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_facility()
    {
        $invalidData = [
            'facility_name' => 'Test Facility',
            // Missing required fields
        ];
        
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/facilities', $invalidData);
        
        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'facility_code',
                    'legal_entity_name',
                    'facility_type',
                    'address_line1',
                    'city',
                    'state_province',
                    'postal_code',
                    'country_code',
                    'main_phone',
                    'operating_hours',
                    'available_services',
                    'data_residency_region',
                    'primary_database_shard',
                    'operational_status',
                ],
            ]);
    }

    /** @test */
    public function it_can_retrieve_single_facility()
    {
        $facility = Facility::factory()->create();
        
        $response = $this->actingAs($this->regularUser)
            ->getJson("/api/facilities/{$facility->facility_uuid}");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'facility_uuid',
                    'facility_code',
                    'facility_name',
                    'address',
                    'contact',
                    'operations',
                    'capabilities',
                    'links',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'facility_uuid' => $facility->facility_uuid,
                    'facility_code' => $facility->facility_code,
                ]
            ]);
    }

    /** @test */
    public function it_returns_404_when_facility_not_found()
    {
        $response = $this->actingAs($this->regularUser)
            ->getJson('/api/facilities/nonexistent-uuid');
        
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Facility not found',
            ]);
    }

    /** @test */
    public function it_can_update_facility_as_admin()
    {
        $facility = Facility::factory()->create([
            'data_residency_region' => 'us-east',
        ]);
        
        $updateData = [
            'facility_name' => 'Updated Hospital Name',
            'bed_capacity' => 250,
        ];
        
        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/facilities/{$facility->facility_uuid}", $updateData);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Facility updated successfully',
                'data' => [
                    'facility_name' => 'Updated Hospital Name',
                ]
            ]);
        
        $this->assertDatabaseHas('facilities', [
            'id' => $facility->id,
            'facility_name' => 'Updated Hospital Name',
            'bed_capacity' => 250,
        ]);
    }

    /** @test */
    public function it_can_update_facility_as_regional_director_in_same_region()
    {
        $facility = Facility::factory()->create([
            'data_residency_region' => 'us-east',
        ]);
        
        $updateData = [
            'facility_name' => 'Updated by Director',
        ];
        
        $response = $this->actingAs($this->regionalDirectorUser)
            ->putJson("/api/facilities/{$facility->facility_uuid}", $updateData);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Facility updated successfully',
            ]);
    }

    /** @test */
    public function it_prevents_regional_director_from_updating_facility_in_different_region()
    {
        $facility = Facility::factory()->create([
            'data_residency_region' => 'eu-west',
        ]);
        
        $updateData = [
            'facility_name' => 'Should Not Update',
        ];
        
        $response = $this->actingAs($this->regionalDirectorUser)
            ->putJson("/api/facilities/{$facility->facility_uuid}", $updateData);
        
        $response->assertStatus(403);
    }

    /** @test */
    public function it_can_soft_delete_facility_as_admin()
    {
        $facility = Facility::factory()->create();
        
        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/facilities/{$facility->facility_uuid}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Facility deleted successfully',
            ]);
        
        $this->assertSoftDeleted('facilities', ['id' => $facility->id]);
    }

    /** @test */
    public function it_prevents_unauthorized_users_from_deleting_facilities()
    {
        $facility = Facility::factory()->create();
        
        $response = $this->actingAs($this->regularUser)
            ->deleteJson("/api/facilities/{$facility->facility_uuid}");
        
        $response->assertStatus(403);
        
        $this->assertDatabaseHas('facilities', ['id' => $facility->id, 'deleted_at' => null]);
    }

    /** @test */
    public function it_can_restore_soft_deleted_facility_as_admin()
    {
        $facility = Facility::factory()->create(['deleted_at' => now()]);
        
        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/facilities/{$facility->facility_uuid}/restore");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Facility restored successfully',
            ]);
        
        $this->assertDatabaseHas('facilities', ['id' => $facility->id, 'deleted_at' => null]);
    }

    /** @test */
    public function it_can_search_facilities_by_name_or_code()
    {
        $facility1 = Facility::factory()->create([
            'facility_name' => 'City Hospital',
            'facility_code' => 'CH001',
        ]);
        
        $facility2 = Facility::factory()->create([
            'facility_name' => 'Community Clinic',
            'facility_code' => 'CC002',
        ]);
        
        $response = $this->actingAs($this->regularUser)
            ->getJson('/api/facilities/search/City');
        
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.facility_name', 'City Hospital');
        
        $response = $this->actingAs($this->regularUser)
            ->getJson('/api/facilities/search/CC002');
        
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.facility_code', 'CC002');
    }

    /** @test */
    public function it_can_get_facilities_by_location()
    {
        Facility::factory()->create([
            'country_code' => 'USA',
            'state_province' => 'CA',
            'city' => 'Los Angeles',
        ]);
        
        Facility::factory()->create([
            'country_code' => 'USA',
            'state_province' => 'CA',
            'city' => 'San Francisco',
        ]);
        
        Facility::factory()->create([
            'country_code' => 'CAN',
            'state_province' => 'ON',
            'city' => 'Toronto',
        ]);
        
        $response = $this->actingAs($this->regularUser)
            ->getJson('/api/facilities/location/USA/CA');
        
        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
        
        $response = $this->actingAs($this->regularUser)
            ->getJson('/api/facilities/location/USA/CA/Los Angeles');
        
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.address.city', 'Los Angeles');
    }

    /** @test */
    public function it_can_get_facilities_with_emergency_departments()
    {
        Facility::factory()->create([
            'has_emergency_department' => true,
            'facility_name' => 'Hospital with ED',
        ]);
        
        Facility::factory()->create([
            'has_emergency_department' => false,
            'facility_name' => 'Clinic without ED',
        ]);
        
        $response = $this->actingAs($this->regularUser)
            ->getJson('/api/facilities/with-emergency-departments');
        
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.facility_name', 'Hospital with ED')
            ->assertJsonPath('data.0.capabilities.has_emergency_department', true);
    }

    /** @test */
    public function it_can_update_facility_metrics()
    {
        $facility = Facility::factory()->create([
            'data_residency_region' => 'us-east',
        ]);
        
        $this->regionalDirectorUser->update(['region' => 'us-east']);
        
        $metrics = [
            'average_wait_time_minutes' => 15.5,
            'patient_satisfaction_score' => 4.2,
            'monthly_patient_volume' => 1000,
        ];
        
        $response = $this->actingAs($this->regionalDirectorUser)
            ->patchJson("/api/facilities/{$facility->facility_uuid}/metrics", $metrics);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Facility metrics updated successfully',
            ]);
        
        $this->assertDatabaseHas('facilities', [
            'id' => $facility->id,
            'average_wait_time_minutes' => 15.5,
            'patient_satisfaction_score' => 4.2,
            'monthly_patient_volume' => 1000,
        ]);
    }

    /** @test */
    public function it_can_check_facility_operational_status()
    {
        $facility = Facility::factory()->create([
            'operational_status' => 'fully_operational',
            'has_emergency_department' => true,
            'is_24_7' => false,
        ]);
        
        $response = $this->actingAs($this->regularUser)
            ->getJson("/api/facilities/{$facility->facility_uuid}/operational-status");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Operational status retrieved',
                'data' => [
                    'is_fully_operational' => true,
                    'is_closed' => false,
                    'operational_status' => 'fully_operational',
                    'has_emergency_department' => true,
                    'is_24_7' => false,
                ]
            ]);
    }

    /** @test */
    public function it_can_update_facility_operational_status()
    {
        $facility = Facility::factory()->create([
            'operational_status' => 'fully_operational',
            'data_residency_region' => 'us-east',
        ]);
        
        $this->regionalDirectorUser->update(['region' => 'us-east']);
        
        $updateData = [
            'operational_status' => 'temporarily_closed',
            'status_change_reason' => 'Renovations in progress',
        ];
        
        $response = $this->actingAs($this->regionalDirectorUser)
            ->patchJson("/api/facilities/{$facility->facility_uuid}/operational-status", $updateData);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Operational status updated successfully',
            ]);
        
        $this->assertDatabaseHas('facilities', [
            'id' => $facility->id,
            'operational_status' => 'temporarily_closed',
        ]);
    }

    /** @test */
    public function it_returns_pagination_metadata()
    {
        Facility::factory()->count(25)->create();
        
        $response = $this->actingAs($this->regularUser)
            ->getJson('/api/facilities?per_page=10');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta' => [
                    'total',
                    'per_page',
                    'current_page',
                    'last_page',
                    'from',
                    'to',
                ],
            ])
            ->assertJson([
                'meta' => [
                    'per_page' => 10,
                    'current_page' => 1,
                ]
            ]);
    }
}