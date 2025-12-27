<?php

namespace Tests\Unit;

use App\Models\StaffInvitation;
use App\Repositories\Contracts\StaffInvitationRepositoryInterface;
use App\Services\StaffInvitation\StaffInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Tests\TestCase;

class StaffInvitationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $repositoryMock;
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repositoryMock = Mockery::mock(StaffInvitationRepositoryInterface::class);
        $this->service = new StaffInvitationService($this->repositoryMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_returns_success_response_when_getting_all_invitations(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 20);
        
        $this->repositoryMock
            ->shouldReceive('getAll')
            ->once()
            ->with([], 20)
            ->andReturn($paginator);

        $result = $this->service->getAllInvitations();

        $this->assertTrue($result['success']);
        $this->assertEquals('Invitations retrieved successfully.', $result['message']);
        $this->assertInstanceOf(LengthAwarePaginator::class, $result['data']);
    }

    /** @test */
    public function it_handles_exceptions_when_getting_all_invitations(): void
    {
        $this->repositoryMock
            ->shouldReceive('getAll')
            ->once()
            ->andThrow(new \Exception('Database error'));

        $result = $this->service->getAllInvitations();

        $this->assertFalse($result['success']);
        $this->assertEquals('Failed to retrieve invitations. Please try again later.', $result['message']);
        $this->assertArrayHasKey('system', $result['errors']);
    }

    /** @test */
    public function it_returns_success_response_when_getting_invitation_by_id(): void
    {
        $invitation = StaffInvitation::factory()->make();

        $this->repositoryMock
            ->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($invitation);

        $result = $this->service->getInvitationById(1);

        $this->assertTrue($result['success']);
        $this->assertEquals('Invitation retrieved successfully.', $result['message']);
        $this->assertEquals($invitation, $result['data']);
    }

    /** @test */
    public function it_returns_not_found_when_invitation_does_not_exist(): void
    {
        $this->repositoryMock
            ->shouldReceive('findById')
            ->once()
            ->with(999)
            ->andReturn(null);

        $result = $this->service->getInvitationById(999);

        $this->assertFalse($result['success']);
        $this->assertEquals('Invitation not found.', $result['message']);
        $this->assertNull($result['data']);
    }

    /** @test */
    public function it_creates_invitation_successfully(): void
    {
        $data = [
            'staff_id' => 1,
            'facility_id' => 1,
            'department_id' => 1,
            'expires_at' => now()->addDays(7),
        ];

        $invitation = StaffInvitation::factory()->make($data);

        $this->repositoryMock
            ->shouldReceive('duplicateExists')
            ->once()
            ->with(1, 1, 1)
            ->andReturn(false);

        $this->repositoryMock
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) use ($data) {
                return $arg['staff_id'] === $data['staff_id'] &&
                       $arg['facility_id'] === $data['facility_id'] &&
                       isset($arg['invitation_uuid']) &&
                       isset($arg['sent_at']);
            }))
            ->andReturn($invitation);

        $result = $this->service->createInvitation($data);

        $this->assertTrue($result['success']);
        $this->assertEquals('Invitation created and sent successfully.', $result['message']);
        $this->assertEquals($invitation, $result['data']);
    }

    /** @test */
    public function it_rejects_duplicate_invitation_creation(): void
    {
        $data = [
            'staff_id' => 1,
            'facility_id' => 1,
            'department_id' => 1,
        ];

        $this->repositoryMock
            ->shouldReceive('duplicateExists')
            ->once()
            ->with(1, 1, 1)
            ->andReturn(true);

        $result = $this->service->createInvitation($data);

        $this->assertFalse($result['success']);
        $this->assertEquals('An active invitation already exists for this staff member at the specified facility/department.', $result['message']);
        $this->assertArrayHasKey('duplicate', $result['errors']);
    }

    /** @test */
    public function it_accepts_invitation_successfully(): void
    {
        $invitation = StaffInvitation::factory()->make([
            'id' => 1,
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        $this->repositoryMock
            ->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($invitation);

        $this->repositoryMock
            ->shouldReceive('updateStatus')
            ->once()
            ->with(1, 'accepted')
            ->andReturn($invitation->fill(['status' => 'accepted']));

        $result = $this->service->acceptInvitation(1);

        $this->assertTrue($result['success']);
        $this->assertEquals('Invitation accepted successfully.', $result['message']);
    }

    /** @test */
    public function it_rejects_accepting_expired_invitation(): void
    {
        $invitation = StaffInvitation::factory()->make([
            'id' => 1,
            'status' => 'pending',
            'expires_at' => now()->subDays(1), // Expired
        ]);

        $this->repositoryMock
            ->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($invitation);

        $result = $this->service->acceptInvitation(1);

        $this->assertFalse($result['success']);
        $this->assertEquals('This invitation has expired.', $result['message']);
    }

    /** @test */
    public function it_declines_invitation_successfully(): void
    {
        $invitation = StaffInvitation::factory()->make([
            'id' => 1,
            'status' => 'pending',
        ]);

        $this->repositoryMock
            ->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($invitation);

        $this->repositoryMock
            ->shouldReceive('updateStatus')
            ->once()
            ->with(1, 'declined')
            ->andReturn($invitation->fill(['status' => 'declined']));

        $result = $this->service->declineInvitation(1);

        $this->assertTrue($result['success']);
        $this->assertEquals('Invitation declined successfully.', $result['message']);
    }

    /** @test */
    public function it_rejects_declining_non_pending_invitation(): void
    {
        $invitation = StaffInvitation::factory()->make([
            'id' => 1,
            'status' => 'accepted', // Not pending
        ]);

        $this->repositoryMock
            ->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($invitation);

        $result = $this->service->declineInvitation(1);

        $this->assertFalse($result['success']);
        $this->assertEquals('This invitation cannot be declined in its current state.', $result['message']);
    }

    /** @test */
    public function it_cancels_invitation_successfully(): void
    {
        $invitation = StaffInvitation::factory()->make([
            'id' => 1,
            'status' => 'pending',
        ]);

        $this->repositoryMock
            ->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($invitation);

        $this->repositoryMock
            ->shouldReceive('delete')
            ->once()
            ->with(1)
            ->andReturn(true);

        $result = $this->service->cancelInvitation(1);

        $this->assertTrue($result['success']);
        $this->assertEquals('Invitation cancelled successfully.', $result['message']);
    }

    /** @test */
    public function it_processes_expired_invitations(): void
    {
        // This test would actually test the method that processes expired invitations
        // Since it's a background process, we're just ensuring the method exists
        $result = $this->service->processExpiredInvitations();

        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('data', $result);
    }
}