<?php

namespace Tests\Unit;

use App\Models\ServiceVersion;
use App\Repositories\Contracts\ServiceVersionRepositoryInterface;
use App\Services\ServiceVersion\ServiceVersionService;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

/**
 * ServiceVersionServiceTest
 * 
 * Unit tests for ServiceVersionService business logic.
 * Mocks repository to isolate service layer testing.
 */
class ServiceVersionServiceTest extends TestCase
{
    /**
     * Service instance.
     *
     * @var ServiceVersionService
     */
    protected $service;

    /**
     * Mock repository.
     *
     * @var Mockery\MockInterface|ServiceVersionRepositoryInterface
     */
    protected $repository;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create mock repository
        $this->repository = Mockery::mock(ServiceVersionRepositoryInterface::class);
        
        // Create service with mocked repository
        $this->service = new ServiceVersionService($this->repository);
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
     * Test getting service version by ID successfully.
     *
     * @return void
     */
    public function test_get_service_version_success(): void
    {
        $serviceVersion = ServiceVersion::factory()->make([
            'id' => 1,
            'version_uuid' => '123e4567-e89b-12d3-a456-426614174000'
        ]);

        $this->repository->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($serviceVersion);

        $result = $this->service->getServiceVersion(1);

        $this->assertTrue($result['success']);
        $this->assertEquals('Service version retrieved successfully', $result['message']);
        $this->assertEquals($serviceVersion, $result['data']);
        $this->assertEquals(200, $result['status']);
    }

    /**
     * Test getting non-existent service version.
     *
     * @return void
     */
    public function test_get_service_version_not_found(): void
    {
        $this->repository->shouldReceive('findById')
            ->once()
            ->with(999)
            ->andReturn(null);

        $result = $this->service->getServiceVersion(999);

        $this->assertFalse($result['success']);
        $this->assertEquals('Service version not found', $result['message']);
        $this->assertNull($result['data']);
        $this->assertEquals(404, $result['status']);
    }

    /**
     * Test getting service version by UUID successfully.
     *
     * @return void
     */
    public function test_get_service_version_by_uuid_success(): void
    {
        $uuid = '123e4567-e89b-12d3-a456-426614174000';
        $serviceVersion = ServiceVersion::factory()->make([
            'version_uuid' => $uuid
        ]);

        $this->repository->shouldReceive('findByUuid')
            ->once()
            ->with($uuid)
            ->andReturn($serviceVersion);

        $result = $this->service->getServiceVersionByUuid($uuid);

        $this->assertTrue($result['success']);
        $this->assertEquals($serviceVersion, $result['data']);
    }

