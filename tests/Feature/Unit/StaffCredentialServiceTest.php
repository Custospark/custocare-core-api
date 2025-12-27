<?php

namespace Tests\Unit;

use App\Models\StaffCredential;
use App\Repositories\Contracts\StaffCredentialRepositoryInterface;
use App\Services\StaffCredential\StaffCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class StaffCredentialServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $repositoryMock;
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repositoryMock = Mockery::mock(StaffCredentialRepositoryInterface::class);
        $this->service = new StaffCredentialService($this->repositoryMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_get_credential_by_uuid()
    {
        $credential = StaffCredential::factory()->make(['credential_uuid' => 'test-uuid-123']);
        
        $this->repositoryMock
            ->shouldReceive('findByUuid')
            ->with('test-uuid-123')
            ->once()
            ->andReturn($credential);

        $result = $this->service->getCredential('test-uuid-123');

        $this->assertTrue($result['success']);
        $this->assertEquals('Credential retrieved successfully', $result['message']);
        $this->assertEquals($credential, $result['data']);
    }

    /** @test */
    public function it_returns_error_when_credential_not_found()
    {
        $this->repositoryMock
            ->shouldReceive('findByUuid')
            ->with('non-existent-uuid')
            ->once()
            ->andReturn(null);

        $result = $this->service->getCredential('non-existent-uuid');

        $this->assertFalse($result['success']);
        $this->assertEquals('Credential not found', $result['message']);
        $this->assertEquals(404, $result['status']);
    }

    /** @test */
    public function it_can_create_credential_with_valid_data()
    {
        $credentialData = [
            'staff_id' => 1,
            'credential_type' => 'medical_license',
            'credential_name' => 'State Medical License',
            'issuing_authority' => 'State Medical Board',
            'issued_date' => '2023-01-01',
            'valid_from' => '2023-01-01',
            'valid_to' => '2024-12-31',
            'verification_status' => 'pending',
            'credential_document_hash' => 'sha256-hash-here',
        ];

        $createdCredential = StaffCredential::factory()->make($credentialData);
        
        $this->repositoryMock
            ->shouldReceive('create')
            ->with(Mockery::on(function ($arg) use ($credentialData) {
                return $arg['staff_id'] === $credentialData['staff_id']
                    && $arg['credential_type'] === $credentialData['credential_type'];
            }))
            ->once()
            ->andReturn($createdCredential);

        $result = $this->service->createCredential($credentialData);

        $this->assertTrue($result['success']);
        $this->assertEquals('Credential created successfully', $result['message']);
        $this->assertEquals(201, $result['status']);
    }

    /** @test */
    public function it_validates_credential_data_before_creation()
    {
        $invalidData = [
            'staff_id' => 'invalid',
            'credential_type' => 'invalid_type',
        ];

        $result = $this->service->createCredential($invalidData);

        $this->assertFalse($result['success']);
        $this->assertEquals('Validation failed', $result['message']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertEquals(422, $result['status']);
    }

    /** @test */
    public function it_can_verify_credential()
    {
        $verificationData = [
            'verified_by_staff_id' => 2,
            'verification_method' => 'document_review',
            'verification_notes' => 'All documents verified',
        ];

        $verifiedCredential = StaffCredential::factory()->make([
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);

        $this->repositoryMock
            ->shouldReceive('verify')
            ->with('test-uuid-123', $verificationData)
            ->once()
            ->andReturn($verifiedCredential);

        $result = $this->service->verifyCredential('test-uuid-123', $verificationData, 2);

        $this->assertTrue($result['success']);
        $this->assertEquals('Credential verified successfully', $result['message']);
    }

    /** @test */
    public function it_prevents_updating_current_verified_credentials()
    {
        $currentCredential = StaffCredential::factory()->make([
            'is_current' => true,
            'verification_status' => 'verified',
        ]);

        $updateData = [
            'credential_name' => 'Updated Name',
        ];

        $this->repositoryMock
            ->shouldReceive('findByUuid')
            ->with('test-uuid-123')
            ->once()
            ->andReturn($currentCredential);

        $result = $this->service->updateCredential('test-uuid-123', $updateData);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('cannot update current verified credential', $result['message']);
        $this->assertEquals(422, $result['status']);
    }

    /** @test */
    public function it_can_get_expiring_credentials()
    {
        $credentials = StaffCredential::factory()->count(3)->make();
        
        $this->repositoryMock
            ->shouldReceive('getExpiringSoon')
            ->with(30)
            ->once()
            ->andReturn($credentials);

        $result = $this->service->getExpiringCredentials();

        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['count']);
    }

    /** @test */
    public function it_can_supersede_credential()
    {
        $oldCredential = StaffCredential::factory()->make(['id' => 1]);
        $newCredential = StaffCredential::factory()->make(['id' => 2]);
        
        $newCredentialData = [
            'credential_name' => 'New License',
            'valid_to' => '2025-12-31',
        ];

        $this->repositoryMock
            ->shouldReceive('supersede')
            ->with('old-uuid', $newCredentialData)
            ->once()
            ->andReturn([
                'old_credential' => $oldCredential,
                'new_credential' => $newCredential,
            ]);

        $result = $this->service->supersedeCredential('old-uuid', $newCredentialData, 1);

        $this->assertTrue($result['success']);
        $this->assertEquals('Credential superseded successfully', $result['message']);
        $this->assertArrayHasKey('old_credential', $result['data']);
        $this->assertArrayHasKey('new_credential', $result['data']);
    }

    /** @test */
    public function it_returns_statistics()
    {
        // Since getStatistics doesn't use repository, we need to test it differently
        // This would involve creating actual records in the database
        $this->markTestIncomplete('Statistics test needs database setup');
    }
}