<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\BillingCycle\BillingCycleService;
use App\Repositories\Contracts\BillingCycleRepositoryInterface;
use App\Models\BillingCycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class BillingCycleServiceTest extends TestCase
{
    use RefreshDatabase;

    private BillingCycleService $service;
    private $repositoryMock;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->repositoryMock = Mockery::mock(BillingCycleRepositoryInterface::class);
        $this->service = new BillingCycleService($this->repositoryMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_get_all_billing_cycles()
    {
        $mockPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
            [BillingCycle::factory()->make()],
            1,
            20,
            1
        );
        
        $this->repositoryMock->shouldReceive('getAllPaginated')
            ->once()
            ->with([], 20)
            ->andReturn($mockPaginator);
        
        $result = $this->service->getAllBillingCycles();
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Billing cycles retrieved successfully', $result['message']);
        $this->assertCount(1, $result['data']['billing_cycles']);
    }

    /** @test */
    public function it_can_get_billing_cycle_by_uuid()
    {
        $billingCycle = BillingCycle::factory()->create();
        
        $this->repositoryMock->shouldReceive('findByUuid')
            ->once()
            ->with($billingCycle->billing_cycle_uuid)
            ->andReturn($billingCycle);
        
        $result = $this->service->getBillingCycleByUuid($billingCycle->billing_cycle_uuid);
        
        $this->assertTrue($result['success']);
        $this->assertEquals($billingCycle->id, $result['data']['billing_cycle']->id);
    }

    /** @test */
    public function it_returns_error_when_billing_cycle_not_found_by_uuid()
    {
        $this->repositoryMock->shouldReceive('findByUuid')
            ->once()
            ->with('non-existent-uuid')
            ->andReturn(null);
        
        $result = $this->service->getBillingCycleByUuid('non-existent-uuid');
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Billing cycle not found', $result['message']);
    }

    /** @test */
    public function it_can_create_billing_cycle()
    {
        $data = [
            'facility_id' => 1,
            'visit_id' => 1,
            'patient_id' => 1,
            'cycle_type' => 'visit_based',
            'period_start' => now(),
            'billing_status' => 'draft',
            'total_amount_charged' => 1000,
            'total_adjustments' => 100,
        ];
        
        $billingCycle = BillingCycle::factory()->make($data);
        
        $this->repositoryMock->shouldReceive('create')
            ->once()
            ->with(Mockery::subset($data))
            ->andReturn($billingCycle);
        
        $result = $this->service->createBillingCycle($data);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Billing cycle created successfully', $result['message']);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_billing_cycle()
    {
        $data = [
            'cycle_type' => 'visit_based',
            // Missing required fields: facility_id, visit_id, patient_id
        ];
        
        $result = $this->service->createBillingCycle($data);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Validation failed', $result['message']);
        $this->assertStringContainsString('Facility, visit, and patient are required', $result['error']);
    }

    /** @test */
    public function it_can_update_billing_cycle()
    {
        $billingCycle = BillingCycle::factory()->create();
        $data = ['total_amount_charged' => 1500];
        
        $updatedBillingCycle = clone $billingCycle;
        $updatedBillingCycle->total_amount_charged = 1500;
        
        $this->repositoryMock->shouldReceive('findByUuid')
            ->once()
            ->with($billingCycle->billing_cycle_uuid)
            ->andReturn($billingCycle);
        
        $this->repositoryMock->shouldReceive('update')
            ->once()
            ->with($billingCycle, $data)
            ->andReturn($updatedBillingCycle);
        
        $result = $this->service->updateBillingCycle($billingCycle->billing_cycle_uuid, $data);
        
        $this->assertTrue($result['success']);
        $this->assertEquals(1500, $result['data']['billing_cycle']->total_amount_charged);
    }

    /** @test */
    public function it_can_update_billing_status()
    {
        $billingCycle = BillingCycle::factory()->create(['billing_status' => 'draft']);
        
        $updatedBillingCycle = clone $billingCycle;
        $updatedBillingCycle->billing_status = 'pending_review';
        
        $this->repositoryMock->shouldReceive('findByUuid')
            ->once()
            ->with($billingCycle->billing_cycle_uuid)
            ->andReturn($billingCycle);
        
        $this->repositoryMock->shouldReceive('updateStatus')
            ->once()
            ->with($billingCycle, 'pending_review', [])
            ->andReturn($updatedBillingCycle);
        
        $result = $this->service->updateBillingStatus($billingCycle->billing_cycle_uuid, 'pending_review');
        
        $this->assertTrue($result['success']);
        $this->assertEquals('pending_review', $result['data']['billing_cycle']->billing_status);
    }

    /** @test */
    public function it_can_record_payment()
    {
        $billingCycle = BillingCycle::factory()->create([
            'net_amount' => 1000,
            'insurance_payment_received' => 0,
            'patient_payment_received' => 0,
        ]);
        
        $paymentData = [
            'amount' => 500,
            'payment_type' => 'patient'
        ];
        
        $updatedBillingCycle = clone $billingCycle;
        $updatedBillingCycle->patient_payment_received = 500;
        
        $this->repositoryMock->shouldReceive('findByUuid')
            ->once()
            ->with($billingCycle->billing_cycle_uuid)
            ->andReturn($billingCycle);
        
        $this->repositoryMock->shouldReceive('recordPayment')
            ->once()
            ->with($billingCycle, $paymentData)
            ->andReturn($updatedBillingCycle);
        
        $result = $this->service->recordPayment($billingCycle->billing_cycle_uuid, $paymentData);
        
        $this->assertTrue($result['success']);
        $this->assertEquals(500, $result['data']['billing_cycle']->patient_payment_received);
    }

    /** @test */
    public function it_prevents_payment_exceeding_outstanding_amount()
    {
        $billingCycle = BillingCycle::factory()->create([
            'net_amount' => 1000,
            'insurance_payment_received' => 600,
            'patient_payment_received' => 300,
        ]);
        
        $paymentData = [
            'amount' => 200, // Would make total 1100, exceeding net amount of 1000
            'payment_type' => 'patient'
        ];
        
        $this->repositoryMock->shouldReceive('findByUuid')
            ->once()
            ->with($billingCycle->billing_cycle_uuid)
            ->andReturn($billingCycle);
        
        $result = $this->service->recordPayment($billingCycle->billing_cycle_uuid, $paymentData);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('exceeds outstanding amount', $result['error']);
    }
}