    /**
     * Test creating service version successfully.
     *
     * @return void
     */
    public function test_create_service_version_success(): void
    {
        $data = [
            'service_catalog_id' => 1,
            'version_number' => '1.0.0',
            'valid_from' => '2024-01-01',
            'currency_code' => 'USD',
            'base_price_amount' => 100.00,
            'billing_method' => 'per_service',
            'minimum_billable_units' => 1,
            'version_snapshot' => ['name' => 'Test Service']
        ];

        $serviceVersion = ServiceVersion::factory()->make(array_merge($data, [
            'id' => 1,
            'final_price_amount' => 100.00
        ]));

        $this->repository->shouldReceive('versionNumberExists')
            ->once()
            ->with(1, null, '1.0.0')
            ->andReturn(false);

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return $arg['service_catalog_id'] === 1;
            }))
            ->andReturn($serviceVersion);

        $result = $this->service->createServiceVersion($data);

        $this->assertTrue($result['success']);
        $this->assertEquals('Service version created successfully', $result['message']);
        $this->assertEquals(201, $result['status']);
    }

    /**
     * Test creating service version with duplicate version number.
     *
     * @return void
     */
    public function test_create_service_version_duplicate_version_number(): void
    {
        $data = [
            'service_catalog_id' => 1,
            'version_number' => '1.0.0',
            'valid_from' => '2024-01-01',
            'currency_code' => 'USD',
            'base_price_amount' => 100.00,
            'billing_method' => 'per_service',
            'minimum_billable_units' => 1,
            'version_snapshot' => ['name' => 'Test Service']
        ];

        $this->repository->shouldReceive('versionNumberExists')
            ->once()
            ->with(1, null, '1.0.0')
            ->andReturn(true);

        $result = $this->service->createServiceVersion($data);

        $this->assertFalse($result['success']);
        $this->assertEquals('Version number already exists for this service catalog and facility', $result['message']);
        $this->assertEquals(422, $result['status']);
        $this->assertArrayHasKey('version_number', $result['errors']);
    }

    /**
     * Test updating service version successfully.
     *
     * @return void
     */
    public function test_update_service_version_success(): void
    {
        $serviceVersion = ServiceVersion::factory()->make([
            'id' => 1,
            'service_catalog_id' => 1,
            'version_number' => '1.0.0'
        ]);

        $data = ['version_number' => '1.0.1'];

        $this->repository->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($serviceVersion);

        $this->repository->shouldReceive('versionNumberExists')
            ->once()
            ->with(1, null, '1.0.1', 1)
            ->andReturn(false);

        $updatedVersion = clone $serviceVersion;
        $updatedVersion->version_number = '1.0.1';

        $this->repository->shouldReceive('update')
            ->once()
            ->with($serviceVersion, $data)
            ->andReturn($updatedVersion);

        $result = $this->service->updateServiceVersion(1, $data);

        $this->assertTrue($result['success']);
        $this->assertEquals('Service version updated successfully', $result['message']);
    }

    /**
     * Test deleting service version successfully.
     *
     * @return void
     */
    public function test_delete_service_version_success(): void
    {
        $serviceVersion = ServiceVersion::factory()->make([
            'id' => 1,
            'service_catalog_id' => 1,
            'facility_id' => null
        ]);

        $this->repository->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($serviceVersion);

        // Mock version count query
        $this->mock(ServiceVersion::class, function ($mock) {
            $mock->shouldReceive('where')->andReturnSelf();
            $mock->shouldReceive('where')->andReturnSelf();
            $mock->shouldReceive('count')->andReturn(2);
        });

        $this->repository->shouldReceive('delete')
            ->once()
            ->with($serviceVersion)
            ->andReturn(true);

        $result = $this->service->deleteServiceVersion(1);

        $this->assertTrue($result['success']);
        $this->assertEquals('Service version deleted successfully', $result['message']);
    }

    /**
     * Test getting current version successfully.
     *
     * @return void
     */
    public function test_get_current_version_success(): void
    {
        $serviceVersions = new Collection([
            ServiceVersion::factory()->make(['is_current' => true])
        ]);

        $this->repository->shouldReceive('getCurrentVersions')
            ->once()
            ->with(1, null)
            ->andReturn($serviceVersions);

        $result = $this->service->getCurrentVersion(1);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['data']);
    }

    /**
     * Test getting price calculation successfully.
     *
     * @return void
     */
    public function test_get_price_calculation_success(): void
    {
        $serviceVersion = ServiceVersion::factory()->make([
            'id' => 1,
            'base_price_amount' => 100.00,
            'facility_markup_percentage' => 10.00,
            'final_price_amount' => 110.00,
            'currency_code' => 'USD',
            'direct_cost' => 50.00,
            'indirect_cost' => 20.00,
            'target_margin_percentage' => 30.00
        ]);

        $this->repository->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($serviceVersion);

        $result = $this->service->getPriceCalculation(1);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals(100.00, $result['data']['base_price']);
        $this->assertEquals(110.00, $result['data']['final_price']);
    }

    /**
     * Test validating version data successfully.
     *
     * @return void
     */
    public function test_validate_version_data_success(): void
    {
        $data = [
            'service_catalog_id' => 1,
            'version_number' => '1.0.0',
            'valid_from' => '2024-01-01',
            'currency_code' => 'USD',
            'base_price_amount' => 100.00,
            'billing_method' => 'per_service',
            'minimum_billable_units' => 1,
            'version_snapshot' => ['name' => 'Test Service']
        ];

        $result = $this->service->validateVersionData($data);

        $this->assertTrue($result['success']);
    }

    /**
     * Test validating version data with invalid currency code.
     *
     * @return void
     */
    public function test_validate_version_data_invalid_currency(): void
    {
        $data = [
            'service_catalog_id' => 1,
            'version_number' => '1.0.0',
            'valid_from' => '2024-01-01',
            'currency_code' => 'US', // Invalid - should be 3 characters
            'base_price_amount' => 100.00,
            'billing_method' => 'per_service',
            'minimum_billable_units' => 1,
            'version_snapshot' => ['name' => 'Test Service']
        ];

        $result = $this->service->validateVersionData($data);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('currency_code', $result['errors']);
    }

    /**
     * Test checking billability successfully.
     *
     * @return void
     */
    public function test_check_billability_success(): void
    {
        $serviceVersion = ServiceVersion::factory()->make([
            'id' => 1,
            'is_billable' => true,
            'billing_method' => 'per_service',
            'minimum_billable_units' => 1,
            'requires_preauthorization' => false
        ]);

        $this->repository->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($serviceVersion);

        $result = $this->service->checkBillability(1, []);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['is_billable']);
    }
}