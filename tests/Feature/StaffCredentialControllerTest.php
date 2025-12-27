<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\StaffCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffCredentialControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $staff;
    protected $admin;
    protected $complianceOfficer;
    protected $credential;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users with different roles
        $this->staff = Staff::factory()->create(['role' => 'staff']);
        $this->admin = Staff::factory()->create(['role' => 'admin']);
        $this->complianceOfficer = Staff::factory()->create(['role' => 'compliance']);
        
        // Create a test credential
        $this->credential = StaffCredential::factory()->create([
            'staff_id' => $this->staff->id,
            'created_by_staff_id' => $this->admin->id,
        ]);
    }

    /** @test */
    public function admin_can_view_all_credentials()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/credentials');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta'
            ]);
    }

    /** @test */
    public function staff_can_view_their_own_credentials()
    {
        $response = $this->actingAs($this->staff)
            ->getJson('/api/v1/credentials/staff/' . $this->staff->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);
    }

    /** @test */
    public function staff_cannot_view_other_staff_credentials()
    {
        $otherStaff = Staff::factory()->create(['role' => 'staff']);
        
        $response = $this->actingAs($this->staff)
            ->getJson('/api/v1/credentials/staff/' . $otherStaff->id);

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_create_credential()
    {
        $credentialData = [
            'staff_id' => $this->staff->id,
            'credential_type' => 'medical_license',
            'credential_name' => 'State Medical License',
            'issuing_authority' => 'State Medical Board',
            'issued_date' => '2023-01-01',
            'valid_from' => '2023-01-01',
            'valid_to' => '2024-12-31',
            'verification_status' => 'pending',
            'credential_document_hash' => 'sha256-hash-here',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/credentials', $credentialData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'credential_uuid',
                    'credential_name',
                    'credential_type'
                ]
            ]);
    }

    /** @test */
    public function staff_cannot_create_credential()
    {
        $credentialData = [
            'staff_id' => $this->staff->id,
            'credential_type' => 'medical_license',
            'credential_name' => 'State Medical License',
        ];

        $response = $this->actingAs($this->staff)
            ->postJson('/api/v1/credentials', $credentialData);

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_update_credential()
    {
        $updateData = [
            'credential_name' => 'Updated License Name',
        ];

        $response = $this->actingAs($this->admin)
            ->putJson('/api/v1/credentials/' . $this->credential->credential_uuid, $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('data.credential_name', 'Updated License Name');
    }

    /** @test */
    public function compliance_officer_can_verify_credential()
    {
        $verificationData = [
            'verification_method' => 'document_review',
            'verification_notes' => 'All documents verified',
        ];

        $response = $this->actingAs($this->complianceOfficer)
            ->postJson('/api/v1/credentials/' . $this->credential->credential_uuid . '/verify', $verificationData);

        $response->assertStatus(200)
            ->assertJsonPath('data.verification_status', 'verified');
    }

    /** @test */
    public function staff_cannot_verify_credential()
    {
        $verificationData = [
            'verification_method' => 'document_review',
        ];

        $response = $this->actingAs($this->staff)
            ->postJson('/api/v1/credentials/' . $this->credential->credential_uuid . '/verify', $verificationData);

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_supersede_credential()
    {
        $newCredentialData = [
            'staff_id' => $this->staff->id,
            'credential_type' => 'medical_license',
            'credential_name' => 'Renewed Medical License',
            'issuing_authority' => 'State Medical Board',
            'issued_date' => '2024-01-01',
            'valid_from' => '2024-01-01',
            'valid_to' => '2025-12-31',
            'verification_status' => 'pending',
            'credential_document_hash' => 'sha256-new-hash',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/credentials/' . $this->credential->credential_uuid . '/supersede', $newCredentialData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'old_credential',
                    'new_credential'
                ]
            ]);
    }

    /** @test */
    public function admin_can_view_expiring_credentials()
    {
        // Create an expiring credential
        StaffCredential::factory()->create([
            'staff_id' => $this->staff->id,
            'valid_to' => now()->addDays(15),
            'is_current' => true,
            'verification_status' => 'verified',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/credentials/expiring');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'count'
            ]);
    }

    /** @test */
    public function admin_can_view_statistics()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/credentials/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'total',
                    'verified',
                    'expired',
                    'pending',
                    'current',
                    'expiring_soon',
                    'by_type'
                ]
            ]);
    }

    /** @test */
    public function validation_fails_with_invalid_data()
    {
        $invalidData = [
            'staff_id' => 'invalid',
            'credential_type' => 'invalid_type',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/credentials', $invalidData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors'
            ]);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_credential()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/credentials/nonexistent-uuid');

        $response->assertStatus(404);
    }
}