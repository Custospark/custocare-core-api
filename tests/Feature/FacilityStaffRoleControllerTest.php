<?php

namespace Tests\Feature;

use App\Models\FacilityStaffRole;
use App\Models\Facility;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class FacilityStaffRoleControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test index endpoint returns assignments
     */
    public function test_index_returns_assignments(): void
    {
        FacilityStaffRole::factory()->count(5)->create();
        
        $response = $this->getJson('/api/facility-staff-roles');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'assignment_uuid',
                        'facility_id',
                        'staff_id',
                        'role_code',
                        'assignment_status'
                    ]
                ],
                'success',
                'message',
                'meta'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Role assignments retrieved successfully'
            ]);
    }

    /**
     * Test store endpoint creates assignment
     */
    public function test_store_creates_assignment(): void
    {
        $facility = Facility::factory()->create();
        $staff = Staff::factory()->create();
        
        $data = [
            'facility_id' => $facility->id,
            'staff_id' => $staff->id,
            'role_code' => 'attending_physician',
            'effective_from' => '2024-12-31',
            'is_primary_facility' => true,
            'department_ids' => [1, 2, 3]
        ];
        
        $response = $this->postJson('/api/facility-staff-roles', $data);
        
        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'assignment_uuid',
                    'facility_id',
                    'staff_id',
                    'role_code'
                ],
                'success',
                'message'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Role assignment created successfully'
            ]);
        
        $this->assertDatabaseHas('facility_staff_roles', [
            'facility_id' => $facility->id,
            'staff_id' => $staff->id,
            'role_code' => 'attending_physician'
        ]);
    }

    /**
     * Test store endpoint validation fails
     */
    public function test_store_validation_fails(): void
    {
        $data = [
            'facility_id' => 999, // Non-existent
            'staff_id' => 999, // Non-existent
            'role_code' => 'invalid_role',
            'effective_from' => '2023-01-01' // Past date
        ];
        
        $response = $this->postJson('/api/facility-staff-roles', $data);
        
        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors'
            ])
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed'
            ]);
    }

    /**
     * Test show endpoint returns assignment
     */
    public function test_show_returns_assignment(): void
    {
        $assignment = FacilityStaffRole::factory()->create();
        
        $response = $this->getJson("/api/facility-staff-roles/{$assignment->id}");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'assignment_uuid',
                    'facility_id',
                    'staff_id',
                    'role_code'
                ],
                'success',
                'message'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Role assignment retrieved successfully',
                'data' => [
                    'id' => $assignment->id,
                    'assignment_uuid' => $assignment->assignment_uuid
                ]
            ]);
    }

    /**
     * Test show endpoint returns 404 for non-existent assignment
     */
    public function test_show_returns_404_for_non_existent_assignment(): void
    {
        $response = $this->getJson('/api/facility-staff-roles/999');
        
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Role assignment not found'
            ]);
    }

    /**
     * Test update endpoint updates assignment
     */
    public function test_update_updates_assignment(): void
    {
        $assignment = FacilityStaffRole::factory()->create();
        
        $data = [
            'role_code' => 'surgeon',
            'shift_type' => 'day',
            'hours_per_week' => 40
        ];
        
        $response = $this->putJson("/api/facility-staff-roles/{$assignment->id}", $data);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Role assignment updated successfully',
                'data' => [
                    'role_code' => 'surgeon',
                    'shift_type' => 'day'
                ]
            ]);
        
        $this->assertDatabaseHas('facility_staff_roles', [
            'id' => $assignment->id,
            'role_code' => 'surgeon',
            'shift_type' => 'day'
        ]);
    }

    /**
     * Test destroy endpoint deletes assignment
     */
    public function test_destroy_deletes_assignment(): void
    {
        $assignment = FacilityStaffRole::factory()->create([
            'assignment_status' => 'active'
        ]);
        
        $response = $this->deleteJson("/api/facility-staff-roles/{$assignment->id}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Role assignment deleted successfully'
            ]);
        
        // Should be soft deleted or status changed to terminated
        $this->assertDatabaseHas('facility_staff_roles', [
            'id' => $assignment->id
        ]);
    }

    /**
     * Test byFacility endpoint returns assignments
     */
    public function test_by_facility_returns_assignments(): void
    {
        $facility = Facility::factory()->create();
        FacilityStaffRole::factory()->count(3)->create([
            'facility_id' => $facility->id
        ]);
        
        $response = $this->getJson("/api/facility-staff-roles/facility/{$facility->id}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Facility assignments retrieved successfully'
            ]);
        
        $responseData = $response->json();
        $this->assertCount(3, $responseData['data']);
    }

    /**
     * Test byStaff endpoint returns assignments
     */
    public function test_by_staff_returns_assignments(): void
    {
        $staff = Staff::factory()->create();
        FacilityStaffRole::factory()->count(2)->create([
            'staff_id' => $staff->id
        ]);
        
        $response = $this->getJson("/api/facility-staff-roles/staff/{$staff->id}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Staff assignments retrieved successfully'
            ]);
        
        $responseData = $response->json();
        $this->assertCount(2, $responseData['data']);
    }

    /**
     * Test updateStatus endpoint updates status
     */
    public function test_update_status_updates_assignment_status(): void
    {
        $assignment = FacilityStaffRole::factory()->create([
            'assignment_status' => 'active'
        ]);
        
        $data = [
            'status' => 'on_leave',
            'effective_to' => '2024-12-31'
        ];
        
        $response = $this->putJson("/api/facility-staff-roles/{$assignment->id}/status", $data);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Assignment status updated successfully',
                'data' => [
                    'assignment_status' => 'on_leave'
                ]
            ]);
    }

    /**
     * Test updateCredentialing endpoint updates credentialing
     */
    public function test_update_credentialing_updates_credentialing_info(): void
    {
        $assignment = FacilityStaffRole::factory()->create();
        $credentialedBy = Staff::factory()->create();
        
        $data = [
            'credentialing_completed_at' => '2024-01-15',
            'credentialed_by_staff_id' => $credentialedBy->id,
            'privileging_approved_at' => '2024-01-16'
        ];
        
        $response = $this->putJson("/api/facility-staff-roles/{$assignment->id}/credentialing", $data);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Credentialing information updated successfully'
            ]);
    }

    /**
     * Test expiring assignments endpoint
     */
    public function test_expiring_assignments_returns_expiring_assignments(): void
    {
        // Create assignments with future effective_to dates
        FacilityStaffRole::factory()->create([
            'effective_to' => now()->addDays(15)->format('Y-m-d'),
            'assignment_status' => 'active'
        ]);
        
        $response = $this->getJson('/api/facility-staff-roles/expiring/assignments?days_ahead=30');
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Expiring assignments retrieved successfully'
            ]);
    }
}