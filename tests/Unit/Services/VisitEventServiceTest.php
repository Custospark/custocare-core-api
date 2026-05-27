<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\VisitEvent\VisitEventService;
use App\Repositories\Contracts\VisitEventRepositoryInterface;
use App\Models\VisitEvent;
use Illuminate\Support\Facades\Log;
use Mockery;
use Carbon\Carbon;

class VisitEventServiceTest extends TestCase
{
    protected $visitEventRepository;
    protected $visitEventService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->visitEventRepository = Mockery::mock(VisitEventRepositoryInterface::class);
        $this->visitEventService = new VisitEventService($this->visitEventRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_record_a_valid_event()
    {
        $eventData = [
            'facility_id' => 1,
            'visit_id' => 100,
            'event_type' => 'patient_arrived',
            'event_payload' => ['patient_id' => 500],
            'actor_type' => 'staff',
            'actor_id' => 10,
            'event_occurred_at' => now()->subMinutes(5)->toISOString(),
        ];

        $mockEvent = Mockery::mock(VisitEvent::class);
        $mockEvent->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $mockEvent->shouldReceive('getAttribute')->with('event_uuid')->andReturn('test-uuid');
        $mockEvent->shouldReceive('getAttribute')->with('integrity_hash')->andReturn('test-hash');

        $this->visitEventRepository
            ->shouldReceive('getLastEventForVisit')
            ->with(100)
            ->andReturn(null);

        $this->visitEventRepository
            ->shouldReceive('create')
            ->with(Mockery::type('array'))
            ->andReturn($mockEvent);

        $result = $this->visitEventService->recordEvent($eventData);

        $this->assertTrue($result['success']);
        $this->assertEquals('Event recorded successfully', $result['message']);
        $this->assertArrayHasKey('event_id', $result['data']);
        $this->assertArrayHasKey('event_uuid', $result['data']);
        $this->assertArrayHasKey('integrity_hash', $result['data']);
    }

    /** @test */
    public function it_handles_validation_errors_when_recording_event()
    {
        $invalidEventData = [
            'facility_id' => null, // Required field missing
            'event_type' => 'invalid_type', // Invalid event type
        ];

        $result = $this->visitEventService->recordEvent($invalidEventData);

        $this->assertFalse($result['success']);
        $this->assertEquals('Validation failed', $result['message']);
        $this->assertEquals(422, $result['status_code']);
        $this->assertArrayHasKey('errors', $result);
    }

    /** @test */
    public function it_can_get_event_by_uuid()
    {
        $eventUuid = 'test-uuid-123';
        $mockEvent = Mockery::mock(VisitEvent::class);
        
        $this->visitEventRepository
            ->shouldReceive('findByUuid')
            ->with($eventUuid)
            ->andReturn($mockEvent);

        $result = $this->visitEventService->getEventByUuid($eventUuid);

        $this->assertTrue($result['success']);
        $this->assertEquals('Event retrieved successfully', $result['message']);
        $this->assertSame($mockEvent, $result['data']);
    }

    /** @test */
    public function it_returns_not_found_when_event_uuid_does_not_exist()
    {
        $eventUuid = 'non-existent-uuid';
        
        $this->visitEventRepository
            ->shouldReceive('findByUuid')
            ->with($eventUuid)
            ->andReturn(null);

        $result = $this->visitEventService->getEventByUuid($eventUuid);

        $this->assertFalse($result['success']);
        $this->assertEquals('Event not found', $result['message']);
        $this->assertEquals(404, $result['status_code']);
    }

    /** @test */
    public function it_can_get_paginated_events()
    {
        $filters = ['facility_id' => 1];
        $perPage = 10;
        $mockPaginator = Mockery::mock(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class);
        
        $this->visitEventRepository
            ->shouldReceive('paginate')
            ->with($filters, $perPage, ['visit', 'precedingEvent'])
            ->andReturn($mockPaginator);

        $result = $this->visitEventService->getPaginatedEvents($filters, $perPage);

        $this->assertTrue($result['success']);
        $this->assertEquals('Events retrieved successfully', $result['message']);
        $this->assertSame($mockPaginator, $result['data']);
        $this->assertArrayHasKey('pagination', $result);
    }

    /** @test */
    public function it_can_get_clinical_timeline_for_visit()
    {
        $visitId = 100;
        $mockCollection = Mockery::mock(\Illuminate\Database\Eloquent\Collection::class);
        
        $this->visitEventRepository
            ->shouldReceive('getClinicalEventsForVisit')
            ->with($visitId)
            ->andReturn($mockCollection);

        $result = $this->visitEventService->getClinicalTimeline($visitId);

        $this->assertTrue($result['success']);
        $this->assertEquals('Clinical timeline retrieved successfully', $result['message']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('count', $result);
    }

    /** @test */
    public function it_can_verify_event_chain_integrity()
    {
        $visitId = 100;
        $verificationResult = [
            'verified' => true,
            'total_events' => 5,
            'failed_events' => [],
            'failed_count' => 0,
        ];
        
        $this->visitEventRepository
            ->shouldReceive('verifyEventChainIntegrity')
            ->with($visitId)
            ->andReturn($verificationResult);

        $result = $this->visitEventService->verifyEventChain($visitId);

        $this->assertTrue($result['success']);
        $this->assertEquals('Event chain integrity verified', $result['message']);
        $this->assertSame($verificationResult, $result['data']);
    }

    /** @test */
    public function it_handles_repository_exceptions_gracefully()
    {
        $eventUuid = 'test-uuid';
        
        $this->visitEventRepository
            ->shouldReceive('findByUuid')
            ->with($eventUuid)
            ->andThrow(new \Exception('Database connection failed'));

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to get event by UUID', Mockery::type('array'));

        $result = $this->visitEventService->getEventByUuid($eventUuid);

        $this->assertFalse($result['success']);
        $this->assertEquals('Failed to retrieve event', $result['message']);
        $this->assertEquals(500, $result['status_code']);
    }

    /** @test */
    public function it_can_recalculate_integrity_hash()
    {
        $eventId = 1;
        $mockEvent = Mockery::mock(VisitEvent::class);
        $mockPrecedingEvent = Mockery::mock(VisitEvent::class);
        
        $this->visitEventRepository
            ->shouldReceive('findById')
            ->with($eventId)
            ->andReturn($mockEvent);

        $mockEvent->shouldReceive('getAttribute')->with('precedingEvent')->andReturn($mockPrecedingEvent);
        $mockPrecedingEvent->shouldReceive('getAttribute')->with('integrity_hash')->andReturn('preceding-hash');
        
        $mockEvent->shouldReceive('generateIntegrityHash')->with('preceding-hash')->andReturn('new-hash');
        $mockEvent->shouldReceive('getAttribute')->with('integrity_hash')->andReturn('old-hash');
        
        $mockEvent->shouldReceive('verifyIntegrityHash')->with('preceding-hash')->andReturn(true);
        $mockEvent->shouldReceive('save')->once();

        $result = $this->visitEventService->recalculateIntegrityHash($eventId);

        $this->assertTrue($result['success']);
        $this->assertEquals('Integrity hash recalculated and updated', $result['message']);
        $this->assertArrayHasKey('hash_changed', $result['data']);
        $this->assertTrue($result['data']['integrity_verified']);
    }
}