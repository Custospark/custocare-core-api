<?php

namespace Tests\Feature\Unit;

use App\Models\PatientConsent;
use App\Repositories\Contracts\PatientConsentRepositoryInterface;
use App\Services\PatientConsent\PatientConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PatientConsentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $repositoryMock;
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->repositoryMock = Mockery::mock(PatientConsentRepositoryInterface::class);
        $this->service = new PatientConsentService($this->repositoryMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_get_consent_by_uuid_successfully()
    {
        $consent = PatientConsent::factory()->make(['consent_uuid' => 'test-uuid-123']);
        
        $this->repositoryMock->shouldReceive('findByUuid')
            ->with('test-uuid-123')
            ->once()
            ->andReturn($consent);

        $result = $this->service->getConsentByUuid('test-uuid-123');

        $this->assertTrue($result['success']);
        $this->assertEquals('Consent retrieved successfully', $result['message']);
        $this->assertEquals($consent, $result['data']);
        $this->assertEquals(200, $result['status']);
    }

    /** @test */
    public function it_returns_not_found_when_consent_uuid_does_not_exist()
    {
        $this->repositoryMock->shouldReceive('findByUuid')
            ->with('non-existent-uuid')
            ->once()
            ->andReturn(null);

        $result = $this->service->getConsentByUuid('non-existent-uuid');

        $this->assertFalse($result['success']);
        $this->assertEquals('Consent not found', $result['message']);
        $this->assertEquals(404, $result['status']);
    }

    /** @test */
    public function it_can_create_consent_successfully()
    {
        $data = [
            'patient_id' => 1,
            'consent_type' => 'treatment',
            'legal_basis' => 'explicit_consent',
            'granted_at' => now()->subDay(),
            'effective_from' => now(),
            'consent_form_version' => '1.0',
            'consent_document_hash' => str_repeat('a', 64),
            'patient_signature_hash' => str_repeat('b', 128),
        ];

        $consent = PatientConsent::factory()->make($data);

        $this->repositoryMock->shouldReceive('findActiveConsent')
            ->with(1, 'treatment')
            ->once()
            ->andReturn(null);

        $this->repositoryMock->shouldReceive('create')
            ->with($data)
            ->once()
            ->andReturn($consent);

        $result = $this->service->createConsent($data);

        $this->assertTrue($result['success']);
        $this->assertEquals('Consent created successfully', $result['message']);
        $this->assertEquals($consent, $result['data']);
        $this->assertEquals(201, $result['status']);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_consent()
    {
        $data = [
            'patient_id' => 1,
            // Missing required fields
        ];

        $result = $this->service->createConsent($data);

        $this->assertFalse($result['success']);
        $this->assertEquals('Validation failed', $result['message']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertEquals(422, $result['status']);
    }

    /** @test */
    public function it_prevents_duplicate_active_consent_creation()
    {
        $data = [
            'patient_id' => 1,
            'consent_type' => 'treatment',
            'legal_basis' => 'explicit_consent',
            'granted_at' => now()->subDay(),
            'effective_from' => now(),
            'consent_form_version' => '1.0',
            'consent_document_hash' => str_repeat('a', 64),
            'patient_signature_hash' => str_repeat('b', 128),
        ];

        $existingConsent = PatientConsent::factory()->make();

        $this->repositoryMock->shouldReceive('findActiveConsent')
            ->with(1, 'treatment')
            ->once()
            ->andReturn($existingConsent);

        $result = $this->service->createConsent($data);

        $this->assertFalse($result['success']);
        $this->assertEquals('Patient already has an active consent of this type', $result['message']);
        $this->assertEquals(409, $result['status']);
    }

    /** @test */
    public function it_can_validate_consent_successfully()
    {
        $patientId = 1;
        $consentType = 'treatment';
        $scopeCheck = ['facility_id' => 1];
        
        $consent = PatientConsent::factory()->make(['status' => 'active']);

        $this->repositoryMock->shouldReceive('findActiveConsent')
            ->with($patientId, $consentType)
            ->once()
            ->andReturn($consent);

        $this->repositoryMock->shouldReceive('validateScope')
            ->with($consent, $scopeCheck)
            ->once()
            ->andReturn(true);

        $result = $this->service->validateConsent($patientId, $consentType, $scopeCheck);

        $this->assertTrue($result['success']);
        $this->assertEquals('Consent is valid', $result['message']);
        $this->assertTrue($result['valid']);
        $this->assertEquals($consent, $result['consent']);
    }

    /** @test */
    public function it_can_revoke_consent_successfully()
    {
        $uuid = 'test-uuid-123';
        $revocationData = [
            'revocation_reason' => 'Patient requested revocation',
            'revoked_by_staff_id' => 1,
        ];

        $consent = PatientConsent::factory()->make(['consent_uuid' => $uuid]);
        
        $this->repositoryMock->shouldReceive('findByUuid')
            ->with($uuid)
            ->once()
            ->andReturn($consent);

        $this->repositoryMock->shouldReceive('revoke')
            ->with($consent, $revocationData)
            ->once()
            ->andReturn(true);

        $result = $this->service->revokeConsent($uuid, $revocationData);

        $this->assertTrue($result['success']);
        $this->assertEquals('Consent revoked successfully', $result['message']);
    }

    /** @test */
    public function it_returns_consent_types()
    {
        $result = $this->service->getConsentTypes();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('treatment', $result);
        $this->assertEquals('General Treatment Authorization', $result['treatment']);
    }

    /** @test */
    public function it_returns_legal_basis_options()
    {
        $result = $this->service->getLegalBasisOptions();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('explicit_consent', $result);
    }

    /** @test */
    public function it_handles_exceptions_gracefully()
    {
        $this->repositoryMock->shouldReceive('findByUuid')
            ->andThrow(new \Exception('Database connection failed'));

        $result = $this->service->getConsentByUuid('test-uuid');

        $this->assertFalse($result['success']);
        $this->assertEquals('An error occurred while retrieving consent', $result['message']);
        $this->assertEquals(500, $result['status']);
    }
}