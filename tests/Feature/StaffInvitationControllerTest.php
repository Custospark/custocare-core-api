<?php

namespace Tests\Feature;

use App\Models\StaffInvitation;
use App\Models\Staff;
use App\Models\Facility;
use App\Models\Department;
use App\Models\FacilityStaffRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffInvitationControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $adminUser;
    protected User $staffUser;
    protected User $managerUser;
    protected Staff $staff;
    protected Facility $facility;
    protected Department $department;
    protected FacilityStaffRole $role;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->adminUser = User::factory()->create(['email' => 'admin@test.com']);
        $this->adminUser->assignRole('admin');

        $this->staffUser = User::factory()->create(['email' => 'staff@test.com']);
        $this->staffUser->assignRole('staff');

        $this->managerUser = User::factory()->create(['email' => 'manager@test.com']);
        $this->managerUser->assignRole('facility_manager');

        // Create related models
        $this->staff = Staff::factory()->create(['user_id' => $this->staffUser->id]);
        $this->facility = Facility::factory()->create();
        $this->department = Department::factory()->create(['facility_id' => $this->facility->id]);
        $this->role = FacilityStaffRole::factory()->create();

        // Associate manager with facility
        $this->managerUser->managedFacilities()->attach($this->facility->id);
    }

    /** @test */
    public function it_can_list_invitations_for_admin(): void
    {
        StaffInvitation::factory()->count(5)->create();

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/staff-invitations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'invitation_uuid',
                        'status',
                        'sent_at',
                        'staff',
                        'facility',
                    ]
                ],
                'meta'
            ]);
    }

    /** @test */
    public function it_can_create_invitation_as_admin(): void
    {
        $staff = Staff::factory()->create();
        $facility = Facility::factory()->create();

        $payload = [
            'staff_id' => $staff->id,
            'facility_id' => $facility->id,
            'department_id' => $this->department->id,
            'role_id' => $this->role->id,
            'expires_at' => now()->addDays(7)->toDateTimeString(),
            'metadata' => [
                'message' => 'Welcome to our facility!',
                'reason' => 'New department opening'
            ]
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/staff-invitations', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Invitation created and sent successfully.'
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'invitation_uuid',
                    'status',
                    'sent_at',
                    'expires_at'
                ]
            ]);

        $this->assertDatabaseHas('staff_invitations', [
            'staff_id' => $payload['staff_id'],
            'facility_id' => $payload['facility_id'],
            'status' => 'pending'
        ]);
    }

    /** @test */
    public function it_validates_required_fields_on_create(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/staff-invitations', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed. Please check your input.'
            ])
            ->assertJsonValidationErrors([
                'staff_id',
                'facility_id'
            ]);
    }

    /** @test */
    public function it_can_show_invitation(): void
    {
        $invitation = StaffInvitation::factory()->create([
            'invited_by_staff_id' => $this->staff->id
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/staff-invitations/{$invitation->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $invitation->id,
                    'invitation_uuid' => $invitation->invitation_uuid,
                ]
            ]);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_invitation(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/staff-invitations/99999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Invitation not found.'
            ]);
    }

    /** @test */
    public function it_can_update_invitation(): void
    {
        $invitation = StaffInvitation::factory()->create([
            'status' => 'pending',
            'invited_by_staff_id' => $this->staff->id
        ]);

        $payload = [
            'department_id' => $this->department->id,
            'expires_at' => now()->addDays(14)->toDateTimeString(),
            'metadata' => ['message' => 'Updated invitation message']
        ];

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/staff-invitations/{$invitation->id}", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Invitation updated successfully.'
            ]);

        $this->assertDatabaseHas('staff_invitations', [
            'id' => $invitation->id,
            'department_id' => $payload['department_id']
        ]);
    }

    /** @test */
    public function it_cannot_update_non_pending_invitation(): void
    {
        $invitation = StaffInvitation::factory()->create([
            'status' => 'accepted',
            'invited_by_staff_id' => $this->staff->id
        ]);

        $payload = [
            'department_id' => $this->department->id,
        ];

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/staff-invitations/{$invitation->id}", $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot update invitation details after it has been responded to.'
            ]);
    }

    /** @test */
    public function it_can_delete_pending_invitation(): void
    {
        $invitation = StaffInvitation::factory()->create([
            'status' => 'pending',
            'invited_by_staff_id' => $this->staff->id
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/staff-invitations/{$invitation->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Invitation deleted successfully.'
            ]);

        $this->assertSoftDeleted('staff_invitations', ['id' => $invitation->id]);
    }

    /** @test */
    public function it_cannot_delete_accepted_invitation(): void
    {
        $invitation = StaffInvitation::factory()->create([
            'status' => 'accepted',
            'invited_by_staff_id' => $this->staff->id
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/staff-invitations/{$invitation->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot delete an accepted invitation.'
            ]);

        $this->assertDatabaseHas('staff_invitations', ['id' => $invitation->id]);
    }

    /** @test */
    public function staff_can_accept_their_own_invitation(): void
    {
        $invitation = StaffInvitation::factory()->create([
            'staff_id' => $this->staff->id,
            'status' => 'pending',
            'expires_at' => now()->addDays(7)
        ]);

        $response = $this->actingAs($this->staffUser)
            ->postJson("/api/staff-invitations/{$invitation->id}/accept");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Invitation accepted successfully.'
            ]);

        $this->assertDatabaseHas('staff_invitations', [
            'id' => $invitation->id,
            'status' => 'accepted',
            'responded_at' => now()
        ]);
    }

    /** @test */
    public function staff_cannot_accept_others_invitation(): void
    {
        $otherStaff = Staff::factory()->create();
        $invitation = StaffInvitation::factory()->create([
            'staff_id' => $otherStaff->id,
            'status' => 'pending'
        ]);

        $response = $this->actingAs($this->staffUser)
            ->postJson("/api/staff-invitations/{$invitation->id}/accept");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You are not authorized to accept this invitation.'
            ]);
    }

    /** @test */
    public function staff_can_decline_their_own_invitation(): void
    {
        $invitation = StaffInvitation::factory()->create([
            'staff_id' => $this->staff->id,
            'status' => 'pending'
        ]);

        $response = $this->actingAs($this->staffUser)
            ->postJson("/api/staff-invitations/{$invitation->id}/decline");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Invitation declined successfully.'
            ]);

        $this->assertDatabaseHas('staff_invitations', [
            'id' => $invitation->id,
            'status' => 'declined',
            'responded_at' => now()
        ]);
    }

    /** @test */
    public function manager_can_resend_invitation_for_their_facility(): void
    {
        $invitation = StaffInvitation::factory()->create([
            'facility_id' => $this->facility->id,
            'status' => 'pending'
        ]);

        $response = $this->actingAs($this->managerUser)
            ->postJson("/api/staff-invitations/{$invitation->id}/resend");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Invitation resent successfully.'
            ]);

        $this->assertDatabaseHas('staff_invitations', [
            'id' => $invitation->id,
            'sent_at' => now()
        ]);
    }

    /** @test */
    public function it_can_list_my_invitations(): void
    {
        StaffInvitation::factory()->create(['staff_id' => $this->staff->id]);
        StaffInvitation::factory()->create(['staff_id' => $this->staff->id]);
        StaffInvitation::factory()->create(); // Different staff

        $response = $this->actingAs($this->staffUser)
            ->getJson('/api/staff-invitations/my/invitations');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function it_can_filter_invitations_by_status(): void
    {
        StaffInvitation::factory()->create(['status' => 'pending']);
        StaffInvitation::factory()->create(['status' => 'accepted']);
        StaffInvitation::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/staff-invitations?status=pending');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function it_handles_server_errors_gracefully(): void
    {
        // Mock a service failure
        $this->mock(\App\Services\Contracts\StaffInvitationServiceInterface::class, function ($mock) {
            $mock->shouldReceive('getAllInvitations')
                ->andThrow(new \Exception('Database connection failed'));
        });

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/staff-invitations');

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving invitations.'
            ]);
    }
}