<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\VisitActor;
use App\Services\VisitActor\VisitActorService;
use App\Repositories\Contracts\VisitActorRepositoryInterface;
use Mockery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class VisitActorServiceTest extends TestCase
{
    /**
     * @var VisitActorService
     */
    protected $visitActorService;

    /**
     * @var Mockery\MockInterface|VisitActorRepositoryInterface
     */
    protected $visitActorRepository;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        // parent::setUp();

        $this->visitActorRepository = Mockery::mock(VisitActorRepositoryInterface::class);
        $this->visitActorService = new VisitActorService($this->visitActorRepository);
    }

    /**
     * Clean up after each test.
     */
    protected function tearDown(): void
    {
        Mockery::close();
        // parent::tearDown();
    }

    /**
     * Test getting all visit actors with pagination.
     */
    public function testGetAllVisitActors(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 15);
        
        $this->visitActorRepository
            ->shouldReceive('paginate')
            ->with(15)
            ->once()
            ->andReturn($paginator);
        
        $result = $this->visitActorService->getAllVisitActors();
        
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
    }

    /**
     * Test getting visit actor by ID successfully.
     */
    public function testGetVisitActorByIdSuccess(): void
    {
        $visitActor = VisitActor::factory()->make(['id' => 1]);
        
        $this->visitActorRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($visitActor);
        
        $result = $this->visitActorService->getVisitActorById(1);
        
        $this->assertInstanceOf(VisitActor::class, $result);
        $this->assertEquals(1, $result->id);
    }

    /**
     * Test getting visit actor by ID when not found.
     */
    public function testGetVisitActorByIdNotFound(): void
    {
        $this->visitActorRepository
            ->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);
        
        $result = $this->visitActorService->getVisitActorById(999);
        
        $this->assertNull($result);
    }

    /**
     * Test creating a visit actor successfully.
     */
    public function testCreateVisitActorSuccess(): void
    {
        $data = [
            'facility_id' => 1,
            'visit_id' => 1,
            'staff_id' => 1,
            'role_at_time' => 'Senior Physician',
            'participation_type' => 'primary_provider',
            'participation_started_at' => now()->toDateTimeString(),
        ];
        
        $visitActor = VisitActor::factory()->make(array_merge($data, ['id' => 1]));
        
        $this->visitActorRepository
            ->shouldReceive('isDuplicateParticipation')
            ->once()
            ->andReturn(false);
        
        $this->visitActorRepository
            ->shouldReceive('create')
            ->with(Mockery::subset($data))
            ->once()
            ->andReturn($visitActor);
        
        $result = $this->visitActorService->createVisitActor($data);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Visit actor created successfully', $result['message']);
        $this->assertInstanceOf(VisitActor::class, $result['data']);
    }

    /**
     * Test creating a visit actor with duplicate participation.
     */
    public function testCreateVisitActorDuplicateParticipation(): void
    {
        $data = [
            'facility_id' => 1,
            'visit_id' => 1,
            'staff_id' => 1,
            'role_at_time' => 'Senior Physician',
            'participation_type' => 'primary_provider',
            'participation_started_at' => now()->toDateTimeString(),
        ];
        
        $this->visitActorRepository
            ->shouldReceive('isDuplicateParticipation')
            ->once()
            ->andReturn(true);
        
        $result = $this->visitActorService->createVisitActor($data);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Duplicate participation detected for this staff member, visit, and start time.', $result['message']);
    }

    /**
     * Test updating a visit actor successfully.
     */
    public function testUpdateVisitActorSuccess(): void
    {
        $visitActor = VisitActor::factory()->make(['id' => 1]);
        $updateData = ['role_at_time' => 'Updated Role'];
        
        $this->visitActorRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($visitActor);
        
        $this->visitActorRepository
            ->shouldReceive('update')
            ->with(1, $updateData)
            ->once()
            ->andReturn($visitActor);
        
        $result = $this->visitActorService->updateVisitActor(1, $updateData);
        
        $this->assertTrue($result['success']);
    }

    /**
     * Test updating a non-existent visit actor.
     */
    public function testUpdateVisitActorNotFound(): void
    {
        $updateData = ['role_at_time' => 'Updated Role'];
        
        $this->visitActorRepository
            ->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);
        
        $result = $this->visitActorService->updateVisitActor(999, $updateData);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Visit actor not found', $result['message']);
    }

    /**
     * Test deleting a visit actor successfully.
     */
    public function testDeleteVisitActorSuccess(): void
    {
        $visitActor = VisitActor::factory()->make([
            'id' => 1,
            'is_billable_provider' => false,
            'provider_charge_amount' => null,
        ]);
        
        $this->visitActorRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($visitActor);
        
        $this->visitActorRepository
            ->shouldReceive('delete')
            ->with(1)
            ->once()
            ->andReturn(true);
        
        $result = $this->visitActorService->deleteVisitActor(1);
        
        $this->assertTrue($result['success']);
    }

    /**
     * Test ending participation successfully.
     */
    public function testEndParticipationSuccess(): void
    {
        $visitActor = VisitActor::factory()->make([
            'id' => 1,
            'participation_started_at' => now()->subHour(),
            'participation_ended_at' => null,
        ]);
        
        $endedData = ['participation_ended_at' => now()->toDateTimeString()];
        
        $this->visitActorRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($visitActor);
        
        $this->visitActorRepository
            ->shouldReceive('endParticipation')
            ->with(1, $endedData)
            ->once()
            ->andReturn($visitActor);
        
        $result = $this->visitActorService->endParticipation(1, $endedData);
        
        $this->assertTrue($result['success']);
    }

    /**
     * Test validation of participation data.
     */
    public function testValidateParticipationData(): void
    {
        $validData = [
            'facility_id' => 1,
            'visit_id' => 1,
            'staff_id' => 1,
            'role_at_time' => 'Test Role',
            'participation_type' => 'primary_provider',
            'participation_started_at' => now()->toDateTimeString(),
        ];
        
        $result = $this->visitActorService->validateParticipationData($validData);
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    /**
     * Test validation of invalid participation data.
     */
    public function testValidateParticipationDataInvalid(): void
    {
        $invalidData = [
            'facility_id' => 'not-an-integer',
            'participation_type' => 'invalid_type',
        ];
        
        $result = $this->visitActorService->validateParticipationData($invalidData);
        
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }
}