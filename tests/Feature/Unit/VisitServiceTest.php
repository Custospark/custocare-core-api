<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Visit;
use App\Services\Visit\VisitService;
use App\Repositories\Contracts\VisitRepositoryInterface;
use Mockery;
use Illuminate\Foundation\Testing\RefreshDatabase;

class VisitServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var VisitService
     */
    protected $visitService;

    /**
     * @var Mockery\MockInterface|VisitRepositoryInterface
     */
    protected $visitRepositoryMock;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->visitRepositoryMock = Mockery::mock(VisitRepositoryInterface::class);
        $this->visitService = new VisitService($this->visitRepositoryMock);
    }

    /**
     * Clean up the test environment.
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test get all visits successfully.
     */
    public function testGetAllVisitsSuccessfully()
    {
        $paginatedVisits = Visit::factory()->count(10)->make();
        
        $this->visitRepositoryMock->shouldReceive('getAllPaginated')
            ->once()
            ->with(15, [])
            ->andReturn($paginatedVisits);

        $result = $this->visitService->getAllVisits();

        $this->assertTrue($result['success']);
        $this->assertEquals('Visits retrieved successfully.', $result['message']);
        $this->assertEquals($paginatedVisits, $result['data']);
    }

    /**
     * Test get visit by UUID successfully.
     */
    public function testGetVisitByUuidSuccessfully()
    {
        $visit = Visit::factory()->create(['visit_uuid' => 'test-uuid-123']);
        
        $this->visitRepositoryMock->shouldReceive('findByUuid')
            ->once()
            ->with('test-uuid-123')
            ->andReturn($visit);

        $result = $this->visitService->getVisitByUuid('test-uuid-123');

        $this->assertTrue($result['success']);
        $this->assertEquals('Visit retrieved successfully.', $result['message']);
        $this->assertEquals($visit, $result['data']);
    }

    /**
     * Test get visit by UUID when not found.
     */
    public function testGetVisitByUuidNotFound()
    {
        $this->visitRepositoryMock->shouldReceive('findByUuid')
            ->once()
            ->with('non-existent-uuid')
            ->andReturn(null);

        $result = $this->visitService->getVisitByUuid('non-existent-uuid');

        $this->assertFalse($result['success']);
        $this->assertEquals('Visit not found.', $result['message']);
        $this->assertArrayHasKey('errors', $result);
    }

    /**
     * Test create visit successfully.
     */
    public function testCreateVisitSuccessfully()
    {
        $visitData = [
            'facility_id' => 1,
            'patient_id' => 1,
            'visit_type' => 'outpatient',
            'arrived_at' => now(),
            'chief_complaints' => ['Headache', 'Fever'],
        ];

        $createdVisit = Visit::factory()->create($visitData);
        
        $this->visitRepositoryMock->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) use ($visitData) {
                return array_intersect_key($data, $visitData) == $visitData;
            }))
            ->andReturn($createdVisit);

        $result = $this->visitService->createVisit($visitData, 1);

        $this->assertTrue($result['success']);
        $this->assertEquals('Visit created successfully.', $result['message']);
        $this->assertEquals($createdVisit, $result['data']);
    }

    /**
     * Test update visit successfully.
     */
    public function testUpdateVisitSuccessfully()
    {
        $visit = Visit::factory()->create(['visit_uuid' => 'test-uuid-123']);
        $updateData = ['acuity_score' => 2];
        $updatedVisit = clone $visit;
        $updatedVisit->acuity_score = 2;
        
        $this->visitRepositoryMock->shouldReceive('findByUuid')
            ->once()
            ->with('test-uuid-123')
            ->andReturn($visit);
        
        $this->visitRepositoryMock->shouldReceive('update')
            ->once()
            ->with($visit, $updateData)
            ->andReturn($updatedVisit);

        $result = $this->visitService->updateVisit('test-uuid-123', $updateData, 1);

        $this->assertTrue($result['success']);
        $this->assertEquals('Visit updated successfully.', $result['message']);
        $this->assertEquals($updatedVisit, $result['data']);
    }

    /**
     * Test update visit when not found.
     */
    public function testUpdateVisitNotFound()
    {
        $this->visitRepositoryMock->shouldReceive('findByUuid')
            ->once()
            ->with('non-existent-uuid')
            ->andReturn(null);

        $result = $this->visitService->updateVisit('non-existent-uuid', ['acuity_score' => 2], 1);

        $this->assertFalse($result['success']);
        $this->assertEquals('Visit not found.', $result['message']);
    }

    /**
     * Test delete visit successfully.
     */
    public function testDeleteVisitSuccessfully()
    {
        $visit = Visit::factory()->create(['visit_uuid' => 'test-uuid-123', 'status' => 'completed']);
        
        $this->visitRepositoryMock->shouldReceive('findByUuid')
            ->once()
            ->with('test-uuid-123')
            ->andReturn($visit);
        
        $this->visitRepositoryMock->shouldReceive('delete')
            ->once()
            ->with($visit)
            ->andReturn(true);

        $result = $this->visitService->deleteVisit('test-uuid-123', 1);

        $this->assertTrue($result['success']);
        $this->assertEquals('Visit deleted successfully.', $result['message']);
    }

    /**
     * Test delete active visit should fail.
     */
    public function testDeleteActiveVisitFails()
    {
        $visit = Visit::factory()->create(['visit_uuid' => 'test-uuid-123', 'status' => 'active']);
        
        $this->visitRepositoryMock->shouldReceive('findByUuid')
            ->once()
            ->with('test-uuid-123')
            ->andReturn($visit);

        $result = $this->visitService->deleteVisit('test-uuid-123', 1);

        $this->assertFalse($result['success']);
        $this->assertEquals('Cannot delete an active visit. Please cancel it first.', $result['message']);
    }

    /**
     * Test update visit phase successfully.
     */
    public function testUpdateVisitPhaseSuccessfully()
    {
        $visit = Visit::factory()->create(['visit_uuid' => 'test-uuid-123', 'status' => 'active']);
        $updatedVisit = clone $visit;
        $updatedVisit->current_phase = 'consultation';
        
        $this->visitRepositoryMock->shouldReceive('findByUuid')
            ->once()
            ->with('test-uuid-123')
            ->andReturn($visit);
        
        $this->visitRepositoryMock->shouldReceive('updatePhase')
            ->once()
            ->with($visit, 'consultation', [])
            ->andReturn($updatedVisit);

        $result = $this->visitService->updateVisitPhase('test-uuid-123', 'consultation');

        $this->assertTrue($result['success']);
        $this->assertEquals('Visit phase updated successfully.', $result['message']);
        $this->assertEquals($updatedVisit, $result['data']);
    }

    /**
     * Test discharge visit successfully.
     */
    public function testDischargeVisitSuccessfully()
    {
        $visit = Visit::factory()->create(['visit_uuid' => 'test-uuid-123', 'status' => 'active']);
        $dischargeData = [
            'discharge_disposition' => 'home',
            'discharge_instructions' => 'Rest and hydrate',
        ];
        $dischargedVisit = clone $visit;
        $dischargedVisit->status = 'completed';
        $dischargedVisit->discharge_disposition = 'home';
        
        $this->visitRepositoryMock->shouldReceive('findByUuid')
            ->once()
            ->with('test-uuid-123')
            ->andReturn($visit);
        
        $this->visitRepositoryMock->shouldReceive('discharge')
            ->once()
            ->with($visit, Mockery::subset($dischargeData))
            ->andReturn($dischargedVisit);

        $result = $this->visitService->dischargeVisit('test-uuid-123', $dischargeData, 1);

        $this->assertTrue($result['success']);
        $this->assertEquals('Visit discharged successfully.', $result['message']);
        $this->assertEquals($dischargedVisit, $result['data']);
    }

    /**
     * Test get long waiting visits.
     */
    public function testGetLongWaitingVisits()
    {
        $longWaitingVisits = Visit::factory()->count(5)->make();
        
        $this->visitRepositoryMock->shouldReceive('getLongWaitingVisits')
            ->once()
            ->with(30, null)
            ->andReturn($longWaitingVisits);

        $result = $this->visitService->getLongWaitingVisits(30);

        $this->assertTrue($result['success']);
        $this->assertEquals('Long waiting visits retrieved successfully.', $result['message']);
        $this->assertEquals($longWaitingVisits, $result['data']);
    }
}