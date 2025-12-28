<?php

namespace Tests\Feature;

use App\Models\DepartmentQueueView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentQueueViewControllerTest extends TestCase
{
    use RefreshDatabase;

    private $adminUser;
    private $regularUser;
    private $queueView;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users
        $this->adminUser = User::factory()->create([
            'email' => 'admin@hospital.com',
            'role' => 'admin'
        ]);
        
        $this->regularUser = User::factory()->create([
            'email' => 'user@hospital.com',
            'role' => 'staff'
        ]);
        
        // Create test queue view
        $this->queueView = DepartmentQueueView::factory()->create();
        
        // Assign permissions
        $this->adminUser->givePermissionTo([
            'view department queue views',
            'create department queue views',
            'edit department queue views',
            'delete department queue views',
            'batch update queue views'
        ]);
    }

    /** @test */
    public function it_lists_department_queue_views_for_authenticated_users()
    {
        DepartmentQueueView::factory()->count(5)->create();
        
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/department-queue-views?facility_id=' . $this->queueView->facility_id);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'facility_id',
                        'department_id',
                        'queue_type',
                        'patients_waiting_count',
                        'capacity' => ['status', 'percentage']
                    ]
                ],
                'meta'
            ]);
    }

    /** @test */
    public function it_requires_facility_id_for_listing_queue_views()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/department-queue-views');
        
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Facility ID is required'
            ]);
    }

    /** @test */
    public function it_creates_new_department_queue_view()
    {
        $data = [
            'facility_id' => 1,
            'department_id' => 1,
            'queue_type' => 'triage',
            'patients_waiting_count' => 5,
            'patients_in_treatment_count' => 3,
            'staff_available_count' => 2,
            'staff_total_count' => 4,
            'capacity_status' => 'normal',
            'snapshot_at' => now()->toDateTimeString()
        ];
        
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/department-queue-views', $data);
        
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Queue view created successfully'
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'queue_type',
                    'patients_waiting_count'
                ]
            ]);
        
        $this->assertDatabaseHas('department_queue_views', [
            'queue_type' => 'triage',
            'patients_waiting_count' => 5
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_queue_view()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/department-queue-views', []);
        
        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'facility_id',
                    'department_id',
                    'queue_type'
                ]
            ]);
    }

    /** @test */
    public function it_shows_specific_department_queue_view()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/department-queue-views/' . $this->queueView->id);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->queueView->id,
                    'queue_type' => $this->queueView->queue_type
                ]
            ]);
    }

    /** @test */
    public function it_returns_404_for_non_existent_queue_view()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/department-queue-views/9999');
        
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Department queue view not found'
            ]);
    }

    /** @test */
    public function it_updates_department_queue_view()
    {
        $updateData = [
            'patients_waiting_count' => 15,
            'capacity_status' => 'busy'
        ];
        
        $response = $this->actingAs($this->adminUser)
            ->putJson('/api/department-queue-views/' . $this->queueView->id, $updateData);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Queue view updated successfully'
            ]);
        
        $this->assertDatabaseHas('department_queue_views', [
            'id' => $this->queueView->id,
            'patients_waiting_count' => 15,
            'capacity_status' => 'busy'
        ]);
    }

    /** @test */
    public function it_deletes_department_queue_view()
    {
        $response = $this->actingAs($this->adminUser)
            ->deleteJson('/api/department-queue-views/' . $this->queueView->id);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Queue view deleted successfully'
            ]);
        
        $this->assertDatabaseMissing('department_queue_views', [
            'id' => $this->queueView->id
        ]);
    }

    /** @test */
    public function it_returns_critical_queues()
    {
        DepartmentQueueView::factory()->create([
            'capacity_status' => 'critical',
            'facility_id' => $this->queueView->facility_id
        ]);
        
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/department-queue-views/critical?facility_id=' . $this->queueView->facility_id);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['total_critical', 'facility_id']
            ]);
    }

    /** @test */
    public function it_returns_dashboard_statistics()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/department-queue-views/dashboard?facility_id=' . $this->queueView->facility_id);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_patients_waiting',
                    'total_patients_in_treatment',
                    'critical_departments_count',
                    'average_wait_time',
                    'overall_capacity_status'
                ],
                'meta'
            ]);
    }

    /** @test */
    public function it_performs_batch_update()
    {
        $queueData = [
            'queue_views' => [
                [
                    'department_id' => 1,
                    'queue_type' => 'triage',
                    'patients_waiting_count' => 5,
                    'staff_available_count' => 2,
                    'staff_total_count' => 4,
                    'capacity_status' => 'normal',
                    'snapshot_at' => now()->toDateTimeString()
                ],
                [
                    'department_id' => 2,
                    'queue_type' => 'consultation',
                    'patients_waiting_count' => 3,
                    'staff_available_count' => 3,
                    'staff_total_count' => 5,
                    'capacity_status' => 'normal',
                    'snapshot_at' => now()->toDateTimeString()
                ]
            ]
        ];
        
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/department-queue-views/batch-update', $queueData);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Queue views updated successfully'
            ]);
    }

    /** @test */
    public function it_analyzes_wait_times()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/department-queue-views/analyze/wait-times?' . http_build_query([
                'department_id' => $this->queueView->department_id,
                'queue_type' => $this->queueView->queue_type
            ]));
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'current_wait_time',
                    'trend',
                    'severity',
                    'recommendations'
                ]
            ]);
    }

    /** @test */
    public function it_generates_predictions()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/department-queue-views/generate/predictions?' . http_build_query([
                'department_id' => $this->queueView->department_id,
                'queue_type' => $this->queueView->queue_type
            ]));
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'next_30_minutes',
                    'next_hour',
                    'recommended_staffing'
                ]
            ]);
    }

    /** @test */
    public function it_gets_queue_view_by_department_and_type()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/department-queue-views/department-type?' . http_build_query([
                'department_id' => $this->queueView->department_id,
                'queue_type' => $this->queueView->queue_type
            ]));
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->queueView->id,
                    'department_id' => $this->queueView->department_id,
                    'queue_type' => $this->queueView->queue_type
                ]
            ]);
    }

    /** @test */
    public function it_denies_access_to_unauthorized_users()
    {
        $response = $this->actingAs($this->regularUser)
            ->getJson('/api/department-queue-views?facility_id=1');
        
        $response->assertStatus(403);
    }
}