<?php

namespace Tests\Unit;

use App\Models\Department;
use App\Repositories\Contracts\DepartmentRepositoryInterface;
use App\Services\Department\DepartmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class DepartmentServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var DepartmentService
     */
    protected $departmentService;

    /**
     * @var Mockery\MockInterface|DepartmentRepositoryInterface
     */
    protected $repositoryMock;

    /**
     * Set up the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create mock repository
        $this->repositoryMock = Mockery::mock(DepartmentRepositoryInterface::class);
        
        // Create service instance with mocked repository
        $this->departmentService = new DepartmentService($this->repositoryMock);
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
     * Test getting all departments successfully.
     *
     * @return void
     */
    public function test_get_all_departments_successfully(): void
    {
        // Mock repository response
        $mockDepartments = Department::factory()->count(5)->make();
        $this->repositoryMock->shouldReceive('getAllPaginated')
            ->once()
            ->with([], 20)
            ->andReturn($mockDepartments);

        // Call service method
        $result = $this->departmentService->getAllDepartments();

        // Assertions
        $this->assertTrue($result['success']);
        $this->assertEquals('Departments retrieved successfully.', $result['message']);
        $this->assertEquals($mockDepartments, $result['data']);
    }

    /**
     * Test getting department by UUID successfully.
     *
     * @return void
     */
    public function test_get_department_by_uuid_successfully(): void
    {
        $uuid = Str::uuid()->toString();
        $mockDepartment = Department::factory()->make(['department_uuid' => $uuid]);

        // Mock repository response
        $this->repositoryMock->shouldReceive('findByUuid')
            ->once()
            ->with($uuid)
            ->andReturn($mockDepartment);

        // Call service method
        $result = $this->departmentService->getDepartmentByUuid($uuid);

        // Assertions
        $this->assertTrue($result['success']);
        $this->assertEquals('Department retrieved successfully.', $result['message']);
        $this->assertEquals($mockDepartment, $result['data']);
    }

    /**
     * Test getting department by UUID when not found.
     *
     * @return void
     */
    public function test_get_department_by_uuid_not_found(): void
    {
        $uuid = Str::uuid()->toString();

        // Mock repository response
        $this->repositoryMock->shouldReceive('findByUuid')
            ->once()
            ->with($uuid)
            ->andReturn(null);

        // Call service method
        $result = $this->departmentService->getDepartmentByUuid($uuid);

        // Assertions
        $this->assertFalse($result['success']);
        $this->assertEquals('Department not found.', $result['message']);
        $this->assertEquals(404, $result['status']);
    }

    /**
     * Test creating a department successfully.
     *
     * @return void
     */
    public function test_create_department_successfully(): void
    {
        $departmentData = [
            'facility_id' => 1,
            'department_code' => 'EMERG',
            'department_name' => 'Emergency Department',
            'department_type' => 'emergency',
            'max_concurrent_capacity' => 50,
            'status' => 'active',
        ];

        $mockDepartment = Department::factory()->make($departmentData);

        // Mock repository methods
        $this->repositoryMock->shouldReceive('isDepartmentCodeUnique')
            ->once()
            ->with($departmentData['department_code'], $departmentData['facility_id'], null)
            ->andReturn(true);

        $this->repositoryMock->shouldReceive('create')
            ->once()
            ->with($departmentData)
            ->andReturn($mockDepartment);

        // Call service method
        $result = $this->departmentService->createDepartment($departmentData);

        // Assertions
        $this->assertTrue($result['success']);
        $this->assertEquals('Department created successfully.', $result['message']);
        $this->assertEquals($mockDepartment, $result['data']);
        $this->assertEquals(201, $result['status']);
    }

    /**
     * Test creating a department with duplicate code.
     *
     * @return void
     */
    public function test_create_department_with_duplicate_code(): void
    {
        $departmentData = [
            'facility_id' => 1,
            'department_code' => 'EMERG',
            'department_name' => 'Emergency Department',
            'department_type' => 'emergency',
        ];

        // Mock repository method
        $this->repositoryMock->shouldReceive('isDepartmentCodeUnique')
            ->once()
            ->with($departmentData['department_code'], $departmentData['facility_id'], null)
            ->andReturn(false);

        // Call service method
        $result = $this->departmentService->createDepartment($departmentData);

        // Assertions
        $this->assertFalse($result['success']);
        $this->assertEquals('Department code already exists in this facility.', $result['message']);
        $this->assertEquals(422, $result['status']);
    }

    /**
     * Test updating a department successfully.
     *
     * @return void
     */
    public function test_update_department_successfully(): void
    {
        $uuid = Str::uuid()->toString();
        $departmentId = 1;
        
        $existingDepartment = Department::factory()->make([
            'id' => $departmentId,
            'department_uuid' => $uuid,
            'facility_id' => 1,
            'department_code' => 'OLDCODE',
        ]);

        $updateData = [
            'department_code' => 'NEWCODE',
            'department_name' => 'Updated Department Name',
        ];

        // Mock repository methods
        $this->repositoryMock->shouldReceive('findByUuid')
            ->once()
            ->with($uuid)
            ->andReturn($existingDepartment);

        $this->repositoryMock->shouldReceive('isDepartmentCodeUnique')
            ->once()
            ->with($updateData['department_code'], $existingDepartment->facility_id, $departmentId)
            ->andReturn(true);

        $this->repositoryMock->shouldReceive('update')
            ->once()
            ->with($existingDepartment, $updateData)
            ->andReturn(true);

        // Mock refresh method
        $existingDepartment->shouldReceive('refresh')->andReturnSelf();

        // Call service method
        $result = $this->departmentService->updateDepartment($uuid, $updateData);

        // Assertions
        $this->assertTrue($result['success']);
        $this->assertEquals('Department updated successfully.', $result['message']);
    }

    /**
     * Test deleting a department successfully.
     *
     * @return void
     */
    public function test_delete_department_successfully(): void
    {
        $uuid = Str::uuid()->toString();
        $department = Department::factory()->make(['department_uuid' => $uuid]);

        // Mock repository methods
        $this->repositoryMock->shouldReceive('findByUuid')
            ->once()
            ->with($uuid)
            ->andReturn($department);

        $this->repositoryMock->shouldReceive('delete')
            ->once()
            ->with($department)
            ->andReturn(true);

        // Mock child departments relationship
        $department->shouldReceive('childDepartments')
            ->once()
            ->andReturn(collect([]));

        // Call service method
        $result = $this->departmentService->deleteDepartment($uuid);

        // Assertions
        $this->assertTrue($result['success']);
        $this->assertEquals('Department deleted successfully.', $result['message']);
    }

    /**
     * Test validating department data.
     *
     * @return void
     */
    public function test_validate_department_data(): void
    {
        // Test valid data
        $validData = [
            'facility_id' => 1,
            'department_code' => 'EMERG',
            'department_name' => 'Emergency',
            'department_type' => 'emergency',
            'bed_count' => 10,
        ];

        $result = $this->departmentService->validateDepartmentData($validData);
        $this->assertTrue($result['success']);

        // Test invalid data
        $invalidData = [
            'facility_id' => null,
            'department_type' => 'invalid_type',
        ];

        $result = $this->departmentService->validateDepartmentData($invalidData);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertEquals(422, $result['status']);
    }
}