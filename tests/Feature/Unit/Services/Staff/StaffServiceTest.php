<?php

namespace Tests\Unit\Services\Staff;

use Tests\TestCase;
use App\Models\Staff;
use App\Repositories\Contracts\StaffRepositoryInterface;
use App\Services\Staff\StaffService;
use Mockery;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StaffServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StaffService $staffService;
    protected $staffRepositoryMock;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->staffRepositoryMock = Mockery::mock(StaffRepositoryInterface::class);
        $this->staffService = new StaffService($this->staffRepositoryMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_returns_staff_by_id()
    {
        $staff = Staff::factory()->create();
        
        $this->staffRepositoryMock
            ->shouldReceive('find')
            ->with($staff->id)
            ->once()
            ->andReturn($staff);
        
        $result = $this->staffService->getStaffById($staff->id);
        
        $this->assertEquals($staff->id, $result->id);
    }

    /** @test */
    public function it_returns_null_when_staff_not_found()
    {
        $this->staffRepositoryMock
            ->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);
        
        $result = $this->staffService->getStaffById(999);
        
        $this->assertNull($result);
    }

    /** @test */
    public function it_creates_staff_successfully()
    {
        $staffData = [
            'user_id' => 1,
            'employee_id' => 'EMP001',
            'professional_title' => 'Doctor',
            'employment_status' => 'active',
            'global_role_level' => 'attending_physician'
        ];
        
        $staff = Staff::factory()->make($staffData);
        
        $this->staffRepositoryMock
            ->shouldReceive('create')
            ->with(Mockery::on(function ($data) use ($staffData) {
                return isset($data['staff_uuid']) && 
                       $data['employee_id'] === $staffData['employee_id'];
            }))
            ->once()
            ->andReturn($staff);
        
        $result = $this->staffService->createStaff($staffData);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Staff created successfully.', $result['message']);
    }

    /** @test */
    public function it_handles_exception_when_creating_staff()
    {
        $staffData = ['employee_id' => 'EMP001'];
        
        $this->staffRepositoryMock
            ->shouldReceive('create')
            ->once()
            ->andThrow(new \Exception('Database error'));
        
        $result = $this->staffService->createStaff($staffData);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('error', $result['message']);
    }

    /** @test */
    public function it_updates_staff_successfully()
    {
        $staff = Staff::factory()->create();
        $updateData = ['professional_title' => 'Senior Doctor'];
        
        $this->staffRepositoryMock
            ->shouldReceive('find')
            ->with($staff->id)
            ->once()
            ->andReturn($staff);
        
        $this->staffRepositoryMock
            ->shouldReceive('update')
            ->with($staff->id, Mockery::on(function ($data) use ($updateData) {
                return $data['professional_title'] === $updateData['professional_title'] &&
                       isset($data['updated_by_staff_id']);
            }))
            ->once()
            ->andReturn(true);
        
        $result = $this->staffService->updateStaff($staff->id, $updateData);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Staff updated successfully.', $result['message']);
    }

    /** @test */
    public function it_returns_error_when_updating_nonexistent_staff()
    {
        $this->staffRepositoryMock
            ->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);
        
        $result = $this->staffService->updateStaff(999, ['title' => 'Doctor']);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Staff not found.', $result['message']);
    }

    /** @test */
    public function it_deletes_staff_successfully()
    {
        $staff = Staff::factory()->create(['employment_status' => 'terminated']);
        
        $this->staffRepositoryMock
            ->shouldReceive('find')
            ->with($staff->id)
            ->once()
            ->andReturn($staff);
        
        $this->staffRepositoryMock
            ->shouldReceive('delete')
            ->with($staff->id)
            ->once()
            ->andReturn(true);
        
        $result = $this->staffService->deleteStaff($staff->id);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Staff deleted successfully.', $result['message']);
    }

    /** @test */
    public function it_prevents_deletion_of_active_staff()
    {
        $staff = Staff::factory()->create(['employment_status' => 'active']);
        
        $this->staffRepositoryMock
            ->shouldReceive('find')
            ->with($staff->id)
            ->once()
            ->andReturn($staff);
        
        $result = $this->staffService->deleteStaff($staff->id);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('active', $result['message']);
    }

    /** @test */
    public function it_updates_employment_status_successfully()
    {
        $staff = Staff::factory()->create(['employment_status' => 'active']);
        
        $this->staffRepositoryMock
            ->shouldReceive('find')
            ->with($staff->id)
            ->once()
            ->andReturn($staff);
        
        $this->staffRepositoryMock
            ->shouldReceive('update')
            ->with($staff->id, Mockery::on(function ($data) {
                return $data['employment_status'] === 'suspended' &&
                       isset($data['updated_by_staff_id']);
            }))
            ->once()
            ->andReturn(true);
        
        $result = $this->staffService->updateEmploymentStatus($staff->id, 'suspended', 'Disciplinary action');
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Employment status updated successfully.', $result['message']);
    }

    /** @test */
    public function it_validates_staff_action_correctly()
    {
        $staff = Staff::factory()->create([
            'employment_status' => 'active',
            'can_order_controlled_substances' => true,
            'prescribing_authority' => ['Schedule II', 'Schedule III']
        ]);
        
        $this->staffRepositoryMock
            ->shouldReceive('find')
            ->with($staff->id)
            ->once()
            ->andReturn($staff);
        
        $result = $this->staffService->validateStaffAction($staff->id, 'prescribe_medication');
        
        $this->assertTrue($result['valid']);
    }

    /** @test */
    public function it_returns_false_for_invalid_staff_action()
    {
        $staff = Staff::factory()->create([
            'employment_status' => 'suspended'
        ]);
        
        $this->staffRepositoryMock
            ->shouldReceive('find')
            ->with($staff->id)
            ->once()
            ->andReturn($staff);
        
        $result = $this->staffService->validateStaffAction($staff->id, 'prescribe_medication');
        
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('not active', $result['message']);
    }

    /** @test */
    public function it_checks_staff_privilege_correctly()
    {
        $staff = Staff::factory()->create([
            'can_order_controlled_substances' => true,
            'global_role_level' => 'attending_physician',
            'prescribing_authority' => ['Schedule II']
        ]);
        
        $this->staffRepositoryMock
            ->shouldReceive('find')
            ->with($staff->id)
            ->once()
            ->andReturn($staff);
        
        $result = $this->staffService->checkStaffPrivilege($staff->id, 'prescribe_controlled_substances');
        
        $this->assertTrue($result);
    }

    /** @test */
    public function it_handles_bulk_status_update()
    {
        $staffIds = [1, 2, 3];
        
        // Mock multiple calls to updateEmploymentStatus
        $this->staffRepositoryMock
            ->shouldReceive('find')
            ->times(3)
            ->andReturn(
                Staff::factory()->make(['id' => 1]),
                Staff::factory()->make(['id' => 2]),
                Staff::factory()->make(['id' => 3])
            );
        
        $this->staffRepositoryMock
            ->shouldReceive('update')
            ->times(3)
            ->andReturn(true, true, false);
        
        $result = $this->staffService->bulkUpdateStatus($staffIds, 'active');
        
        $this->assertFalse($result['success']); // One failed
        $this->assertEquals(2, $result['data']['updated']);
        $this->assertEquals(1, $result['data']['failed']);
    }
}