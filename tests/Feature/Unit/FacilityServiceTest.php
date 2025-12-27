<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Facility\FacilityService;
use App\Repositories\Contracts\FacilityRepositoryInterface;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Mockery;

/**
 * Class FacilityServiceTest
 * 
 * Unit tests for FacilityService.
 */
class FacilityServiceTest extends TestCase
{
    private $facilityRepositoryMock;
    private $facilityService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->facilityRepositoryMock = Mockery::mock(FacilityRepositoryInterface::class);
        $this->facilityService = new FacilityService($this->facilityRepositoryMock);
        
        // Clear cache before each test
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_get_all_facilities()
    {
        $facilities = new Collection([
            Facility::factory()->make(),
            Facility::factory()->make(),
        ]);
        
        $this->facilityRepositoryMock
            ->shouldReceive('getAll')
            ->once()
            ->with([], ['parentOrganization', 'createdBy', 'updatedBy'])
            ->andReturn($facilities);
        
        $result = $this->facilityService->getAllFacilities();
        
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
    }

    /** @test */
    public function it_can_get_paginated_facilities()
    {
        $paginator = new LengthAwarePaginator([], 0, 15);
        
        $this->facilityRepositoryMock
            ->shouldReceive('getPaginated')
            ->once()
            ->with(15, [], ['parentOrganization', 'createdBy', 'updatedBy'])
            ->andReturn($paginator);
        
        $result = $this->facilityService->getPaginatedFacilities();
        
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
    }

    /** @test */
    public function it_can_get_facility_by_id()
    {
        $facility = Facility::factory()->make(['id' => 1]);
        
        $this->facilityRepositoryMock
            ->shouldReceive('findById')
            ->once()
            ->with(1, ['parentOrganization', 'createdBy', 'updatedBy'])
            ->andReturn($facility);
        
        $result = $this->facilityService->getFacilityById(1);
        
        $this->assertInstanceOf(Facility::class, $result);
        $this->assertEquals(1, $result->id);
    }

    /** @test */
    public function it_returns_null_when_facility_not_found_by_id()
    {
        $this->facilityRepositoryMock
            ->shouldReceive('findById')
            ->once()
            ->with(999, ['parentOrganization', 'createdBy', 'updatedBy'])
            ->andReturn(null);
        
        $result = $this->facilityService->getFacilityById(999);
        
        $this->assertNull($result);
    }

    /** @test */
    public function it_can_create_facility()
    {
        $data = [
            'facility_code' => 'TEST001',
            'facility_name' => 'Test Facility',
            'legal_entity_name' => 'Test Entity',
            'facility_type' => 'hospital',
            'facility_tier' => 'tertiary',
            'address_line1' => '123 Test St',
            'city' => 'Test City',
            'state_province' => 'Test State',
            'postal_code' => '12345',
            'country_code' => 'USA',
            'main_phone' => '123-456-7890',
            'operating_hours' => [],
            'available_services' => ['emergency'],
            'data_residency_region' => 'us-east',
            'primary_database_shard' => 'shard-01',
            'operational_status' => 'fully_operational',
        ];
        
        $facility = Facility::factory()->make($data);
        
        // Mock validation
        $this->facilityRepositoryMock
            ->shouldReceive('codeExists')
            ->once()
            ->with('TEST001', null)
            ->andReturn(false);
        
        $this->facilityRepositoryMock
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::subset(array_merge($data, [
                'created_by_staff_id' => 1,
                'updated_by_staff_id' => 1,
                'facility_uuid' => Mockery::type('string'),
            ])))
            ->andReturn($facility);
        
