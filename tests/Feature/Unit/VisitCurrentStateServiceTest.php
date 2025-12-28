<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\VisitCurrentState\VisitCurrentStateService;
use App\Repositories\Contracts\VisitCurrentStateRepositoryInterface;
use App\Models\VisitCurrentState;
use Mockery;
use Illuminate\Database\Eloquent\Collection;

class VisitCurrentStateServiceTest extends TestCase
{
    protected $repositoryMock;
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->repositoryMock = Mockery::mock(VisitCurrentStateRepositoryInterface::class);
        $this->service = new VisitCurrentStateService($this->repositoryMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_returns_visit_current_state_by_id_successfully()
    {
        $visitCurrentState = VisitCurrentState::factory()->make(['id' => 1]);
        
        $this->repositoryMock
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($visitCurrentState);
        
        $result = $this->service->getVisitCurrentState(1);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Visit current state retrieved successfully.', $result['message']);
        $this->assertEquals($visitCurrentState, $result['data']);
        $this->assertEquals(200, $result['status']);
    }

    /** @test */
    public function it_returns_not_found_when_visit_current_state_does_not_exist()
    {
        $this->repositoryMock
            ->shouldReceive('findById')
            ->with(999)
            ->once()
            ->andReturn(null);
        
        $result = $this->service->getVisitCurrentState(999);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Visit current state not found.', $result['message']);
        $this->assertEquals(404, $result['status']);
    }

    /** @test */
    public function it_creates_visit_current_state_successfully()
    {
        $data = [
            'visit_id' => 1,
            'facility_id' => 1,
            'patient_id' => 1,
            'current_phase' => 'registration',
            'acuity_score' => 3
        ];
        
        $visitCurrentState = VisitCurrentState::factory()->make(array_merge($data, ['id' => 1]));
        
        $this->repositoryMock
            ->shouldReceive('create')
            ->with(Mockery::on(function ($arg) use ($data) {
                return $arg['visit_id'] === $data['visit_id'] 
                    && $arg['current_phase'] === $data['current_phase'];
            }))
            ->once()
            ->andReturn($visitCurrentState);
        
        $result = $this->service->createVisitCurrentState($data);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Visit current state created successfully.', $result['message']);
        $this->assertEquals($visitCurrentState, $result['data']);
        $this->assertEquals(201, $result['status']);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_visit_current_state()
    {
        $data = [
            // Missing required fields
            'current_phase' => 'invalid_phase'
        ];
        
        $result = $this->service->createVisitCurrentState($data);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('The visit_id field is required.', $result['message']);
        $this->assertEquals(422, $result['status']);
    }

    /** @test */
    public function it_updates_visit_current_state_successfully()
    {
        $data = ['current_phase' => 'consultation'];
        
        $visitCurrentState = VisitCurrentState::factory()->make(['id' => 1]);
        $updatedVisitCurrentState = clone $visitCurrentState;
        $updatedVisitCurrentState->current_phase = 'consultation';
        
        $this->repositoryMock
            ->shouldReceive('update')
            ->with(1, $data)
            ->once()
            ->andReturn($updatedVisitCurrentState);
        
        $result = $this->service->updateVisitCurrentState(1, $data);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Visit current state updated successfully.', $result['message']);
        $this->assertEquals($updatedVisitCurrentState, $result['data']);
        $this->assertEquals(200, $result['status']);
    }

    /** @test */
    public function it_returns_visit_current_states_by_facility_successfully()
    {
        $facilityId = 1;
        $filters = ['phase' => 'waiting_triage'];
        $visitCurrentStates = new Collection([
            VisitCurrentState::factory()->make(['facility_id' => $facilityId]),
            VisitCurrentState::factory()->make(['facility_id' => $facilityId])
        ]);
        
        $this->repositoryMock
            ->shouldReceive('getByFacility')
            ->with($facilityId, $filters)
            ->once()
            ->andReturn($visitCurrentStates);
        
        $result = $this->service->getVisitCurrentStatesByFacility($facilityId, $filters);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Visit current states retrieved successfully.', $result['message']);
        $this->assertEquals($visitCurrentStates, $result['data']);
        $this->assertEquals(200, $result['status']);
        $this->assertEquals($facilityId, $result['meta']['facility_id']);
    }

    /** @test */
    public function it_gets_visits_with_critical_alerts_successfully()
    {
        $facilityId = 1;
        $visitCurrentStates = new Collection([
            VisitCurrentState::factory()->make(['facility_id' => $facilityId, 'has_critical_alerts' => true]),
            VisitCurrentState::factory()->make(['facility_id' => $facilityId, 'has_critical_alerts' => true])
        ]);
        
        $this->repositoryMock
            ->shouldReceive('getWithCriticalAlerts')
            ->with($facilityId)
            ->once()
            ->andReturn($visitCurrentStates);
        
        $result = $this->service->getVisitsWithCriticalAlerts($facilityId);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Visits with critical alerts retrieved successfully.', $result['message']);
        $this->assertEquals($visitCurrentStates, $result['data']);
        $this->assertEquals(2, $result['meta']['count']);
    }

    /** @test */
    public function it_processes_visit_event_successfully()
    {
        $visitId = 1;
        $eventData = [
            'event_type' => 'phase_change',
            'new_phase' => 'consultation'
        ];
        
        $visitCurrentState = VisitCurrentState::factory()->make(['visit_id' => $visitId]);
        
        $this->repositoryMock
            ->shouldReceive('updateFromEvent')
            ->with($visitId, Mockery::on(function ($arg) {
                return $arg['event_type'] === 'phase_change' 
                    && $arg['current_phase'] === 'consultation';
            }))
            ->once()
            ->andReturn($visitCurrentState);
        
        $result = $this->service->processVisitEvent($visitId, $eventData);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Visit event processed successfully.', $result['message']);
        $this->assertEquals($visitCurrentState, $result['data']);
    }

    /** @test */
    public function it_gets_dashboard_stats_successfully()
    {
        $facilityId = 1;
        $stats = [
            'total_visits' => 50,
            'critical_alerts_count' => 3,
            'avg_wait_time' => 45,
            'currently_waiting' => 20,
            'discharged_today' => 15
        ];
        
        $this->repositoryMock
            ->shouldReceive('getDashboardStats')
            ->with($facilityId)
            ->once()
            ->andReturn($stats);
        
        $result = $this->service->getDashboardStats($facilityId);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Dashboard statistics retrieved successfully.', $result['message']);
        $this->assertEquals($stats, $result['data']);
    }
}