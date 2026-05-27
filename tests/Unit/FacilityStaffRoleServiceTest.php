<?php

namespace Tests\Unit;

use App\Models\FacilityStaffRole;
use App\Repositories\Contracts\FacilityStaffRoleRepositoryInterface;
use App\Services\FacilityStaffRole\FacilityStaffRoleService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class FacilityStaffRoleServiceTest extends TestCase
{
    /**
     * Service instance
     *
     * @var FacilityStaffRoleService
     */
    private $service;

    /**
     * Mock repository
     *
     * @var MockInterface|FacilityStaffRoleRepositoryInterface
     */
    private $repositoryMock;

    /**
     * Set up the test
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->repositoryMock = Mockery::mock(FacilityStaffRoleRepositoryInterface::class);
        $this->service = new FacilityStaffRoleService($this->repositoryMock);
    }

    /**
     * Tear down the test
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test get assignment by ID success
     */
    public function test_get_assignment_by_id_success(): void
    {
        $assignment = FacilityStaffRole::factory()->make(['id' => 1]);
        
        $this->repositoryMock
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($assignment);
        
        $result = $this->service->getAssignmentById(1);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Role assignment retrieved successfully', $result['message']);
        $this->assertSame($assignment, $result['data']);
    }

    /**
     * Test get assignment by ID not found
     */
    public function test_get_assignment_by_id_not_found(): void
    {
        $this->repositoryMock
            ->shouldReceive('findById')
            ->with(999)
            ->once()
            ->andReturn(null);
        
        $result = $this->service->getAssignmentById(999);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Role assignment not found', $result['message']);
        $this->assertNull($result['data']);
    }

    /**
     * Test get all assignments
     */
    public function test_get_all_assignments(): void
    {
        $assignments = new Collection([
            FacilityStaffRole::factory()->make(),
            FacilityStaffRole::factory()->make()
        ]);
        
        $this->repositoryMock
            ->shouldReceive('all')
            ->with(['facility_id' => 1])
            ->once()
            ->andReturn($assignments);
        
        $result = $this->service->getAllAssignments(['facility_id' => 1]);
        
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
    }

    /**
     * Test get paginated assignments
     */
    public function test_get_paginated_assignments(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 15);
        
        $this->repositoryMock
            ->shouldReceive('paginate')
            ->with(15, [])
            ->once()
            ->andReturn($paginator);
        
        $result = $this->service->getPaginatedAssignments();
        
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
    }

    /**
     * Test create assignment success
     */
    public function test_create_assignment_success(): void
    {
        $data = [
            'facility_id' => 1,
            'staff_id' => 1,
            'role_code' => 'attending_physician',
            'effective_from' => '2024-12-31'
        ];
        
        $assignment = FacilityStaffRole::factory()->make($data);
        
        $this->repositoryMock
            ->shouldReceive('duplicateAssignmentExists')
            ->with(1, 1, 'attending_physician', '2024-12-31', null)
            ->once()
            ->andReturn(false);
        
        $this->repositoryMock
            ->shouldReceive('create')
            ->with(Mockery::on(function ($arg) use ($data) {
                return isset($arg['assignment_uuid']) && 
                       $arg['facility_id'] === $data['facility_id'];
            }))
            ->once()
            ->andReturn($assignment);
        
        $result = $this->service->createAssignment($data);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Role assignment created successfully', $result['message']);
    }

    /**
     * Test create assignment with duplicate
     */
    public function test_create_assignment_with_duplicate(): void
    {
        $data = [
            'facility_id' => 1,
            'staff_id' => 1,
            'role_code' => 'attending_physician',
            'effective_from' => '2024-12-31'
        ];
        
        $this->repositoryMock
            ->shouldReceive('duplicateAssignmentExists')
            ->with(1, 1, 'attending_physician', '2024-12-31', null)
            ->once()
            ->andReturn(true);
        
        $result = $this->service->createAssignment($data);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already exists', $result['message']);
    }

    /**
     * Test update assignment success
     */
    public function test_update_assignment_success(): void
    {
        $assignment = FacilityStaffRole::factory()->make(['id' => 1]);
        $data = ['role_code' => 'surgeon'];
        
        $this->repositoryMock
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($assignment);
        
        $this->repositoryMock
            ->shouldReceive('update')
            ->with($assignment, $data)
            ->once()
            ->andReturn($assignment);
        
        $result = $this->service->updateAssignment(1, $data);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Role assignment updated successfully', $result['message']);
    }

    /**
     * Test update assignment not found
     */
    public function test_update_assignment_not_found(): void
    {
        $this->repositoryMock
            ->shouldReceive('findById')
            ->with(999)
            ->once()
            ->andReturn(null);
        
        $result = $this->service->updateAssignment(999, ['role_code' => 'surgeon']);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Role assignment not found', $result['message']);
    }

    /**
     * Test delete assignment success
     */
    public function test_delete_assignment_success(): void
    {
        $assignment = FacilityStaffRole::factory()->make([
            'id' => 1,
            'assignment_status' => 'active'
        ]);
        
        $this->repositoryMock
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($assignment);
        
        $this->repositoryMock
            ->shouldReceive('updateStatus')
            ->with($assignment, 'terminated', ['effective_to' => Mockery::type('string')])
            ->once()
            ->andReturn($assignment);
        
        $result = $this->service->deleteAssignment(1);
        
        $this->assertTrue($result);
    }

    /**
     * Test delete assignment not found
     */
    public function test_delete_assignment_not_found(): void
    {
        $this->repositoryMock
            ->shouldReceive('findById')
            ->with(999)
            ->once()
            ->andReturn(null);
        
        $result = $this->service->deleteAssignment(999);
        
        $this->assertFalse($result);
    }

    /**
     * Test update assignment status success
     */
    public function test_update_assignment_status_success(): void
    {
        $assignment = FacilityStaffRole::factory()->make(['id' => 1]);
        
        $this->repositoryMock
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($assignment);
        
        $this->repositoryMock
            ->shouldReceive('updateStatus')
            ->with($assignment, 'on_leave', [])
            ->once()
            ->andReturn($assignment);
        
        $result = $this->service->updateAssignmentStatus(1, 'on_leave');
        
        $this->assertTrue($result['success']);
    }

    /**
     * Test update assignment status invalid status
     */
    public function test_update_assignment_status_invalid(): void
    {
        $result = $this->service->updateAssignmentStatus(1, 'invalid_status');
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid assignment status', $result['message']);
    }

    /**
     * Test validate assignment data success
     */
    public function test_validate_assignment_data_success(): void
    {
        $data = [
            'facility_id' => 1,
            'staff_id' => 1,
            'role_code' => 'attending_physician',
            'effective_from' => '2024-12-31'
        ];
        
        $this->repositoryMock
            ->shouldReceive('duplicateAssignmentExists')
            ->with(1, 1, 'attending_physician', '2024-12-31', null)
            ->once()
            ->andReturn(false);
        
        $result = $this->service->validateAssignmentData($data);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Validation passed', $result['message']);
    }

    /**
     * Test validate assignment data failure
     */
    public function test_validate_assignment_data_failure(): void
    {
        $data = [
            'facility_id' => 1,
            'staff_id' => 1,
            'role_code' => 'invalid_role',
            'effective_from' => '2024-12-31'
        ];
        
        $result = $this->service->validateAssignmentData($data);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Validation failed', $result['message']);
        $this->assertArrayHasKey('role_code', $result['errors']);
    }
}