<?php

namespace Tests\Feature;

use App\Models\ServiceCatalog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ServiceCatalogControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var array
     */
    protected $serviceCatalogData;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password')
        ]);

        // Generate test service catalog data
        $this->serviceCatalogData = [
            'service_code' => 'TEST001',
            'code_system' => 'local_custom',
            'service_name' => 'Test Service',
            'service_description' => 'This is a test service',
            'service_category' => 'consultation',
            'applicable_region' => 'US',
            'effective_from' => now()->addDays(1)->format('Y-m-d'),
            'risk_level' => 'low',
            'requires_informed_consent' => false,
            'status' => 'active'
        ];

        // Authenticate the user
        $this->actingAs($this->user, 'api');
    }

    /**
     * Test getting all service catalogs.
     *
     * @return void
     */
    public function test_get_all_service_catalogs(): void
    {
        // Create test service catalogs
        ServiceCatalog::factory()->count(3)->create();

        $response = $this->getJson('/api/service-catalogs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'service_uuid',
                        'service_code',
                        'service_name',
                        'service_category',
                        'status'
                    ]
                ],
                'pagination' => [
                    'total',
                    'per_page',
                    'current_page',
                    'last_page'
                ]
            ])
            ->assertJson(['success' => true]);
    }

    /**
     * Test creating a new service catalog.
     *
     * @return void
     */
    public function test_create_service_catalog(): void
    {
        $response = $this->postJson('/api/service-catalogs', $this->serviceCatalogData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'service_uuid',
                    'service_code',
                    'service_name',
                    'service_category',
                    'status'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Service catalog created successfully.',
                'data' => [
                    'service_code' => 'TEST001',
                    'service_name' => 'Test Service'
                ]
            ]);

        // Verify the service catalog was created in the database
        $this->assertDatabaseHas('service_catalogs', [
            'service_code' => 'TEST001',
            'service_name' => 'Test Service'
        ]);
    }

    /**
     * Test validation when creating service catalog with duplicate service code.
     *
     * @return void
     */
    public function test_create_service_catalog_with_duplicate_code_fails(): void
    {
        // Create a service catalog first
        ServiceCatalog::factory()->create([
            'service_code' => 'TEST001'
        ]);

        $response = $this->postJson('/api/service-catalogs', $this->serviceCatalogData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'service_code'
                ]
            ])
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed.'
            ]);
    }

    /**
     * Test getting a specific service catalog by UUID.
     *
     * @return void
     */
    public function test_get_service_catalog_by_uuid(): void
    {
        $serviceCatalog = ServiceCatalog::factory()->create();

        $response = $this->getJson("/api/service-catalogs/{$serviceCatalog->service_uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'service_uuid',
                    'service_code',
                    'service_name',
                    'service_category',
                    'status'
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'service_uuid' => $serviceCatalog->service_uuid,
                    'service_code' => $serviceCatalog->service_code
                ]
            ]);
    }

    /**
     * Test getting a non-existent service catalog returns 404.
     *
     * @return void
     */
    public function test_get_nonexistent_service_catalog_returns_404(): void
    {
        $response = $this->getJson('/api/service-catalogs/nonexistent-uuid');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Service catalog not found.'
            ]);
    }

    /**
     * Test updating a service catalog.
     *
     * @return void
     */
    public function test_update_service_catalog(): void
    {
        $serviceCatalog = ServiceCatalog::factory()->create();

        $updateData = [
            'service_name' => 'Updated Service Name',
            'service_description' => 'Updated description',
            'risk_level' => 'moderate'
        ];

        $response = $this->putJson("/api/service-catalogs/{$serviceCatalog->service_uuid}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Service catalog updated successfully.',
                'data' => [
                    'service_name' => 'Updated Service Name',
                    'risk_level' => 'moderate'
                ]
            ]);

        // Verify the service catalog was updated in the database
        $this->assertDatabaseHas('service_catalogs', [
            'service_uuid' => $serviceCatalog->service_uuid,
            'service_name' => 'Updated Service Name',
            'risk_level' => 'moderate'
        ]);
    }

    /**
     * Test deleting a service catalog.
     *
     * @return void
     */
    public function test_delete_service_catalog(): void
    {
        $serviceCatalog = ServiceCatalog::factory()->create();

        $response = $this->deleteJson("/api/service-catalogs/{$serviceCatalog->service_uuid}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Service catalog deleted successfully.'
            ]);

        // Verify the service catalog was soft deleted
        $this->assertSoftDeleted('service_catalogs', [
            'service_uuid' => $serviceCatalog->service_uuid
        ]);
    }

    /**
     * Test restoring a soft-deleted service catalog.
     *
     * @return void
     */
    public function test_restore_service_catalog(): void
    {
        $serviceCatalog = ServiceCatalog::factory()->create();
        $serviceCatalog->delete();

        $response = $this->postJson("/api/service-catalogs/{$serviceCatalog->service_uuid}/restore");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Service catalog restored successfully.'
            ]);

        // Verify the service catalog was restored
        $this->assertDatabaseHas('service_catalogs', [
            'service_uuid' => $serviceCatalog->service_uuid,
            'deleted_at' => null
        ]);
    }

    /**
     * Test getting effective services for a date.
     *
     * @return void
     */
    public function test_get_effective_services(): void
    {
        $date = now()->format('Y-m-d');
        
        // Create an effective service
        ServiceCatalog::factory()->create([
            'effective_from' => now()->subDays(10),
            'effective_to' => now()->addDays(10),
            'status' => 'active'
        ]);

        // Create an inactive service
        ServiceCatalog::factory()->create([
            'effective_from' => now()->subDays(10),
            'status' => 'inactive'
        ]);

        $response = $this->getJson("/api/service-catalogs/effective/{$date}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'service_uuid',
                        'service_code',
                        'service_name',
                        'status'
                    ]
                ]
            ])
            ->assertJson(['success' => true]);

        // Check that only active, effective services are returned
        $responseData = $response->json();
        $this->assertCount(1, $responseData['data']);
    }

    /**
     * Test search functionality.
     *
     * @return void
     */
    public function test_search_service_catalogs(): void
    {
        ServiceCatalog::factory()->create([
            'service_name' => 'Cardiology Consultation',
            'service_code' => 'CARD001'
        ]);

        ServiceCatalog::factory()->create([
            'service_name' => 'Radiology Scan',
            'service_code' => 'RAD001'
        ]);

        $response = $this->getJson('/api/service-catalogs/search?q=Cardiology');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    [
                        'service_name' => 'Cardiology Consultation'
                    ]
                ]
            ]);
    }

    /**
     * Test getting service catalogs by code system.
     *
     * @return void
     */
    public function test_get_by_code_system(): void
    {
        ServiceCatalog::factory()->create([
            'code_system' => 'cpt',
            'service_code' => '99213'
        ]);

        ServiceCatalog::factory()->create([
            'code_system' => 'hcpcs',
            'service_code' => 'G0438'
        ]);

        $response = $this->getJson('/api/service-catalogs/code-system/cpt');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    [
                        'code_system' => 'cpt',
                        'service_code' => '99213'
                    ]
                ]
            ]);
    }

    /**
     * Test getting service catalog by service code.
     *
     * @return void
     */
    public function test_get_by_service_code(): void
    {
        $serviceCatalog = ServiceCatalog::factory()->create([
            'service_code' => 'SPECIAL001'
        ]);

        $response = $this->getJson('/api/service-catalogs/code/SPECIAL001');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'service_code' => 'SPECIAL001'
                ]
            ]);
    }

    /**
     * Test validation for required fields.
     *
     * @return void
     */
    public function test_validation_for_required_fields(): void
    {
        $response = $this->postJson('/api/service-catalogs', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors'
            ])
            ->assertJsonValidationErrors([
                'service_code',
                'code_system',
                'service_name',
                'service_category',
                'applicable_region',
                'effective_from'
            ]);
    }

    /**
     * Test pagination works correctly.
     *
     * @return void
     */
    public function test_pagination_works(): void
    {
        ServiceCatalog::factory()->count(25)->create();

        $response = $this->getJson('/api/service-catalogs?per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'pagination'
            ])
            ->assertJson([
                'pagination' => [
                    'per_page' => 10,
                    'total' => 25,
                    'last_page' => 3
                ]
            ]);
    }

    /**
     * Test filtering by status.
     *
     * @return void
     */
    public function test_filter_by_status(): void
    {
        ServiceCatalog::factory()->count(3)->create(['status' => 'active']);
        ServiceCatalog::factory()->create(['status' => 'inactive']);

        $response = $this->getJson('/api/service-catalogs?status=active');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    /**
     * Test filtering by service category.
     *
     * @return void
     */
    public function test_filter_by_service_category(): void
    {
        ServiceCatalog::factory()->count(2)->create(['service_category' => 'consultation']);
        ServiceCatalog::factory()->create(['service_category' => 'laboratory_test']);

        $response = $this->getJson('/api/service-catalogs?service_category=consultation');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }
}