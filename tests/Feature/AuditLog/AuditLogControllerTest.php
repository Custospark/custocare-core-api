<?php

namespace Tests\Feature\AuditLog;

use Tests\TestCase;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class AuditLogControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @var User */
    protected $adminUser;

    /** @var User */
    protected $complianceOfficer;

    /** @var User */
    protected $regularUser;

    /** @var AuditLog */
    protected $auditLog;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users with different roles
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('system_admin');

        $this->complianceOfficer = User::factory()->create();
        $this->complianceOfficer->assignRole('compliance_officer');

        $this->regularUser = User::factory()->create();
        $this->regularUser->assignRole('healthcare_provider');

        // Create test audit log
        $this->auditLog = AuditLog::factory()->create([
            'facility_id' => 1,
            'patient_id' => 1,
            'phi_accessed' => true,
            'legal_hold_flag' => false,
        ]);
    }

    /** @test */
    public function admin_can_view_audit_logs_index()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/audit-logs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'audit_uuid',
                            'timestamp',
                            'operation',
                            'entity' => ['type', 'id', 'identifier', 'name'],
                            'performed_by' => ['type', 'id', 'identifier', 'role', 'name'],
                            'request_context' => ['request_id', 'session_id', 'user_ip'],
                            'compliance' => ['reason', 'legal_hold', 'justification'],
                            'patient_privacy' => ['patient_id', 'phi_accessed'],
                            'system' => ['created_at', 'age_days', 'retention_status'],
                            'links' => ['self'],
                        ]
                    ],
                    'meta' => [
                        'total',
                        'count',
                        'per_page',
                        'current_page',
                        'total_pages',
                        'links' => ['first', 'last', 'prev', 'next'],
                    ],
                ],
                'message',
            ]);
    }

    /** @test */
    public function compliance_officer_can_view_audit_logs_index()
    {
        $response = $this->actingAs($this->complianceOfficer)
            ->getJson('/api/audit-logs');

        $response->assertStatus(200);
    }

    /** @test */
    public function regular_user_cannot_view_audit_logs_index()
    {
        $response = $this->actingAs($this->regularUser)
            ->getJson('/api/audit-logs');

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_view_specific_audit_log()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/audit-logs/{$this->auditLog->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->auditLog->id,
                    'audit_uuid' => $this->auditLog->audit_uuid,
                ],
            ]);
    }

    /** @test */
    public function admin_can_create_audit_log()
    {
        $data = [
            'operation' => 'read',
            'entity_type' => 'patient',
            'entity_id' => 1,
            'entity_identifier' => 'Patient #1',
            'performed_by_type' => 'staff',
            'performed_by_id' => $this->adminUser->id,
            'performed_by_identifier' => $this->adminUser->email,
            'request_id' => 'test-request-' . uniqid(),
            'compliance_reason' => 'treatment',
            'result' => 'success',
            'phi_accessed' => true,
            'phi_fields_accessed' => ['medical_history', 'diagnosis'],
            'justification' => 'Treatment planning',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/audit-logs', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Audit log created successfully.',
            ])
            ->assertJsonStructure([
                'data' => ['id', 'audit_uuid', 'created_at'],
            ]);
    }

    /** @test */
    public function regular_user_cannot_create_audit_log()
    {
        $data = [
            'operation' => 'read',
            'entity_type' => 'patient',
            'entity_id' => 1,
            'request_id' => 'test-request',
            'compliance_reason' => 'treatment',
            'result' => 'success',
        ];

        $response = $this->actingAs($this->regularUser)
            ->postJson('/api/audit-logs', $data);

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_place_audit_log_under_legal_hold()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/audit-logs/{$this->auditLog->id}/legal-hold", [
                'reason' => 'Legal investigation regarding patient data access',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Audit log placed under legal hold successfully.',
            ]);
    }

    /** @test */
    public function admin_can_release_audit_log_from_legal_hold()
    {
        // First place under legal hold
        $this->auditLog->update(['legal_hold_flag' => true]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/audit-logs/{$this->auditLog->id}/legal-hold", [
                'reason' => 'Investigation completed',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Audit log released from legal hold successfully.',
            ]);
    }

    /** @test */
    public function admin_can_view_phi_access_logs()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/audit-logs/phi-access');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'logs',
                    'count',
                    'phi_fields_summary',
                ],
            ]);
    }

    /** @test */
    public function compliance_officer_can_view_hippa_accounting()
    {
        $response = $this->actingAs($this->complianceOfficer)
            ->getJson("/api/audit-logs/patient/{$this->auditLog->patient_id}/hippa-accounting");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'patient_id',
                    'logs',
                    'total_disclosures',
                    'disclosures_by_reason',
                    'period_covered',
                ],
            ]);
    }

    /** @test */
    public function admin_can_view_audit_log_statistics()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/audit-logs/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'total_logs',
                    'phi_access_logs',
                    'legal_hold_logs',
                    'successful_operations',
                    'failed_operations',
                    'average_duration_ms',
                    'by_operation',
                    'by_compliance_reason',
                ],
            ]);
    }

    /** @test */
    public function admin_can_export_audit_logs()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/audit-logs/export', [
                'format' => 'csv',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Audit logs exported successfully.',
            ]);
    }

    /** @test */
    public function validation_fails_with_invalid_data()
    {
        $data = [
            'operation' => 'invalid_operation',
            'compliance_reason' => 'invalid_reason',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/audit-logs', $data);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['operation', 'compliance_reason'],
            ]);
    }

    /** @test */
    public function audit_log_immutability_is_enforced()
    {
        $data = [
            'operation' => 'update', // Trying to change operation
            'result' => 'failure',
        ];

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/audit-logs/{$this->auditLog->id}", $data);

        // Should fail because only legal_hold_flag can be updated
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Only legal hold status can be updated.',
            ]);
    }

    /** @test */
    public function cannot_delete_audit_log_under_legal_hold()
    {
        $auditLog = AuditLog::factory()->create(['legal_hold_flag' => true]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/audit-logs/{$auditLog->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot delete audit log under legal hold.',
            ]);
    }

    /** @test */
    public function admin_can_run_archival_process()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/audit-logs/archive', [
                'batch_size' => 100,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function regular_user_cannot_run_archival_process()
    {
        $response = $this->actingAs($this->regularUser)
            ->postJson('/api/audit-logs/archive');

        $response->assertStatus(403);
    }

    /** @test */
    public function filters_work_correctly()
    {
        // Create audit logs with different operations
        AuditLog::factory()->create(['operation' => 'create', 'result' => 'success']);
        AuditLog::factory()->create(['operation' => 'read', 'result' => 'failure']);
        AuditLog::factory()->create(['operation' => 'update', 'result' => 'success']);

        // Filter by operation
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/audit-logs?operation=create');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals(1, $data['data']['meta']['pagination']['total']);

        // Filter by result
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/audit-logs?result=failure');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals(1, $data['data']['meta']['pagination']['total']);
    }

    /** @test */
    public function pagination_works_correctly()
    {
        // Create multiple audit logs
        AuditLog::factory()->count(25)->create();

        // Request first page with 10 items
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/audit-logs?per_page=10&page=1');

        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertEquals(10, count($data['data']['data']));
        $this->assertEquals(1, $data['data']['meta']['pagination']['current_page']);
        $this->assertEquals(10, $data['data']['meta']['pagination']['per_page']);
        $this->assertEquals(3, $data['data']['meta']['pagination']['total_pages']);
        $this->assertNotNull($data['data']['meta']['pagination']['links']['next']);
    }

    /** @test */
    public function sorting_works_correctly()
    {
        // Create audit logs with different creation times
        $oldLog = AuditLog::factory()->create(['created_at' => now()->subDays(2)]);
        $newLog = AuditLog::factory()->create(['created_at' => now()]);

        // Sort by created_at descending (default)
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/audit-logs?sort_by=created_at&sort_direction=desc');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals($newLog->id, $data['data']['data'][0]['id']);

        // Sort by created_at ascending
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/audit-logs?sort_by=created_at&sort_direction=asc');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals($oldLog->id, $data['data']['data'][0]['id']);
    }
}