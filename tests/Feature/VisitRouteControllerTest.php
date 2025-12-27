<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\VisitRoute;
use App\Models\Visit;
use App\Models\Facility;
use App\Models\Department;
use Laravel\Passport\Passport;
use Illuminate\Foundation\Testing\RefreshDatabase;

class VisitRouteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $staffUser;
    protected Facility $facility;
    protected Visit $visit;
    protected Department $department1;
    protected Department $department2;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->facility = Facility::factory()->create();
        $this->visit = Visit::factory()->create(['facility_id' => $this->facility->id]);
        $this->department1 = Department::factory()->create(['facility_id' => $this->facility->id]);
        $this->department2 = Department::factory()->create(['facility_id' => $this->facility->id]);
        
        $this->adminUser = User::factory()->create([
            'facility_id' => $this->facility->id,
        ]);
        $this->adminUser->assignRole('administrator');
        
        $this->staffUser = User::factory()->create([
            'facility_id' => $this->facility->id,
            'department_id' => $this->department1->id,
        ]);
        $this->staffUser->assignRole('nurse');
        
        // Passport::actingAs($this->adminUser, ['visit-routes-read', 'visit-routes-write']);
    }

    /** @test */
    public function it_can_list_visit_routes()
    {
        VisitRoute::factory()->count(3)->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'to_department_id' => $this->department2->id,
        ]);
        
        $response = $this->getJson('/api/v1/visit-routes');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'total',
                    'per_page',
                    'current_page',
                    'last_page'
                ]
            ])
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_can_create_a_visit_route()
    {
        $data = [
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'to_department_id' => $this->department2->id,
            'routing_reason' => 'initial_assignment',
            'routing_notes' => 'Patient requires specialist consultation',
            'routed_at' => now()->toDateTimeString(),
            'estimated_wait_minutes' => 30,
        ];
        
        $response = $this->postJson('/api/v1/visit-routes', $data);
        
        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'facility_id',
                    'visit_id',
                    'to_department_id',
                    'routing_reason',
                ]
            ])
            ->assertJson(['success' => true]);
        
        $this->assertDatabaseHas('visit_routes', [
            'visit_id' => $this->visit->id,
            'to_department_id' => $this->department2->id,
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_route()
    {
        $response = $this->postJson('/api/v1/visit-routes', []);
        
        $response->assertStatus(422)
            ->assertJsonStructure(['errors'])
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function it_can_show_a_specific_visit_route()
    {
        $route = VisitRoute::factory()->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'to_department_id' => $this->department2->id,
        ]);
        
        $response = $this->getJson("/api/v1/visit-routes/{$route->id}");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'facility_id',
                    'visit_id',
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => ['id' => $route->id]
            ]);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_route()
    {
        $response = $this->getJson('/api/v1/visit-routes/9999');
        
        $response->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function it_can_update_a_visit_route()
    {
        $route = VisitRoute::factory()->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'to_department_id' => $this->department2->id,
        ]);
        
        $updateData = [
            'routing_notes' => 'Updated routing notes',
            'estimated_wait_minutes' => 45,
        ];
        
        $response = $this->putJson("/api/v1/visit-routes/{$route->id}", $updateData);
        
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
        
        $this->assertDatabaseHas('visit_routes', [
            'id' => $route->id,
            'routing_notes' => 'Updated routing notes',
            'estimated_wait_minutes' => 45,
        ]);
    }

    /** @test */
    public function it_can_delete_a_visit_route()
    {
        $route = VisitRoute::factory()->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'to_department_id' => $this->department2->id,
            'arrived_at_department' => now(),
            'departed_department' => now()->addHours(1),
        ]);
        
        $response = $this->deleteJson("/api/v1/visit-routes/{$route->id}");
        
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
        
        $this->assertSoftDeleted('visit_routes', ['id' => $route->id]);
    }

    /** @test */
    public function it_cannot_delete_active_route()
    {
        $route = VisitRoute::factory()->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'to_department_id' => $this->department2->id,
            'arrived_at_department' => null, // Active route
        ]);
        
        $response = $this->deleteJson("/api/v1/visit-routes/{$route->id}");
        
        $response->assertStatus(422)
            ->assertJson(['success' => false]);
        
        $this->assertDatabaseHas('visit_routes', ['id' => $route->id]);
    }

    /** @test */
    public function it_can_get_routes_for_a_visit()
    {
        VisitRoute::factory()->count(2)->create([
            'visit_id' => $this->visit->id,
            'facility_id' => $this->facility->id,
            'to_department_id' => $this->department2->id,
        ]);
        
        $response = $this->getJson("/api/v1/visits/{$this->visit->id}/routes");
        
        $response->assertStatus(200)
            ->assertJsonStructure(['data'])
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_can_acknowledge_handoff()
    {
        $route = VisitRoute::factory()->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'to_department_id' => $this->department2->id,
            'handoff_acknowledged' => false,
            'handoff_summary' => 'Patient handoff required',
        ]);
        
        $response = $this->postJson("/api/v1/visit-routes/{$route->id}/acknowledge-handoff", [
            'staff_id' => $this->staffUser->id,
        ]);
        
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
        
        $this->assertDatabaseHas('visit_routes', [
            'id' => $route->id,
            'handoff_acknowledged' => true,
            'receiving_staff_id' => $this->staffUser->id,
        ]);
    }

    /** @test */
    public function it_can_mark_route_as_arrived()
    {
        $route = VisitRoute::factory()->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'to_department_id' => $this->department2->id,
            'arrived_at_department' => null,
        ]);
        
        $response = $this->postJson("/api/v1/visit-routes/{$route->id}/mark-arrived");
        
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
        
        $this->assertDatabaseHas('visit_routes', [
            'id' => $route->id,
            'arrived_at_department' => now(),
        ]);
    }

    /** @test */
    public function it_can_mark_route_as_departed()
    {
        $route = VisitRoute::factory()->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'to_department_id' => $this->department2->id,
            'arrived_at_department' => now()->subHour(),
            'departed_department' => null,
        ]);
        
        $response = $this->postJson("/api/v1/visit-routes/{$route->id}/mark-departed");
        
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
        
        $this->assertDatabaseHas('visit_routes', [
            'id' => $route->id,
            'departed_department' => now(),
        ]);
    }

    /** @test */
    public function it_requires_authentication()
    {
        // Test without authentication
        $this->app['auth']->guard('api')->setUser(null);
        
        $response = $this->getJson('/api/v1/visit-routes');
        
        $response->assertStatus(401);
    }

    /** @test */
    public function it_enforces_authorization()
    {
        // Test with user lacking permissions
        $user = User::factory()->create();
        // Passport::actingAs($user, []);
        
        $response = $this->getJson('/api/v1/visit-routes');
        
        $response->assertStatus(403);
    }
}