        $result = $this->facilityService->createFacility($data, 1);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Facility created successfully', $result['message']);
        $this->assertInstanceOf(Facility::class, $result['facility']);
    }

    /** @test */
    public function it_validates_facility_data_before_creation()
    {
        $data = [
            'facility_code' => 'TEST001',
            'facility_name' => 'Test Facility',
            // Missing required fields
        ];
        
        $result = $this->facilityService->validateFacilityData($data);
        
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertNotEmpty($result['errors']);
    }

    /** @test */
    public function it_prevents_duplicate_facility_codes()
    {
        $data = [
            'facility_code' => 'EXISTING001',
            'facility_name' => 'Test Facility',
            'legal_entity_name' => 'Test Entity',
            'facility_type' => 'hospital',
            'facility_tier' => 'tertiary',
            'address_line1' => '123 Test St',
            'city' => 'Test City',
            'state_province' => 'Test State',
            'postal_code' => '12345',
            'country_code' => 'USA',
            'main_phone' => '123-456-7890',
            'operating_hours' => [],
            'available_services' => ['emergency'],
            'data_residency_region' => 'us-east',
            'primary_database_shard' => 'shard-01',
            'operational_status' => 'fully_operational',
        ];
        
        $this->facilityRepositoryMock
            ->shouldReceive('codeExists')
            ->once()
            ->with('EXISTING001', null)
            ->andReturn(true);
        
        $result = $this->facilityService->validateFacilityData($data);
        
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('facility_code', $result['errors']);
    }

    /** @test */
    public function it_can_update_facility()
    {
        $existingFacility = Facility::factory()->make(['id' => 1]);
        
        $data = [
            'facility_name' => 'Updated Facility Name',
            'updated_by_staff_id' => 2,
        ];
        
        $updatedFacility = Facility::factory()->make(array_merge($existingFacility->toArray(), $data));
        
        // Mock getting existing facility
        $this->facilityRepositoryMock
            ->shouldReceive('findById')
            ->once()
            ->with(1, ['parentOrganization', 'createdBy', 'updatedBy'])
            ->andReturn($existingFacility);
        
        // Mock validation
        $this->facilityRepositoryMock
            ->shouldReceive('codeExists')
            ->once()
            ->with($existingFacility->facility_code, 1)
            ->andReturn(false);
        
        // Mock update
        $this->facilityRepositoryMock
            ->shouldReceive('update')
            ->once()
            ->with(1, Mockery::subset($data))
            ->andReturn($updatedFacility);
        
        $result = $this->facilityService->updateFacility(1, $data, 2);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Facility updated successfully', $result['message']);
        $this->assertEquals('Updated Facility Name', $result['facility']->facility_name);
    }

    /** @test */
    public function it_returns_error_when_updating_nonexistent_facility()
    {
        $this->facilityRepositoryMock
            ->shouldReceive('findById')
            ->once()
            ->with(999, ['parentOrganization', 'createdBy', 'updatedBy'])
            ->andReturn(null);
        
        $result = $this->facilityService->updateFacility(999, ['facility_name' => 'Updated'], 1);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Facility not found', $result['message']);
    }

    /** @test */
    public function it_can_delete_facility()
    {
        $facility = Facility::factory()->make(['id' => 1]);
        
        $this->facilityRepositoryMock
            ->shouldReceive('findById')
            ->once()
            ->with(1, ['parentOrganization', 'createdBy', 'updatedBy'])
            ->andReturn($facility);
        
        $this->facilityRepositoryMock
            ->shouldReceive('delete')
            ->once()
            ->with(1)
            ->andReturn(true);
        
        $result = $this->facilityService->deleteFacility(1);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Facility deleted successfully', $result['message']);
    }

    /** @test */
    public function it_can_search_facilities()
    {
        $facilities = new Collection([
            Facility::factory()->make(['facility_name' => 'Test Hospital']),
        ]);
        
        $this->facilityRepositoryMock
            ->shouldReceive('search')
            ->once()
            ->with('Test', 10)
            ->andReturn($facilities);
        
        $result = $this->facilityService->searchFacilities('Test');
        
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
    }

    /** @test */
    public function it_can_get_facilities_by_location()
    {
        $facilities = new Collection([
            Facility::factory()->make(['country_code' => 'USA', 'state_province' => 'CA']),
        ]);
        
        $this->facilityRepositoryMock
            ->shouldReceive('getByLocation')
            ->once()
            ->with('USA', 'CA', null)
            ->andReturn($facilities);
        
        $result = $this->facilityService->getFacilitiesByLocation('USA', 'CA');
        
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
    }

    /** @test */
    public function it_can_update_facility_metrics()
    {
        $facility = Facility::factory()->make(['id' => 1]);
        
        $metrics = [
            'average_wait_time_minutes' => 15.5,
            'patient_satisfaction_score' => 4.2,
            'monthly_patient_volume' => 1000,
        ];
        
        $this->facilityRepositoryMock
            ->shouldReceive('findById')
            ->once()
            ->with(1, ['parentOrganization', 'createdBy', 'updatedBy'])
            ->andReturn($facility);
        
        $this->facilityRepositoryMock
            ->shouldReceive('update')
            ->once()
            ->with(1, Mockery::subset($metrics))
            ->andReturn($facility);
        
        $result = $this->facilityService->updateFacilityMetrics(1, $metrics);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Facility metrics updated successfully', $result['message']);
    }

    /** @test */
    public function it_caches_facility_data()
    {
        $facility = Facility::factory()->make(['id' => 1]);
        
        $this->facilityRepositoryMock
            ->shouldReceive('findById')
            ->once()
            ->with(1, ['parentOrganization', 'createdBy', 'updatedBy'])
            ->andReturn($facility);
        
        // First call should hit repository
        $result1 = $this->facilityService->getFacilityById(1);
        
        // Second call should return cached result
        $result2 = $this->facilityService->getFacilityById(1);
        
        $this->assertEquals($result1, $result2);
    }
}