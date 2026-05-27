<?php

namespace Tests\Unit;

use App\Models\PatientVisitSummaryView;
use App\Repositories\Contracts\PatientVisitSummaryViewRepositoryInterface;
use App\Services\PatientVisitSummaryView\PatientVisitSummaryViewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PatientVisitSummaryViewServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var PatientVisitSummaryViewRepositoryInterface|Mockery\MockInterface
     */
    protected $repositoryMock;

    /**
     * @var PatientVisitSummaryViewService
     */
    protected $service;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->repositoryMock = Mockery::mock(PatientVisitSummaryViewRepositoryInterface::class);
        $this->service = new PatientVisitSummaryViewService($this->repositoryMock);
    }

    /** @test */
    public function it_can_get_summary_view_by_id()
    {
        $summary = PatientVisitSummaryView::factory()->make(['id' => 1]);

        $this->repositoryMock->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($summary);

        $result = $this->service->getSummaryViewById(1);

        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['status']);
        $this->assertEquals($summary, $result['data']);
    }

    /** @test */
    public function it_returns_error_when_summary_not_found()
    {
        $this->repositoryMock->shouldReceive('findById')
            ->with(999)
            ->once()
            ->andReturn(null);

        $result = $this->service->getSummaryViewById(999);

        $this->assertFalse($result['success']);
        $this->assertEquals(404, $result['status']);
        $this->assertEquals('Patient visit summary not found', $result['message']);
    }

    /** @test */
    public function it_can_get_summary_by_patient_id()
    {
        $summary = PatientVisitSummaryView::factory()->make(['patient_id' => 1]);

        $this->repositoryMock->shouldReceive('findByPatientId')
            ->with(1)
            ->once()
            ->andReturn($summary);

        $result = $this->service->getSummaryByPatientId(1);

        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['status']);
        $this->assertEquals($summary, $result['data']);
    }

    /** @test */
    public function it_can_create_a_summary_view()
    {
        $data = ['patient_id' => 1];
        $summary = PatientVisitSummaryView::factory()->make($data);

        $this->repositoryMock->shouldReceive('findByPatientId')
            ->with(1)
            ->once()
            ->andReturn(null);

        $this->repositoryMock->shouldReceive('create')
            ->with($data)
            ->once()
            ->andReturn($summary);

        $result = $this->service->createSummaryView($data);

        $this->assertTrue($result['success']);
        $this->assertEquals(201, $result['status']);
        $this->assertEquals($summary, $result['data']);
    }

    /** @test */
    public function it_validates_patient_id_when_creating_summary()
    {
        $data = ['active_visits_count' => 2];

        $result = $this->service->createSummaryView($data);

        $this->assertFalse($result['success']);
        $this->assertEquals(422, $result['status']);
        $this->assertEquals('Patient ID is required', $result['message']);
    }

    /** @test */
    public function it_prevents_duplicate_summary_for_patient()
    {
        $data = ['patient_id' => 1];
        $existingSummary = PatientVisitSummaryView::factory()->make($data);

        $this->repositoryMock->shouldReceive('findByPatientId')
            ->with(1)
            ->once()
            ->andReturn($existingSummary);

        $result = $this->service->createSummaryView($data);

        $this->assertFalse($result['success']);
        $this->assertEquals(409, $result['status']);
        $this->assertEquals('Summary view already exists for this patient', $result['message']);
    }

    /** @test */
    public function it_can_update_a_summary_view()
    {
        $data = ['active_visits_count' => 3];
        $updatedSummary = PatientVisitSummaryView::factory()->make(['id' => 1]);

        $this->repositoryMock->shouldReceive('update')
            ->with(1, $data)
            ->once()
            ->andReturn($updatedSummary);

        $result = $this->service->updateSummaryView(1, $data);

        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['status']);
        $this->assertEquals($updatedSummary, $result['data']);
    }

    /** @test */
    public function it_can_delete_a_summary_view()
    {
        $this->repositoryMock->shouldReceive('delete')
            ->with(1)
            ->once()
            ->andReturn(true);

        $result = $this->service->deleteSummaryView(1);

        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['status']);
    }

    /** @test */
    public function it_handles_repository_exceptions_gracefully()
    {
        $this->repositoryMock->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andThrow(new \Exception('Database error'));

        $result = $this->service->getSummaryViewById(1);

        $this->assertFalse($result['success']);
        $this->assertEquals(500, $result['status']);
        $this->assertEquals('Failed to retrieve patient visit summary', $result['message']);
    }

    /** @test */
    public function it_can_get_all_summaries_with_filters()
    {
        $filters = ['has_upcoming_appointments' => true];
        $paginator = PatientVisitSummaryView::factory()->count(5)->make();

        $this->repositoryMock->shouldReceive('paginate')
            ->with($filters, 20)
            ->once()
            ->andReturn($paginator);

        $result = $this->service->getAllSummaries($filters);

        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['status']);
        $this->assertEquals($paginator, $result['data']);
    }

    /** @test */
    public function it_can_batch_refresh_summary_views()
    {
        $patientIds = [1, 2, 3];
        
        // Mock the refreshSummaryView method behavior
        // Since we're testing the service, we need to mock internal calls
        $this->markTestSkipped('Batch refresh test requires refactoring for proper mocking');

        // This test would normally verify batch operation success/failure reporting
    }

    /** @test */
    public function it_can_get_upcoming_appointments()
    {
        $startDate = '2024-01-01';
        $endDate = '2024-01-31';
        $collection = PatientVisitSummaryView::factory()->count(3)->make();

        $this->repositoryMock->shouldReceive('getWithUpcomingAppointments')
            ->with($startDate, $endDate)
            ->once()
            ->andReturn($collection);

        $result = $this->service->getUpcomingAppointments($startDate, $endDate);

        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['status']);
        $this->assertEquals($collection, $result['data']['appointments']);
    }

    /** @test */
    public function it_can_get_care_coordination_insights()
    {
        $filters = ['last_updated_since' => '2024-01-01'];
        $paginator = PatientVisitSummaryView::factory()->count(10)->make();

        $this->repositoryMock->shouldReceive('paginate')
            ->with($filters, 50)
            ->once()
            ->andReturn($paginator);

        $result = $this->service->getCareCoordinationInsights($filters);

        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['status']);
        $this->assertArrayHasKey('insights', $result['data']);
        $this->assertArrayHasKey('filters_applied', $result['data']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}