<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\VisitRoute\VisitRouteService;
use App\Repositories\Contracts\VisitRouteRepositoryInterface;
use App\Models\VisitRoute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;

class VisitRouteServiceTest extends TestCase
{
    protected VisitRouteService $service;
    protected $repositoryMock;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->repositoryMock = Mockery::mock(VisitRouteRepositoryInterface::class);
        $this->service = new VisitRouteService($this->repositoryMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_returns_all_routes_successfully()
    {
        $paginator = new LengthAwarePaginator([], 0, 15);
        
        $this->repositoryMock
            ->shouldReceive('all')
            ->once()
            ->with([], [], 15)
            ->andReturn($paginator);
        
        $result = $this->service->getAllRoutes();
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Visit routes retrieved successfully.', $result['message']);
        $this->assertInstanceOf(LengthAwarePaginator::class, $result['data']);
    }

    /** @test */
    public function it_returns_route_by_id_successfully()
    {
        $route = VisitRoute::factory()->make(['id' => 1]);
        
        $this->repositoryMock
            ->shouldReceive('find')
            ->once()
            ->with(1, [])
            ->andReturn($route);
        
        $result = $this->service->getRouteById(1);
        
        $this->assertTrue($result['success']);
        $this->assertEquals($route, $result['data']);
    }

    /** @test */
    public function it_returns_error_when_route_not_found()
    {
        $this->repositoryMock
            ->shouldReceive('find')
            ->once()
            ->with(999, [])
            ->andReturn(null);
        
        $result = $this->service->getRouteById(999);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Visit route not found.', $result['message']);
        $this->assertArrayHasKey('id', $result['errors']);
    }

    /** @test */
    public function it_creates_route_successfully()
    {
        $data = [
            'facility_id' => 1,
            'visit_id' => 1,
            'to_department_id' => 2,
            'routing_reason' => 'initial_assignment',
            'routed_at' => now(),
        ];
        
        $route = VisitRoute::factory()->make($data);
        
        $this->repositoryMock
            ->shouldReceive('create')
            ->once()
            ->with($data)
            ->andReturn($route);
        
        $result = $this->service->createRoute($data);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Visit route created successfully.', $result['message']);
        $this->assertEquals($route, $result['data']);
    }

    /** @test */
    public function it_updates_route_successfully()
    {
        $route = VisitRoute::factory()->make(['id' => 1]);
        $data = ['routing_notes' => 'Updated notes'];
        
        $this->repositoryMock
            ->shouldReceive('find')
            ->once()
            ->with(1, [])
            ->andReturn($route);
        
        $this->repositoryMock
            ->shouldReceive('update')
            ->once()
            ->with(1, $data)
            ->andReturn($route);
        
        $result = $this->service->updateRoute(1, $data);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Visit route updated successfully.', $result['message']);
    }

    /** @test */
    public function it_deletes_route_successfully()
    {
        $route = VisitRoute::factory()->make(['id' => 1]);
        
        $this->repositoryMock
            ->shouldReceive('find')
            ->once()
            ->with(1, [])
            ->andReturn($route);
        
        $this->repositoryMock
            ->shouldReceive('delete')
            ->once()
            ->with(1)
            ->andReturn(true);
        
        $result = $this->service->deleteRoute(1);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Visit route deleted successfully.', $result['message']);
    }

    /** @test */
    public function it_returns_routes_for_visit()
    {
        $routes = new Collection([
            VisitRoute::factory()->make(['visit_id' => 1]),
            VisitRoute::factory()->make(['visit_id' => 1]),
        ]);
        
        $this->repositoryMock
            ->shouldReceive('findByVisit')
            ->once()
            ->with(1, [])
            ->andReturn($routes);
        
        $result = $this->service->getRoutesForVisit(1);
        
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['meta']['count']);
    }

    /** @test */
    public function it_acknowledges_handoff_successfully()
    {
        $route = VisitRoute::factory()->make([
            'id' => 1,
            'handoff_acknowledged' => false,
        ]);
        
        $this->repositoryMock
            ->shouldReceive('find')
            ->once()
            ->with(1, [])
            ->andReturn($route);
        
        $route->shouldReceive('acknowledgeHandoff')
            ->once()
            ->with(5)
            ->andReturn(true);
        
        $route->shouldReceive('fresh')
            ->once()
            ->andReturn($route);
        
        $result = $this->service->acknowledgeHandoff(1, 5);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Handoff acknowledged successfully.', $result['message']);
    }

    /** @test */
    public function it_validates_route_creation_data()
    {
        $data = [
            'facility_id' => 1,
            'visit_id' => 1,
            'to_department_id' => 2,
            'routing_reason' => 'initial_assignment',
            'routed_at' => now(),
        ];
        
        $result = $this->service->validateRouteCreation($data);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Validation passed.', $result['message']);
    }

    /** @test */
    public function it_rejects_invalid_route_creation_data()
    {
        $data = [
            'facility_id' => 1,
            // Missing required fields
        ];
        
        $result = $this->service->validateRouteCreation($data);
        
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('visit_id', $result['errors']);
    }
}