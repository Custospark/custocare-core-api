<?php

namespace Tests\Unit\Services\Prescription;

use Tests\TestCase;
use App\Services\Prescription\PrescriptionService;
use App\Repositories\Contracts\PrescriptionRepositoryInterface;
use App\Models\Prescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class PrescriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $prescriptionService;
    protected $prescriptionRepositoryMock;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->prescriptionRepositoryMock = Mockery::mock(PrescriptionRepositoryInterface::class);
        $this->prescriptionService = new PrescriptionService($this->prescriptionRepositoryMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_get_prescription_by_uuid()
    {
        $uuid = 'test-uuid-123';
        $prescription = Prescription::factory()->make(['prescription_uuid' => $uuid]);
        
        $this->prescriptionRepositoryMock
            ->shouldReceive('findByUuid')
            ->once()
            ->with($uuid)
            ->andReturn($prescription);
        
        $result = $this->prescriptionService->getPrescriptionByUuid($uuid);
        
        $this->assertInstanceOf(Prescription::class, $result);
        $this->assertEquals($uuid, $result->prescription_uuid);
    }

    /** @test */
    public function it_throws_exception_when_prescription_not_found()
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        
        $uuid = 'non-existent-uuid';
        
        $this->prescriptionRepositoryMock
            ->shouldReceive('findByUuid')
            ->once()
            ->with($uuid)
            ->andReturn(null);
        
        $this->prescriptionService->getPrescriptionByUuid($uuid);
    }

    /** @test */
    public function it_can_create_prescription_with_valid_data()
    {
        $prescriptionData = [
            'patient_id' => 1,
            'prescribing_provider_staff_id' => 2,
            'inventory_item_id' => 3,
            'medication_name' => 'Test Medication',
            'dosage_strength' => '500mg',
            'dosage_form' => 'Tablet',
            'route' => 'Oral',
            'sig_instructions' => 'Take once daily',
            'quantity_prescribed' => 30,
            'quantity_unit' => 'tablets',
            'valid_from' => now()->format('Y-m-d'),
            'valid_to' => now()->addDays(30)->format('Y-m-d'),
        ];
        
        $expectedPrescription = Prescription::factory()->make($prescriptionData);
        
        $this->prescriptionRepositoryMock
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) use ($prescriptionData) {
                return array_intersect_key($data, $prescriptionData) == $prescriptionData;
            }))
            ->andReturn($expectedPrescription);
        
        $result = $this->prescriptionService->createPrescription($prescriptionData);
        
        $this->assertInstanceOf(Prescription::class, $result);
    }

    /** @test */
    public function it_throws_exception_when_creating_prescription_with_invalid_data()
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $invalidData = [
            // Missing required fields
            'medication_name' => 'Test Medication',
        ];
        
        $this->prescriptionService->createPrescription($invalidData);
    }

    /** @test */
    public function it_can_update_prescription()
    {
        $uuid = 'test-uuid-123';
        $prescription = Prescription::factory()->create(['prescription_uuid' => $uuid]);
        $updateData = ['medication_name' => 'Updated Medication Name'];
        
        $this->prescriptionRepositoryMock
            ->shouldReceive('findByUuid')
            ->once()
            ->with($uuid)
            ->andReturn($prescription);
        
        $this->prescriptionRepositoryMock
            ->shouldReceive('update')
            ->once()
            ->with($prescription, $updateData)
            ->andReturn($prescription);
        
        $result = $this->prescriptionService->updatePrescription($uuid, $updateData);
        
        $this->assertInstanceOf(Prescription::class, $result);
    }

    /** @test */
    public function it_can_delete_prescription()
    {
        $uuid = 'test-uuid-123';
        $prescription = Prescription::factory()->create(['prescription_uuid' => $uuid]);
        
        $this->prescriptionRepositoryMock
            ->shouldReceive('findByUuid')
            ->once()
            ->with($uuid)
            ->andReturn($prescription);
        
        $this->prescriptionRepositoryMock
            ->shouldReceive('delete')
            ->once()
            ->with($prescription)
            ->andReturn(true);
        
        $result = $this->prescriptionService->deletePrescription($uuid);
        
        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_process_refill()
    {
        $uuid = 'test-uuid-123';
        $prescription = Prescription::factory()->create([
            'prescription_uuid' => $uuid,
            'refills_remaining' => 2,
            'status' => 'active',
        ]);
        
        $refillData = ['pharmacy_ncpdp_id' => '1234567890'];
        
        $this->prescriptionRepositoryMock
            ->shouldReceive('findByUuid')
            ->once()
            ->with($uuid)
            ->andReturn($prescription);
        
        $this->prescriptionRepositoryMock
            ->shouldReceive('processRefill')
            ->once()
            ->with($prescription, Mockery::on(function ($data) use ($refillData) {
                return array_intersect_key($data, $refillData) == $refillData;
            }))
            ->andReturn($prescription);
        
        $result = $this->prescriptionService->processRefill($uuid, $refillData);
        
        $this->assertInstanceOf(Prescription::class, $result);
    }

    /** @test */
    public function it_throws_exception_when_refill_not_allowed()
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $uuid = 'test-uuid-123';
        $prescription = Prescription::factory()->create([
            'prescription_uuid' => $uuid,
            'refills_remaining' => 0, // No refills remaining
            'status' => 'active',
        ]);
        
        $refillData = ['pharmacy_ncpdp_id' => '1234567890'];
        
        $this->prescriptionRepositoryMock
            ->shouldReceive('findByUuid')
            ->once()
            ->with($uuid)
            ->andReturn($prescription);
        
        $this->prescriptionRepositoryMock
            ->shouldReceive('processRefill')
            ->once()
            ->andThrow(new \InvalidArgumentException('No refills remaining for this prescription'));
        
        $this->prescriptionService->processRefill($uuid, $refillData);
    }

    /** @test */
    public function it_can_update_dispense_status()
    {
        $uuid = 'test-uuid-123';
        $prescription = Prescription::factory()->create(['prescription_uuid' => $uuid]);
        $newStatus = 'transmitted';
        
        $this->prescriptionRepositoryMock
            ->shouldReceive('findByUuid')
            ->once()
            ->with($uuid)
            ->andReturn($prescription);
        
        $this->prescriptionRepositoryMock
            ->shouldReceive('updateDispenseStatus')
            ->once()
            ->with($prescription, $newStatus, [])
            ->andReturn($prescription);
        
        $result = $this->prescriptionService->updateDispenseStatus($uuid, $newStatus);
        
        $this->assertInstanceOf(Prescription::class, $result);
    }

    /** @test */
    public function it_can_check_refill_eligibility()
    {
        $uuid = 'test-uuid-123';
        $prescription = Prescription::factory()->create([
            'prescription_uuid' => $uuid,
            'refills_remaining' => 2,
            'status' => 'active',
            'valid_to' => now()->addDays(30),
        ]);
        
        $this->prescriptionRepositoryMock
            ->shouldReceive('findByUuid')
            ->once()
            ->with($uuid)
            ->andReturn($prescription);
        
        $result = $this->prescriptionService->checkRefillEligibility($uuid);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('is_eligible', $result);
        $this->assertTrue($result['is_eligible']);
    }

    /** @test */
    public function it_can_get_prescription_statistics()
    {
        $facilityId = 1;
        $dateRange = ['start_date' => now()->subDays(30), 'end_date' => now()];
        $expectedStats = [
            'total_prescriptions' => 100,
            'active_prescriptions' => 75,
            'electronic_prescriptions' => 80,
        ];
        
        $this->prescriptionRepositoryMock
            ->shouldReceive('getStatistics')
            ->once()
            ->with($facilityId, $dateRange)
            ->andReturn($expectedStats);
        
        $result = $this->prescriptionService->getPrescriptionStatistics($facilityId, $dateRange);
        
        $this->assertEquals($expectedStats, $result);
    }

    /** @test */
    public function it_handles_exceptions_gracefully()
    {
        $this->expectException(\RuntimeException::class);
        
        $uuid = 'test-uuid-123';
        
        $this->prescriptionRepositoryMock
            ->shouldReceive('findByUuid')
            ->once()
            ->with($uuid)
            ->andThrow(new \Exception('Database connection failed'));
        
        $this->prescriptionService->getPrescriptionByUuid($uuid);
    }
}