<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

class StaffControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $regularUser;
    protected Staff $adminStaff;
    protected Staff $regularStaff;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin user and staff
        $this->adminUser = User::factory()->create();
        $this->adminStaff = Staff::factory()->create([
            'user_id' => $this->adminUser->id,
            'global_role_level' => 'facility_admin',
            'employment_status' => 'active'
        ]);
        
        // Create regular user and staff
        $this->regularUser = User::factory()->create();
        $this->regularStaff = Staff::factory()->create([
            'user_id' => $this->regularUser->id,
            'global_role_level' => 'registered_nurse',
            'employment_status' => 'active'
        ]);
    }

    /** @test */
    public function admin_can_view_all_staff()
    {
        Sanctum::actingAs($this->adminUser);
        
        Staff::factory()->count(5)->create();
        
        $response = $this->getJson('/api/staff');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'staff_uuid',
                        'employee_id',
                        'professional_title',
                        'employment_status'
                    ]
                ],
                'meta'
            ])
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function regular_user_cannot_view_all_staff()
    {
        Sanctum::actingAs($this->regularUser);
        
        $response = $this->getJson('/api/staff');
        
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You do not have permission to view staff records.'
            ]);
    }

    /** @test */
    public function user_can_view_their_own_staff_record()
    {
        Sanctum::actingAs($this->regularUser);
        
        $response = $this->getJson("/api/staff/{$this->regularStaff->id}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->regularStaff->id,
                    'employee_id' => $this->regularStaff->employee_id
                ]
            ]);
    }

    /** @test */
    public function user_cannot_view_others_staff_record_without_permission()
    {
        Sanctum::actingAs($this->regularUser);
        
        $response = $this->getJson("/api/staff/{$this->adminStaff->id}");
        
        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_create_staff()
    {
        Sanctum::actingAs($this->adminUser);
        
        $user = User::factory()->create();
        
        $staffData = [
            'user_id' => $user->id,
            'employee_id' => 'EMP' . rand(1000, 9999),
            'professional_title' => 'Senior Physician',
            'employment_status' => 'active',
            'employment_type' => 'full_time',
            'global_role_level' => 'attending_physician',
            'specialization_codes' => ['207R00000X'],
            'max_concurrent_patients' => 15,
            'average_appointment_duration_minutes' => 30
        ];
        
        $response = $this->postJson('/api/staff', $staffData);
        
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Staff created successfully.'
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'staff_uuid',
                    'employee_id'
                ]
            ]);
        
        $this->assertDatabaseHas('staff', [
            'employee_id' => $staffData['employee_id'],
            'user_id' => $user->id
        ]);
    }

    /** @test */
    public function validation_fails_with_invalid_data()
    {
        Sanctum::actingAs($this->adminUser);
        
        $staffData = [
            'employee_id' => '', // Required
            'professional_title' => '', // Required
            'employment_status' => 'invalid_status', // Invalid
            'global_role_level' => 'invalid_role' // Invalid
        ];
        
        $response = $this->postJson('/api/staff', $staffData);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'employee_id',
                'professional_title',
                'employment_status',
                'global_role_level'
            ]);
    }

    /** @test */
    public function admin_can_update_staff()
    {
        Sanctum::actingAs($this->adminUser);
        
        $staff = Staff::factory()->create();
        
        $updateData = [
            'professional_title' => 'Updated Title',
            'max_concurrent_patients' => 20
        ];
        
        $response = $this->putJson("/api/staff/{$staff->id}", $updateData);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Staff updated successfully.'
            ]);
        
        $this->assertDatabaseHas('staff', [
            'id' => $staff->id,
            'professional_title' => 'Updated Title'
        ]);
    }

    /** @test */
    public function admin_can_delete_staff()
    {
        Sanctum::actingAs($this->adminUser);
        
        $staff = Staff::factory()->create(['employment_status' => 'terminated']);
        
        $response = $this->deleteJson("/api/staff/{$staff->id}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Staff deleted successfully.'
            ]);
        
        $this->assertSoftDeleted('staff', ['id' => $staff->id]);
    }

    /** @test */
    public function cannot_delete_active_staff()
    {
        Sanctum::actingAs($this->adminUser);
        
        $staff = Staff::factory()->create(['employment_status' => 'active']);
        
        $response = $this->deleteJson("/api/staff/{$staff->id}");
        
        $response->assertStatus(400)
            ->assertJson([
                'success' => false
            ]);
        
        $this->assertDatabaseHas('staff', ['id' => $staff->id, 'deleted_at' => null]);
    }

    /** @test */
    public function admin_can_update_staff_status()
    {
        Sanctum::actingAs($this->adminUser);
        
        $staff = Staff::factory()->create(['employment_status' => 'active']);
        
        $response = $this->patchJson("/api/staff/{$staff->id}/status", [
            'status' => 'on_leave',
            'reason' => 'Vacation'
        ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Employment status updated successfully.'
            ]);
        
        $this->assertDatabaseHas('staff', [
            'id' => $staff->id,
            'employment_status' => 'on_leave'
        ]);
    }

    /** @test */
    public function admin_can_view_expiring_credentials()
    {
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->getJson('/api/staff/expiring-credentials?days=30');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'expiring_licenses',
                    'expiring_dea_registrations',
                    'total_expiring'
                ]
            ]);
    }

    /** @test */
    public function regular_user_cannot_view_expiring_credentials()
    {
        Sanctum::actingAs($this->regularUser);
        
        $response = $this->getJson('/api/staff/expiring-credentials');
        
        $response->assertStatus(403);
    }

    /** @test */
    public function can_validate_staff_action()
    {
        Sanctum::actingAs($this->adminUser);
        
        $staff = Staff::factory()->create([
            'employment_status' => 'active',
            'can_order_controlled_substances' => true,
            'prescribing_authority' => ['Schedule II']
        ]);
        
        $response = $this->postJson("/api/staff/{$staff->id}/validate-action", [
            'action' => 'prescribe_medication'
        ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'valid' => true
                ]
            ]);
    }

    /** @test */
    public function admin_can_update_staff_license()
    {
        Sanctum::actingAs($this->adminUser);
        
        $staff = Staff::factory()->create();
        
        $licenseData = [
            'license_number_encrypted' => 'encrypted_license_123',
            'license_number_hash' => Hash::make('license_123'),
            'issuing_state' => 'CA',
            'expiry_date' => now()->addYear()->toDateString()
        ];
        
        $response = $this->patchJson("/api/staff/{$staff->id}/license", $licenseData);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);
    }

    /** @test */
    public function filters_work_correctly()
    {
        Sanctum::actingAs($this->adminUser);
        
        Staff::factory()->create(['employment_status' => 'active', 'global_role_level' => 'attending_physician']);
        Staff::factory()->create(['employment_status' => 'on_leave', 'global_role_level' => 'registered_nurse']);
        
        $response = $this->getJson('/api/staff?employment_status=active&global_role_level=attending_physician');
        
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function pagination_works_correctly()
    {
        Sanctum::actingAs($this->adminUser);
        
        Staff::factory()->count(25)->create();
        
        $response = $this->getJson('/api/staff?per_page=10');
        
        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total'
                ]
            ]);
    }
}