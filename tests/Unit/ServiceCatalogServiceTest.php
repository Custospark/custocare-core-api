<?php

namespace Tests\Unit;

use App\Models\ServiceCatalog;
use App\Repositories\Contracts\ServiceCatalogRepositoryInterface;
use App\Services\ServiceCatalog\ServiceCatalogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Tests\TestCase;

class ServiceCatalogServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var ServiceCatalogService
     */
    protected $service;

    /**
     * @var Mockery\MockInterface|ServiceCatalogRepositoryInterface
     */
    protected $repositoryMock;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock repository
        $this->repositoryMock = Mockery::mock(ServiceCatalogRepositoryInterface::class);

        // Create the service with the mock repository
        $this->service = new ServiceCatalogService($this->repositoryMock);
    }

    /**
     * Clean up the test environment.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test getting all service catalogs successfully.
     *
     * @return void
     */
    public function test_get_all_service_catalogs_successfully(): void
    {
        // Create a mock paginator
        $paginator = new LengthAwarePaginator(
            ServiceCatalog::factory()->count(5)->make(),
            15,
            5,
            1
        );

        // Mock repository method
        $this->repositoryMock->shouldReceive('paginate')
            ->once()
            ->with(15, [])
            ->andReturn($paginator);

        // Call the service method
        $result = $this->service->getAllServiceCatalogs();

        // Assert the result
        $this->assertTrue($result['success']);
        $this->assertEquals('Service catalogs retrieved successfully.', $result['message']);
        $this->assertCount(5, $result['data']['services']);
        $this->assertArrayHasKey('pagination', $result['data']);
    }

    /**
     * Test creating a service catalog successfully.
     *
     * @return void
     */
    public function test_create_service_catalog_successfully(): void
    {
        // Create a service catalog model
        $serviceCatalog = ServiceCatalog::factory()->make();
        
        // Mock repository method
        $this->repositoryMock->shouldReceive('create')
            ->once()
            ->andReturn($serviceCatalog);

        $this->repositoryMock->shouldReceive('serviceCodeExists')
            ->once()
            ->andReturn(false);

        // Test data
        $data = [
            'service_code' => 'TEST001',
            'code_system' => 'local_custom',
            'service_name' => 'Test Service',
            'service_category' => 'consultation',
            'applicable_region' => 'US',
            'effective_from' => now()->addDay()->format('Y-m-d')
        ];

        // Call the service method
        $result = $this->service->createServiceCatalog($data);

        // Assert the result
        $this->assertTrue($result['success']);
        $this->assertEquals('Service catalog created successfully.', $result['message']);
        $this->assertNotNull($result['data']);
    }

    /**
     * Test creating a service catalog with duplicate service code fails.
     *
     * @return void
     */
    public function test_create_service_catalog_with_duplicate_code_fails(): void
    {
        // Mock repository method
        $this->repositoryMock->shouldReceive('serviceCodeExists')
            ->once()
            ->andReturn(true);

        // Test data
        $data = [
            'service_code' => 'DUPLICATE001'
        ];

        // Call the service method
        $result = $this->service->createServiceCatalog($data);

        // Assert the result
        $this->assertFalse($result['success']);
        $this->assertEquals('Service code already exists. Please use a different code.', $result['message']);
    }

    /**
     * Test getting a service catalog by UUID successfully.
     *
     * @return void
     */
    public function test_get_service_catalog_by_uuid_successfully(): void
    {
        // Create a service catalog model
        $serviceCatalog = ServiceCatalog::factory()->make();

        // Mock repository method
        $this->repositoryMock->shouldReceive('findByUuid')
            ->once()
            ->with('test-uuid')
            ->andReturn($serviceCatalog);

        // Call the service method
        $result = $this->service->getServiceCatalogByUuid('test-uuid');

        // Assert the result
        $this->assertTrue($result['success']);
        $this->assertEquals('Service catalog retrieved successfully.', $result['message']);
        $this->assertNotNull($result['data']);
    }

    /**
     * Test getting a non-existent service catalog returns not found.
     *
     * @return void
     */
    public function test_get_nonexistent_service_catalog_returns_not_found(): void
    {
        // Mock repository method
        $this->repositoryMock->shouldReceive('findByUuid')
            ->once()
            ->with('nonexistent-uuid')
            ->andReturn(null);

        // Call the service method
        $result = $this->service->getServiceCatalogByUuid('nonexistent-uuid');

        // Assert the result
        $this->assertFalse($result['success']);
        $this->assertEquals('Service catalog not found.', $result['message']);
    }

    /**
     * Test updating a service catalog successfully.
     *
     * @return void
     */
    public function test_update_service_catalog_successfully(): void
    {
        // Create a service catalog model
        $serviceCatalog = ServiceCatalog::factory()->make();

        // Mock repository methods
        $this->repositoryMock->shouldReceive('findByUuid')
            ->once()
            ->with('test-uuid')
            ->andReturn($serviceCatalog);

        $this->repositoryMock->shouldReceive('update')
            ->once()
            ->andReturn(true);

        $this->repositoryMock->shouldReceive('serviceCodeExists')
            ->once()
            ->andReturn(false);

        // Test data
        $data = [
            'service_name' => 'Updated Service Name'
        ];

        // Call the service method
        $result = $this->service->updateServiceCatalog('test-uuid', $data);

        // Assert the result
        $this->assertTrue($result['success']);
        $this->assertEquals('Service catalog updated successfully.', $result['message']);
    }

    /**
     * Test updating a non-existent service catalog returns not found.
     *
     * @return void
     */
    public function test_update_nonexistent_service_catalog_returns_not_found(): void
    {
        // Mock repository method
        $this->repositoryMock->shouldReceive('findByUuid')
            ->once()
            ->with('nonexistent-uuid')
            ->andReturn(null);

        // Call the service method
        $result = $this->service->updateServiceCatalog('nonexistent-uuid', []);

        // Assert the result
        $this->assertFalse($result['success']);
        $this->assertEquals('Service catalog not found.', $result['message']);
    }

    /**
     * Test deleting a service catalog successfully.
     *
     * @return void
     */
    public function test_delete_service_catalog_successfully(): void
    {
        // Create a service catalog model
        $serviceCatalog = ServiceCatalog::factory()->make();

        // Mock repository methods
        $this->repositoryMock->shouldReceive('findByUuid')
            ->once()
            ->with('test-uuid')
            ->andReturn($serviceCatalog);

        $this->repositoryMock->shouldReceive('delete')
            ->once()
            ->andReturn(true);

        // Call the service method
        $result = $this->service->deleteServiceCatalog('test-uuid');

        // Assert the result
        $this->assertTrue($result['success']);
        $this->assertEquals('Service catalog deleted successfully.', $result['message']);
    }

    /**
     * Test getting effective services successfully.
     *
     * @return void
     */
    public function test_get_effective_services_successfully(): void
    {
        // Create a collection of service catalogs
        $services = new Collection([
            ServiceCatalog::factory()->make(),
            ServiceCatalog::factory()->make()
        ]);

        // Mock repository method
        $this->repositoryMock->shouldReceive('getEffectiveServices')
            ->once()
            ->with('2024-01-01', [])
            ->andReturn($services);

        // Call the service method
        $result = $this->service->getEffectiveServices('2024-01-01');

        // Assert the result
        $this->assertTrue($result['success']);
        $this->assertEquals('Effective services retrieved successfully.', $result['message']);
        $this->assertCount(2, $result['data']);
    }

    /**
     * Test getting services by code system successfully.
     *
     * @return void
     */
    public function test_get_by_code_system_successfully(): void
    {
        // Create a collection of service catalogs
        $services = new Collection([
            ServiceCatalog::factory()->make(['code_system' => 'cpt']),
            ServiceCatalog::factory()->make(['code_system' => 'cpt'])
        ]);

        // Mock repository method
        $this->repositoryMock->shouldReceive('getByCodeSystem')
            ->once()
            ->with('cpt', [])
            ->andReturn($services);

        // Call the service method
        $result = $this->service->getByCodeSystem('cpt');

        // Assert the result
        $this->assertTrue($result['success']);
        $this->assertEquals('Service catalogs retrieved successfully.', $result['message']);
        $this->assertCount(2, $result['data']);
    }

    /**
     * Test getting services by invalid code system returns error.
     *
     * @return void
     */
    public function test_get_by_invalid_code_system_returns_error(): void
    {
        // Call the service method with invalid code system
        $result = $this->service->getByCodeSystem('invalid-system');

        // Assert the result
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid code system', $result['message']);
    }

    /**
     * Test search service catalogs successfully.
     *
     * @return void
     */
    public function test_search_service_catalogs_successfully(): void
    {
        // Create a collection of service catalogs
        $services = new Collection([
            ServiceCatalog::factory()->make(['service_name' => 'Cardiology Test']),
            ServiceCatalog::factory()->make(['service_code' => 'CARD001'])
        ]);

        // Mock repository method
        $this->repositoryMock->shouldReceive('search')
            ->once()
            ->with('Cardio', [])
            ->andReturn($services);

        // Call the service method
        $result = $this->service->searchServiceCatalogs('Cardio');

        // Assert the result
        $this->assertTrue($result['success']);
        $this->assertEquals('Search completed successfully.', $result['message']);
        $this->assertCount(2, $result['data']);
    }

    /**
     * Test search with short term returns error.
     *
     * @return void
     */
    public function test_search_with_short_term_returns_error(): void
    {
        // Call the service method with short search term
        $result = $this->service->searchServiceCatalogs('a');

        // Assert the result
        $this->assertFalse($result['success']);
        $this->assertEquals('Search term must be at least 2 characters long.', $result['message']);
    }

    /**
     * Test validating service catalog data successfully.
     *
     * @return void
     */
    public function test_validate_service_catalog_data_successfully(): void
    {
        // Valid data
        $data = [
            'service_code' => 'TEST001',
            'code_system' => 'local_custom',
            'service_name' => 'Test Service',
            'service_category' => 'consultation',
            'applicable_region' => 'US',
            'effective_from' => '2024-01-01'
        ];

        // Mock repository method
        $this->repositoryMock->shouldReceive('serviceCodeExists')
            ->once()
            ->andReturn(false);

        // Call the service method
        $result = $this->service->validateServiceCatalogData($data);

        // Assert the result
        $this->assertTrue($result['success']);
        $this->assertEquals('Validation successful.', $result['message']);
    }

    /**
     * Test validating service catalog data with missing required fields.
     *
     * @return void
     */
    public function test_validate_service_catalog_data_with_missing_fields(): void
    {
        // Invalid data (missing required fields)
        $data = [
            'service_name' => 'Test Service'
        ];

        // Call the service method
        $result = $this->service->validateServiceCatalogData($data);

        // Assert the result
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('is required', $result['message']);
    }

    /**
     * Test checking service effectiveness successfully.
     *
     * @return void
     */
    public function test_check_service_effectiveness_successfully(): void
    {
        // Create a service catalog model
        $serviceCatalog = ServiceCatalog::factory()->make([
            'effective_from' => '2024-01-01',
            'effective_to' => '2024-12-31',
            'status' => 'active'
        ]);

        // Mock repository method
        $this->repositoryMock->shouldReceive('findByUuid')
            ->once()
            ->with('test-uuid')
            ->andReturn($serviceCatalog);

        // Call the service method
        $result = $this->service->checkServiceEffectiveness('test-uuid', '2024-06-01');

        // Assert the result
        $this->assertTrue($result['success']);
        $this->assertEquals('Service effectiveness check completed.', $result['message']);
        $this->assertArrayHasKey('is_effective', $result['data']);
    }

    /**
     * Test restoring a service catalog successfully.
     *
     * @return void
     */
    public function test_restore_service_catalog_successfully(): void
    {
        // Create a service catalog model
        $serviceCatalog = ServiceCatalog::factory()->make();

        // Mock repository methods
        $this->repositoryMock->shouldReceive('restore')
            ->once()
            ->andReturn(true);

        // We need to mock the ServiceCatalog model for withTrashed
        $modelMock = Mockery::mock('alias:' . ServiceCatalog::class);
        $modelMock->shouldReceive('withTrashed')
            ->once()
            ->andReturnSelf();
        $modelMock->shouldReceive('where')
            ->once()
            ->andReturnSelf();
        $modelMock->shouldReceive('first')
            ->once()
            ->andReturn($serviceCatalog);

        // Mock trashed method
        $serviceCatalog->shouldReceive('trashed')
            ->once()
            ->andReturn(true);

        $serviceCatalog->shouldReceive('refresh')
            ->once()
            ->andReturn($serviceCatalog);

        // Call the service method
        $result = $this->service->restoreServiceCatalog('test-uuid');

        // Assert the result
        $this->assertTrue($result['success']);
        $this->assertEquals('Service catalog restored successfully.', $result['message']);
    }
}