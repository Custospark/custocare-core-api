<?php

namespace Tests\Unit;

use App\Models\ClinicalEncounter;
use App\Models\Patient;
use App\Models\Staff;
use App\Models\Visit;
use App\Models\Facility;
use App\Models\Department;
use App\Repositories\Contracts\ClinicalEncounterRepositoryInterface;
use App\Services\ClinicalEncounter\ClinicalEncounterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Mockery;
use Tests\TestCase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ClinicalEncounterServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * @var ClinicalEncounterService
     */
    protected $service;

    /**
     * @var Mockery\MockInterface|ClinicalEncounterRepositoryInterface
     */
    protected $repositoryMock;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->repositoryMock = Mockery::mock(ClinicalEncounterRepositoryInterface::class);
        $this->service = new ClinicalEncounterService($this->repositoryMock);
    }

    /**
     * Clean up the test environment.
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test getAllEncounters method returns paginated results.
     */
    public function test_get_all_encounters_returns_paginated_results(): void
    {
        $paginator = $this->createMock(LengthAwarePaginator::class);
        
        $this->repositoryMock
            ->shouldReceive('getAllPaginated')
            ->with([], 15)
            ->once()
            ->andReturn($paginator);

        $result = $this->service->getAllEncounters();

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
    }

    /**
     * Test createEncounter method creates encounter with audit trail.
     */
    public function test_create_encounter_creates_with_audit_trail(): void
    {
        $data = [
            'facility_id' => 1,
            'visit_id' => 1,
            'patient_id' => 1,
            'encounter_type' => 'initial_consultation',
            'primary_provider_staff_id' => 1,
            'department_id' => 1,
            'assessment_diagnosis_codes' => [['code' => 'I10', 'description' => 'Hypertension']],
            'clinical_impression' => 'Test impression',
            'treatment_plan' => 'Test plan',
        ];

        $encounter = ClinicalEncounter::factory()->make($data);
        
        $this->repositoryMock
            ->shouldReceive('create')
            ->with(Mockery::on(function ($arg) {
                return isset($arg['created_by_staff_id']) && isset($arg['updated_by_staff_id']);
            }))
            ->once()
            ->andReturn($encounter);

        $result = $this->service->createEncounter($data);

        $this->assertInstanceOf(ClinicalEncounter::class, $result);
    }

    /**
     * Test createEncounter throws exception for invalid clinical data.
     */
    public function test_create_encounter_throws_exception_for_invalid_data(): void
    {
        $this->expectException(\RuntimeException::class);

        $data = [
            // Missing required fields
            'encounter_type' => 'initial_consultation',
        ];

        $this->service->createEncounter($data);
    }

    /**
     * Test updateEncounter method updates encounter.
     */
    public function test_update_encounter_updates_successfully(): void
    {
        $uuid = 'test-uuid-123';
        $data = ['clinical_impression' => 'Updated impression'];
        
        $encounter = ClinicalEncounter::factory()->make([
            'encounter_uuid' => $uuid,
            'documentation_status' => 'in_progress',
        ]);

        $this->repositoryMock
            ->shouldReceive('findByUuid')
            ->with($uuid)
            ->once()
            ->andReturn($encounter);

        $this->repositoryMock
            ->shouldReceive('update')
            ->with($encounter, Mockery::subset(['updated_by_staff_id' => null]))
            ->once()
            ->andReturn($encounter);

        $result = $this->service->updateEncounter($uuid, $data);

        $this->assertInstanceOf(ClinicalEncounter::class, $result);
    }

    /**
     * Test deleteEncounter method soft deletes encounter.
     */
    public function test_delete_encounter_soft_deletes(): void
    {
        $uuid = 'test-uuid-123';
        
        $encounter = ClinicalEncounter::factory()->make([
            'encounter_uuid' => $uuid,
            'signed_at' => null, // Not signed
        ]);

        $this->repositoryMock
            ->shouldReceive('findByUuid')
            ->with($uuid)
            ->once()
            ->andReturn($encounter);

        $this->repositoryMock
            ->shouldReceive('delete')
            ->with($encounter)
            ->once()
            ->andReturn(true);

        $result = $this->service->deleteEncounter($uuid);

        $this->assertTrue($result);
    }

    /**
     * Test deleteEncounter throws exception for signed encounter.
     */
    public function test_delete_encounter_throws_exception_for_signed_encounter(): void
    {
        $this->expectException(\RuntimeException::class);

        $uuid = 'test-uuid-123';
        
        $encounter = ClinicalEncounter::factory()->make([
            'encounter_uuid' => $uuid,
            'signed_at' => now(), // Signed encounter
        ]);

        $this->repositoryMock
            ->shouldReceive('findByUuid')
            ->with($uuid)
            ->once()
            ->andReturn($encounter);

        $this->service->deleteEncounter($uuid);
    }

    /**
     * Test signEncounter method signs completed encounter.
     */
    public function test_sign_encounter_signs_completed_encounter(): void
    {
        $uuid = 'test-uuid-123';
        $signatureHash = hash('sha256', 'test');
        
        $encounter = ClinicalEncounter::factory()->make([
            'encounter_uuid' => $uuid,
            'documentation_status' => 'completed',
            'signed_at' => null,
            'subjective_assessment' => 'Present',
            'objective_findings' => 'Normal',
            'clinical_impression' => 'Stable',
            'treatment_plan' => 'Follow-up',
            'assessment_diagnosis_codes' => [['code' => 'I10', 'description' => 'Hypertension']],
        ]);

        $this->repositoryMock
            ->shouldReceive('findByUuid')
            ->with($uuid)
            ->once()
            ->andReturn($encounter);

        $this->repositoryMock
            ->shouldReceive('update')
            ->with($encounter, Mockery::subset([
                'documentation_status' => 'signed',
                'signed_at' => Mockery::type(\DateTime::class),
                'electronic_signature_hash' => $signatureHash,
            ]))
            ->once()
            ->andReturn($encounter);

        $result = $this->service->signEncounter($uuid, $signatureHash);

        $this->assertInstanceOf(ClinicalEncounter::class, $result);
    }

    /**
     * Test signEncounter throws exception for incomplete encounter.
     */
    public function test_sign_encounter_throws_exception_for_incomplete_encounter(): void
    {
        $this->expectException(\RuntimeException::class);

        $uuid = 'test-uuid-123';
        $signatureHash = hash('sha256', 'test');
        
        $encounter = ClinicalEncounter::factory()->make([
            'encounter_uuid' => $uuid,
            'documentation_status' => 'in_progress', // Not completed
            'signed_at' => null,
        ]);

        $this->repositoryMock
            ->shouldReceive('findByUuid')
            ->with($uuid)
            ->once()
            ->andReturn($encounter);

        $this->service->signEncounter($uuid, $signatureHash);
    }

    /**
     * Test createAmendment method creates amendment.
     */
    public function test_create_amendment_creates_amendment(): void
    {
        $originalUuid = 'original-uuid-123';
        $amendmentData = ['clinical_impression' => 'Updated impression'];
        $amendmentReason = 'Correction needed';
        
        $original = ClinicalEncounter::factory()->make([
            'encounter_uuid' => $originalUuid,
            'id' => 1,
            'signed_at' => now(),
        ]);

        $amendment = ClinicalEncounter::factory()->make([
            'amended_from_encounter_id' => 1,
            'amendment_reason' => $amendmentReason,
        ]);

        $this->repositoryMock
            ->shouldReceive('findByUuid')
            ->with($originalUuid)
            ->once()
            ->andReturn($original);

        $this->repositoryMock
            ->shouldReceive('create')
            ->with(Mockery::subset([
                'amended_from_encounter_id' => 1,
                'amendment_reason' => $amendmentReason,
                'documentation_status' => 'amended',
            ]))
            ->once()
            ->andReturn($amendment);

        $this->repositoryMock
            ->shouldReceive('update')
            ->with($original, ['documentation_status' => 'amended'])
            ->once()
            ->andReturn($original);

        $result = $this->service->createAmendment($originalUuid, $amendmentData, $amendmentReason);

        $this->assertInstanceOf(ClinicalEncounter::class, $result);
    }

    /**
     * Test createAmendment throws exception for unsigned encounter.
     */
    public function test_create_amendment_throws_exception_for_unsigned_encounter(): void
    {
        $this->expectException(\RuntimeException::class);

        $originalUuid = 'original-uuid-123';
        $amendmentData = [];
        $amendmentReason = 'Correction needed';
        
        $original = ClinicalEncounter::factory()->make([
            'encounter_uuid' => $originalUuid,
            'signed_at' => null, // Not signed
        ]);

        $this->repositoryMock
            ->shouldReceive('findByUuid')
            ->with($originalUuid)
            ->once()
            ->andReturn($original);

        $this->service->createAmendment($originalUuid, $amendmentData, $amendmentReason);
    }

    /**
     * Test validateEncounterCompleteness returns correct results.
     */
    public function test_validate_encounter_completeness(): void
    {
        $encounter = ClinicalEncounter::factory()->make([
            'subjective_assessment' => 'Present',
            'objective_findings' => 'Normal',
            'assessment_diagnosis_codes' => [['code' => 'I10']],
            'clinical_impression' => 'Stable',
            'treatment_plan' => 'Follow-up',
        ]);

        $result = $this->service->validateEncounterCompleteness($encounter);

        $this->assertArrayHasKey('is_complete', $result);
        $this->assertArrayHasKey('missing_fields', $result);
        $this->assertTrue($result['is_complete']);
        $this->assertEmpty($result['missing_fields']);
    }

    /**
     * Test validateEncounterCompleteness detects missing fields.
     */
    public function test_validate_encounter_completeness_detects_missing_fields(): void
    {
        $encounter = ClinicalEncounter::factory()->make([
            'subjective_assessment' => null, // Missing
            'objective_findings' => 'Normal',
            'assessment_diagnosis_codes' => [['code' => 'I10']],
            'clinical_impression' => 'Stable',
            'treatment_plan' => null, // Missing
        ]);

        $result = $this->service->validateEncounterCompleteness($encounter);

        $this->assertFalse($result['is_complete']);
        $this->assertContains('subjective_assessment', $result['missing_fields']);
        $this->assertContains('treatment_plan', $result['missing_fields']);
    }

    /**
     * Test getEncountersRequiringAttention returns collection.
     */
    public function test_get_encounters_requiring_attention(): void
    {
        $facilityId = 1;
        $collection = new Collection();

        $this->repositoryMock
            ->shouldReceive('getRequiringAttention')
            ->with($facilityId)
            ->once()
            ->andReturn($collection);

        $result = $this->service->getEncountersRequiringAttention($facilityId);

        $this->assertInstanceOf(Collection::class, $result);
    }

    /**
     * Test generateBillingInformation returns billing data.
     */
    public function test_generate_billing_information(): void
    {
        $encounter = ClinicalEncounter::factory()->make([
            'is_billable' => true,
            'billing_code' => '99213',
            'encounter_type' => 'initial_consultation',
            'severity_score' => 5,
            'assessment_diagnosis_codes' => [['code' => 'I10']],
            'plan_treatment_codes' => [['code' => '12002']],
        ]);

        $result = $this->service->generateBillingInformation($encounter);

        $this->assertArrayHasKey('billable', $result);
        $this->assertArrayHasKey('billing_code', $result);
        $this->assertArrayHasKey('estimated_amount', $result);
        $this->assertTrue($result['billable']);
    }

    /**
     * Test generateBillingInformation for non-billable encounter.
     */
    public function test_generate_billing_information_for_non_billable(): void
    {
        $encounter = ClinicalEncounter::factory()->make([
            'is_billable' => false,
        ]);

        $result = $this->service->generateBillingInformation($encounter);

        $this->assertFalse($result['billable']);
        $this->assertArrayHasKey('message', $result);
    }
}