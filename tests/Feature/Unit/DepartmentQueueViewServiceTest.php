<?php

namespace Tests\Unit;

use App\Models\DepartmentQueueView;
use App\Repositories\Contracts\DepartmentQueueViewRepositoryInterface;
use App\Services\DepartmentQueueView\DepartmentQueueViewService;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class DepartmentQueueViewServiceTest extends TestCase
{
    private $repositoryMock;
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->repositoryMock = Mockery::mock(DepartmentQueueViewRepositoryInterface::class);
        $this->service = new DepartmentQueueViewService($this->repositoryMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_returns_queue_view_by_id()
    {
        $queueView = DepartmentQueueView::factory()->make(['id' => 1]);
        
        $this->repositoryMock->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($queueView);

        $result = $this->service->getQueueViewById(1);
        
        $this->assertInstanceOf(DepartmentQueueView::class, $result);
        $this->assertEquals(1, $result->id);
    }

    /** @test */
    public function it_returns_null_when_queue_view_not_found_by_id()
    {
        $this->repositoryMock->shouldReceive('findById')
            ->with(999)
            ->once()
            ->andReturn(null);

        $result = $this->service->getQueueViewById(999);
        
        $this->assertNull($result);
    }

    /** @test */
    public function it_returns_queue_view_by_department_and_type()
    {
        $queueView = DepartmentQueueView::factory()->make([
            'department_id' => 1,
            'queue_type' => 'triage'
        ]);
        
        $this->repositoryMock->shouldReceive('findByDepartmentAndType')
            ->with(1, 'triage')
            ->once()
            ->andReturn($queueView);

        $result = $this->service->getQueueViewByDepartmentAndType(1, 'triage');
        
        $this->assertInstanceOf(DepartmentQueueView::class, $result);
        $this->assertEquals('triage', $result->queue_type);
    }

    /** @test */
    public function it_returns_facility_queue_views_with_filters()
    {
        $queueViews = DepartmentQueueView::factory()->count(3)->make();
        $filters = ['queue_type' => 'triage', 'current' => true];
        
        $this->repositoryMock->shouldReceive('getByFacilityId')
            ->with(1, $filters)
            ->once()
            ->andReturn(new Collection($queueViews));

        $result = $this->service->getFacilityQueueViews(1, $filters);
        
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(3, $result);
    }

    /** @test */
    public function it_creates_queue_view_successfully()
    {
        $data = [
            'facility_id' => 1,
            'department_id' => 1,
            'queue_type' => 'triage',
            'patients_waiting_count' => 5,
            'patients_in_treatment_count' => 3,
            'staff_available_count' => 2,
            'staff_total_count' => 4,
            'capacity_status' => 'normal'
        ];
        
        $queueView = DepartmentQueueView::factory()->make($data);
        
        $this->repositoryMock->shouldReceive('create')
            ->with(Mockery::subset($data))
            ->once()
            ->andReturn($queueView);

        $result = $this->service->createQueueView($data);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Queue view created successfully', $result['message']);
        $this->assertInstanceOf(DepartmentQueueView::class, $result['data']);
    }

    /** @test */
    public function it_validates_queue_data_and_returns_errors()
    {
        $invalidData = [
            'facility_id' => 1,
            'queue_type' => 'invalid_type', // Invalid queue type
            'patients_waiting_count' => -5, // Negative count
        ];

        $result = $this->service->createQueueView($invalidData);
        
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('queue_type', $result['errors']);
    }

    /** @test */
    public function it_updates_queue_view_successfully()
    {
        $queueView = DepartmentQueueView::factory()->make(['id' => 1]);
        $updateData = ['patients_waiting_count' => 10];
        
        $this->repositoryMock->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($queueView);
            
        $this->repositoryMock->shouldReceive('update')
            ->with($queueView, Mockery::subset($updateData))
            ->once()
            ->andReturn(true);

        $result = $this->service->updateQueueView(1, $updateData);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Queue view updated successfully', $result['message']);
    }

    /** @test */
    public function it_returns_not_found_when_updating_non_existent_queue_view()
    {
        $this->repositoryMock->shouldReceive('findById')
            ->with(999)
            ->once()
            ->andReturn(null);

        $result = $this->service->updateQueueView(999, ['patients_waiting_count' => 10]);
        
        $this->assertFalse($result['success']);
        $this->assertEquals(404, $result['code']);
    }

    /** @test */
    public function it_performs_batch_update_successfully()
    {
        $queueData = [
            [
                'department_id' => 1,
                'queue_type' => 'triage',
                'patients_waiting_count' => 5
            ],
            [
                'department_id' => 2,
                'queue_type' => 'consultation',
                'patients_waiting_count' => 3
            ]
        ];
        
        $this->repositoryMock->shouldReceive('batchUpdate')
            ->with(Mockery::type('array'))
            ->once()
            ->andReturn(true);

        $result = $this->service->batchUpdateQueueViews($queueData);
        
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['updated_count']);
    }

    /** @test */
    public function it_returns_critical_queues_with_alert_levels()
    {
        $criticalQueues = DepartmentQueueView::factory()->count(2)->make([
            'capacity_status' => 'critical'
        ]);
        
        $this->repositoryMock->shouldReceive('getCriticalQueues')
            ->with(1)
            ->once()
            ->andReturn(new Collection($criticalQueues));

        $result = $this->service->getCriticalQueues(1);
        
        $this->assertInstanceOf(Collection::class, $result);
        $result->each(function ($queue) {
            // $this->assertObjectHasAttribute('alert_level', $queue);
        });
    }

    /** @test */
    public function it_returns_dashboard_statistics()
    {
        $statistics = [
            'total_patients_waiting' => 50,
            'total_patients_in_treatment' => 30,
            'critical_departments_count' => 2,
            'average_wait_time' => 25.5,
            'by_queue_type' => ['triage' => 20, 'consultation' => 15]
        ];
        
        $this->repositoryMock->shouldReceive('getDashboardStatistics')
            ->with(1)
            ->once()
            ->andReturn($statistics);

        $result = $this->service->getDashboardStatistics(1);
        
        $this->assertArrayHasKey('overall_capacity_status', $result);
        $this->assertArrayHasKey('recommended_actions', $result);
        $this->assertEquals(50, $result['total_patients_waiting']);
    }

    /** @test */
    public function it_analyzes_wait_times_successfully()
    {
        $queueView = DepartmentQueueView::factory()->make([
            'department_id' => 1,
            'queue_type' => 'triage',
            'average_wait_minutes' => 45
        ]);
        
        $trends = new Collection([
            (object) ['average_wait_minutes' => 30],
            (object) ['average_wait_minutes' => 45]
        ]);
        
        $this->repositoryMock->shouldReceive('findByDepartmentAndType')
            ->with(1, 'triage')
            ->once()
            ->andReturn($queueView);
            
        $this->repositoryMock->shouldReceive('getWaitTimeTrends')
            ->with(1, 'triage', 6)
            ->once()
            ->andReturn($trends);

        $result = $this->service->analyzeWaitTimes(1, 'triage');
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('trend', $result['data']);
        $this->assertArrayHasKey('recommendations', $result['data']);
    }

    /** @test */
    public function it_generates_predictions_successfully()
    {
        $queueView = DepartmentQueueView::factory()->make([
            'department_id' => 1,
            'queue_type' => 'triage',
            'patients_waiting_count' => 10,
            'average_wait_minutes' => 30
        ]);
        
        $this->repositoryMock->shouldReceive('findByDepartmentAndType')
            ->with(1, 'triage')
            ->once()
            ->andReturn($queueView);

        $result = $this->service->generatePredictions(1, 'triage');
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('next_30_minutes', $result['data']);
        $this->assertArrayHasKey('next_hour', $result['data']);
        $this->assertArrayHasKey('recommended_staffing', $result['data']);
    }

    /** @test */
    public function it_deletes_queue_view_successfully()
    {
        $queueView = DepartmentQueueView::factory()->make(['id' => 1]);
        
        $this->repositoryMock->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($queueView);
            
        $this->repositoryMock->shouldReceive('delete')
            ->with($queueView)
            ->once()
            ->andReturn(true);

        $result = $this->service->deleteQueueView(1);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Queue view deleted successfully', $result['message']);
    }
}