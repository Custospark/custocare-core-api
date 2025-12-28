<?php

namespace Tests\Unit\AuditLog;

use Tests\TestCase;
use App\Services\AuditLog\AuditLogService;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class AuditLogServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @var AuditLogService */
    protected $service;

    /** @var Mockery\MockInterface|AuditLogRepositoryInterface */
    protected $repositoryMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock repository
        $this->repositoryMock = Mockery::mock(AuditLogRepositoryInterface::class);
        
        // Create service instance with mocked repository
        $this->service = new AuditLogService($this->repositoryMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_creates_audit_log_successfully()
    {
        $data = [
            'operation' => 'read',
            'entity_type' => 'patient',
            'entity_id' => 1,
            'performed_by_type' => 'staff',
            'performed_by_id' => 1,
            'request_id' => 'test-request-id',
            'compliance_reason' => 'treatment',
            'result' => 'success',
        ];

        $auditLog = AuditLog::factory()->make($data);
        
        $this->repositoryMock->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) use ($data) {
                return $arg['operation'] === $data['operation']
                    && $arg['entity_type'] === $data['entity_type'];
            }))
            ->andReturn($auditLog);

        $result = $this->service->createAuditLog($data);

        $this->assertTrue($result['success']);
        $this->assertEquals('Audit log created successfully', $result['message']);
        $this->assertArrayHasKey('id', $result['data']);
        $this->assertArrayHasKey('audit_uuid', $result['data']);
        $this->assertArrayHasKey('created_at', $result['data']);
    }

    /** @test */
    public function it_returns_error_when_validation_fails()
    {
        $data = [
            'operation' => 'invalid_operation', // Invalid operation
            'compliance_reason' => 'invalid_reason', // Invalid compliance reason
        ];

        $result = $this->service->createAuditLog($data);

        $this->assertFalse($result['success']);
        $this->assertEquals('Validation failed', $result['message']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('operation', $result['errors']);
        $this->assertArrayHasKey('compliance_reason', $result['errors']);
    }

    /** @test */
    public function it_returns_audit_log_by_id()
    {
        $auditLog = AuditLog::factory()->create();
        
        $this->repositoryMock->shouldReceive('findById')
            ->once()
            ->with($auditLog->id)
            ->andReturn($auditLog);

        $result = $this->service->getAuditLog($auditLog->id);

        $this->assertTrue($result['success']);
        $this->assertEquals('Audit log retrieved successfully', $result['message']);
        $this->assertEquals($auditLog->id, $result['data']->id);
    }

    /** @test */
    public function it_returns_not_found_for_nonexistent_audit_log()
    {
        $this->repositoryMock->shouldReceive('findById')
            ->once()
            ->with(999)
            ->andReturn(null);

        $result = $this->service->getAuditLog(999);

        $this->assertFalse($result['success']);
        $this->assertEquals('Audit log not found', $result['message']);
        $this->assertNull($result['data']);
    }

    /** @test */
    public function it_gets_paginated_audit_logs_with_filters()
    {
        $filters = ['operation' => 'read', 'result' => 'success'];
        $perPage = 20;
        $sortBy = 'created_at';
        $sortDirection = 'desc';

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            AuditLog::factory()->count(5)->make(),
            50,
            $perPage,
            1
        );
        
        $this->repositoryMock->shouldReceive('paginateWithFilters')
            ->once()
            ->with($filters, $perPage, $sortBy, $sortDirection)
            ->andReturn($paginator);

        $result = $this->service->getPaginatedAuditLogs($filters, $perPage, $sortBy, $sortDirection);

        $this->assertTrue($result['success']);
        $this->assertEquals('Audit logs retrieved successfully', $result['message']);
        $this->assertCount(5, $result['data']['logs']);
        $this->assertArrayHasKey('pagination', $result['data']);
        $this->assertEquals(50, $result['data']['pagination']['total']);
        $this->assertEquals($perPage, $result['data']['pagination']['per_page']);
    }

    /** @test */
    public function it_gets_audit_logs_for_specific_entity()
    {
        $entityType = 'patient';
        $entityId = 1;
        $filters = ['operation' => 'read'];
        
        $logs = AuditLog::factory()->count(3)->make([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);
        
        $this->repositoryMock->shouldReceive('getForEntity')
            ->once()
            ->with($entityType, $entityId, $filters)
            ->andReturn($logs);

        $result = $this->service->getEntityAuditLogs($entityType, $entityId, $filters);

        $this->assertTrue($result['success']);
        $this->assertEquals('Entity audit logs retrieved successfully', $result['message']);
        $this->assertEquals($entityType, $result['data']['entity_type']);
        $this->assertEquals($entityId, $result['data']['entity_id']);
        $this->assertCount(3, $result['data']['logs']);
        $this->assertEquals(3, $result['data']['count']);
    }

    /** @test */
    public function it_gets_phi_access_audit_logs()
    {
        $filters = ['start_date' => '2024-01-01'];
        
        $logs = AuditLog::factory()->count(2)->make([
            'phi_accessed' => true,
            'phi_fields_accessed' => ['medical_history', 'diagnosis'],
        ]);
        
        $this->repositoryMock->shouldReceive('getPhiAccessLogs')
            ->once()
            ->with($filters)
            ->andReturn($logs);

        $result = $this->service->getPhiAccessAuditLogs($filters);

        $this->assertTrue($result['success']);
        $this->assertEquals('PHI access audit logs retrieved successfully', $result['message']);
        $this->assertCount(2, $result['data']['logs']);
        $this->assertEquals(2, $result['data']['count']);
        $this->assertArrayHasKey('phi_fields_summary', $result['data']);
    }

    /** @test */
    public function it_gets_audit_log_statistics()
    {
        $filters = ['result' => 'success'];
        
        $stats = [
            'total_logs' => 100,
            'phi_access_logs' => 25,
            'legal_hold_logs' => 5,
            'successful_operations' => 80,
            'failed_operations' => 20,
            'average_duration_ms' => 150.5,
            'by_operation' => ['read' => 50, 'write' => 30, 'delete' => 20],
            'by_compliance_reason' => ['treatment' => 70, 'audit' => 20, 'research' => 10],
        ];
        
        $this->repositoryMock->shouldReceive('getStatistics')
            ->once()
            ->with($filters)
            ->andReturn($stats);

        $result = $this->service->getAuditLogStatistics($filters);

        $this->assertTrue($result['success']);
        $this->assertEquals('Audit log statistics retrieved successfully', $result['message']);
        $this->assertEquals($stats, $result['data']);
    }

    // /** @test */
    // public function it_places_audit_log_under_legal_hold()
    // {
    //     $auditLog = AuditLog::factory()->create(['legal_hold_flag' => false]);
        
    //     // Mock findById to return audit log
    //     $this->repositoryMock->shouldReceive('findById')
    //         ->once()
    //         ->with($auditLog->id)
    //         ->andReturn($auditLog);
        
    //     // Mock the database update (since we bypass Eloquent for legal hold updates)
    //     // We'll use a partial mock for this test
    //     $service = Mockery::mock(AuditLogService::class, [$this->repositoryMock])
    //         ->makePartial()
    //         ->shouldAllowMockingProtectedMethods();

    //     // $result = $service->placeUnderLegalHold($auditLog->id, 'Legal investigation');

    //     $this->assertTrue($result['success']);
    //     $this->assertEquals('Audit log placed under legal hold successfully', $result['message']);
    //     $this->assertEquals($auditLog->id, $result['data']['id']);
    //     $this->assertTrue($result['data']['legal_hold_flag']);
    // }

    /** @test */
    public function it_fails_to_place_nonexistent_audit_log_under_legal_hold()
    {
        $this->repositoryMock->shouldReceive('findById')
            ->once()
            ->with(999)
            ->andReturn(null);

        $result = $this->service->placeUnderLegalHold(999, 'Legal investigation');

        $this->assertFalse($result['success']);
        $this->assertEquals('Audit log not found', $result['message']);
    }

    /** @test */
    public function it_processes_archival_successfully()
    {
        $eligibleLogs = AuditLog::factory()->count(3)->make();
        
        $this->repositoryMock->shouldReceive('getEligibleForArchival')
            ->once()
            ->with(1000)
            ->andReturn($eligibleLogs);
        
        $this->repositoryMock->shouldReceive('markAsArchived')
            ->once()
            ->with(Mockery::type('array'))
            ->andReturn(3);

        $result = $this->service->processArchival(1000);

        $this->assertTrue($result['success']);
        $this->assertEquals('Audit logs archived successfully', $result['message']);
        $this->assertEquals(3, $result['data']['archived_count']);
        $this->assertEquals(3, $result['data']['total_eligible']);
    }

    /** @test */
    public function it_handles_no_logs_eligible_for_archival()
    {
        $this->repositoryMock->shouldReceive('getEligibleForArchival')
            ->once()
            ->with(1000)
            ->andReturn(collect([]));

        $result = $this->service->processArchival(1000);

        $this->assertTrue($result['success']);
        $this->assertEquals('No audit logs eligible for archival', $result['message']);
        $this->assertEquals(0, $result['data']['archived_count']);
    }

    /** @test */
    public function it_validates_audit_log_data_correctly()
    {
        // Test valid data
        $validData = [
            'operation' => 'read',
            'entity_type' => 'patient',
            'performed_by_type' => 'staff',
            'request_id' => 'test-request-id',
            'compliance_reason' => 'treatment',
            'result' => 'success',
            'phi_accessed' => true,
            'phi_fields_accessed' => ['field1', 'field2'],
        ];

        $result = $this->service->validateAuditLogData($validData);
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);

        // Test invalid data
        $invalidData = [
            'operation' => 'invalid',
            'compliance_reason' => 'invalid',
        ];

        $result = $this->service->validateAuditLogData($invalidData);
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('operation', $result['errors']);
        $this->assertArrayHasKey('compliance_reason', $result['errors']);
        $this->assertArrayHasKey('performed_by_type', $result['errors']);
        $this->assertArrayHasKey('request_id', $result['errors']);
        $this->assertArrayHasKey('result', $result['errors']);
    }

    /** @test */
    public function it_handles_business_logic_validation()
    {
        // Test: PHI accessed but no fields specified
        $data = [
            'operation' => 'read',
            'entity_type' => 'patient',
            'performed_by_type' => 'staff',
            'request_id' => 'test-request-id',
            'compliance_reason' => 'treatment',
            'result' => 'success',
            'phi_accessed' => true,
            // Missing phi_fields_accessed
        ];

        $result = $this->service->validateAuditLogData($data);
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('phi_fields_accessed', $result['errors']);

        // Test: Break glass access without justification
        $data = [
            'operation' => 'read',
            'entity_type' => 'patient',
            'performed_by_type' => 'staff',
            'request_id' => 'test-request-id',
            'compliance_reason' => 'break_glass',
            'result' => 'success',
            // Missing justification
        ];

        $result = $this->service->validateAuditLogData($data);
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('justification', $result['errors']);

        // Test: Failed operation without reason
        $data = [
            'operation' => 'read',
            'entity_type' => 'patient',
            'performed_by_type' => 'staff',
            'request_id' => 'test-request-id',
            'compliance_reason' => 'treatment',
            'result' => 'failure',
            // Missing failure_reason
        ];

        $result = $this->service->validateAuditLogData($data);
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('failure_reason', $result['errors']);
    }
}