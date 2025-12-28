<?php

namespace Tests\Feature;

use App\Models\ServiceCatalog;
use App\Models\ServiceVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ServiceVersionControllerTest
 * 
 * Feature tests for ServiceVersion API endpoints.
 * Tests complete request/response cycles including authentication and authorization.
 */
class ServiceVersionControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user.
     *
     * @var User
     */
    protected $user;

    /**
     * Test admin user.
     *
     * @var User
     */
    protected $adminUser;

    /**
     * Test service catalog.
     *
     * @var ServiceCatalog
     */
    protected $serviceCatalog;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->user = User::factory()->create([
            'role' => 'service_manager',
            'email' => 'manager@example.com'
        ]);

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@example.com'
        ]);

        // Create service catalog
        $this->serviceCatalog = ServiceCatalog::factory()->create();

        // Create test service version
        ServiceVersion::factory()->create([
            'service_catalog_id' => $this->serviceCatalog->id
        ]);
    }

    /**
     * Test index endpoint returns service versions.
     *
     * @return void
     */
    public function test_index_returns_service_versions(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/service-versions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'version_uuid',
                        'version_number',
                        'base_price_amount',
                        'final_price_amount',
                        'currency_code'
                    ]
                ],
                'pagination'
            ]);
    }

    /**
     * Test store endpoint creates service version.
     *
     * @return void
     */
    public function test_store_creates_service_version(): void
    {
        $data = [
            'service_catalog_id' => $this->serviceCatalog->id,
            'version_number' => '2.0.0',
            'valid_from' => '2024-02-01',
            'currency_code' => 'USD',
            'base_price_amount' => 150.00,
            'billing_method' => 'per_service',
            'minimum_billable_units' => 1,
            'version_snapshot' => [
                'name' => 'New Service Version',
                'description' => 'Updated service description'
            ]
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/service-versions', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Service version created successfully'
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'version_uuid',
                    'version_number',
                    'base_price_amount'
                ]
            ]);

        $this->assertDatabaseHas('service_versions', [
            'version_number' => '2.0.0',
            'service_catalog_id' => $this->serviceCatalog->id
        ]);
    }

    /**
     * Test store endpoint validation fails with invalid data.
     *
     * @return void
     */
    public function test_store_validation_fails_with_invalid_data(): void
    {
        $data = [
            'service_catalog_id' => $this->serviceCatalog->id,
            'version_number' => '', // Invalid - required
            'valid_from' => 'invalid-date', // Invalid format
            'currency_code' => 'US', // Invalid - should be 3 chars
            'base_price_amount' => -10, // Invalid - negative
            'billing_method' => 'invalid_method', // Invalid value
            'minimum_billable_units' => -1 // Invalid - negative
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/service-versions', $data);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed'
            ])
            ->assertJsonStructure([
                'errors' => [
                    'version_number',
                    'valid_from',
                    'currency_code',
                    'base_price_amount',
                    'billing_method',
                    'minimum_billable_units'
                ]
            ]);
    }

    /**
     * Test show endpoint returns service version.
     *
     * @return void
     */
    public function test_show_returns_service_version(): void
    {
        $serviceVersion = ServiceVersion::first();

        $response = $this->actingAs($this->user)
            ->getJson("/api/service-versions/{$serviceVersion->version_uuid}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $serviceVersion->id,
                    'version_uuid' => $serviceVersion->version_uuid,
                    'version_number' => $serviceVersion->version_number
                ]
            ]);
    }

    /**
     * Test show endpoint returns 404 for non-existent version.
     *
     * @return void
     */
    public function test_show_returns_404_for_non_existent_version(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/service-versions/non-existent-uuid');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Service version not found'
            ]);
    }

    /**
     * Test update endpoint updates service version.
     *
     * @return void
     */
    public function test_update_updates_service_version(): void
    {
        $serviceVersion = ServiceVersion::first();

        $data = [
            'version_number' => '1.0.1',
            'change_notes' => 'Updated pricing structure'
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/service-versions/{$serviceVersion->version_uuid}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Service version updated successfully'
            ]);

        $this->assertDatabaseHas('service_versions', [
            'id' => $serviceVersion->id,
            'version_number' => '1.0.1',
            'change_notes' => 'Updated pricing structure'
        ]);
    }

    /**
     * Test destroy endpoint deletes service version.
     *
     * @return void
     */
    public function test_destroy_deletes_service_version(): void
    {
        // Create an extra version so we don't delete the only one
        $serviceVersion = ServiceVersion::factory()->create([
            'service_catalog_id' => $this->serviceCatalog->id,
            'is_current' => false
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/service-versions/{$serviceVersion->version_uuid}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Service version deleted successfully'
            ]);

        $this->assertDatabaseMissing('service_versions', [
            'id' => $serviceVersion->id,
            'deleted_at' => null
        ]);
    }

    /**
     * Test get current version endpoint.
     *
     * @return void
     */
    public function test_get_current_version_returns_current_version(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/service-versions/service-catalog/{$this->serviceCatalog->id}/current");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'version_uuid',
                    'is_current'
                ]
            ]);
    }

    /**
     * Test set as current version endpoint.
     *
     * @return void
     */
    public function test_set_as_current_version_updates_current_flag(): void
    {
        // Create a non-current version
        $serviceVersion = ServiceVersion::factory()->create([
            'service_catalog_id' => $this->serviceCatalog->id,
            'is_current' => false,
            'valid_from' => now()->subDay()->format('Y-m-d')
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/service-versions/{$serviceVersion->version_uuid}/set-current");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Version set as current successfully'
            ]);

        // Verify the version is now current
        $this->assertDatabaseHas('service_versions', [
            'id' => $serviceVersion->id,
            'is_current' => true
        ]);

        // Verify other versions are not current
        $this->assertDatabaseHas('service_versions', [
            'id' => ServiceVersion::first()->id,
            'is_current' => false
        ]);
    }

    /**
     * Test get price calculation endpoint.
     *
     * @return void
     */
    public function test_get_price_calculation_returns_calculation(): void
    {
        $serviceVersion = ServiceVersion::first();

        $response = $this->actingAs($this->user)
            ->getJson("/api/service-versions/{$serviceVersion->version_uuid}/price-calculation");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'data' => [
                    'base_price',
                    'currency',
                    'final_price',
                    'display_price'
                ]
            ]);
    }

    /**
     * Test check billability endpoint.
     *
     * @return void
     */
    public function test_check_billability_returns_billability_info(): void
    {
        $serviceVersion = ServiceVersion::first();

        $response = $this->actingAs($this->user)
            ->postJson("/api/service-versions/{$serviceVersion->version_uuid}/check-billability", [
                'units' => 2
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'data' => [
                    'is_billable',
                    'billing_method',
                    'minimum_units'
                ]
            ]);
    }

    /**
     * Test calculate insurance coverage endpoint.
     *
     * @return void
     */
    public function test_calculate_insurance_coverage_returns_coverage(): void
    {
        $serviceVersion = ServiceVersion::factory()->create([
            'service_catalog_id' => $this->serviceCatalog->id,
            'insurance_coverage_rates' => [
                'commercial' => 80,
                'medicare' => 100
            ]
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/service-versions/{$serviceVersion->version_uuid}/calculate-insurance-coverage", [
                'insurance_type' => 'commercial'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'data' => [
                    'insurance_type',
                    'coverage_percentage',
                    'service_price',
                    'insurance_portion',
                    'patient_portion'
                ]
            ]);
    }

    /**
     * Test unauthorized access to protected endpoints.
     *
     * @return void
     */
    public function test_unauthorized_access_returns_401(): void
    {
        // Test without authentication
        $response = $this->postJson('/api/service-versions', []);
        
        $response->assertStatus(401);
    }

    /**
     * Test forbidden access due to insufficient permissions.
     *
     * @return void
     */
    // public function test_forbidden_access_returns_403(): void
    // {
    //     // Create user with insufficient permissions
    //     $unauthorizedUser = User::factory()->create([
    //         'role' => 'viewer', // Role without write permissions
    //         'email' => 'viewer@example.com'
    //     ]);

    //     $response = $this->actingAs($unauthorizedUser)
    //         ->postJson('/api/service-versions', [
    //             'service_catalog_id' => $this->serviceCatalog->id,
    //             'version_number' => '2.0.0',
    //             'valid_from' => '2024-02-01',
    //             'currency_code' => 'USD',
    //             'base_price_amount' => 150.00,
    //             'billing_method' => 'per_service',
    //             'minimum_billable_units' => 1,
    //             'version_snapshot' => ['name' => 'Test']
    //         ]);

    //     $response->assertStatus(403);
    // }
}