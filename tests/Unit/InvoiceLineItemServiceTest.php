<?php

namespace Tests\Unit;

use App\Models\InvoiceLineItem;
use App\Repositories\Contracts\InvoiceLineItemRepositoryInterface;
use App\Services\InvoiceLineItem\InvoiceLineItemService;
use Mockery;
use Tests\TestCase;

class InvoiceLineItemServiceTest extends TestCase
{
    protected $repositoryMock;
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->repositoryMock = Mockery::mock(InvoiceLineItemRepositoryInterface::class);
        $this->service = new InvoiceLineItemService($this->repositoryMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_get_invoice_line_item_by_id()
    {
        $expectedItem = InvoiceLineItem::factory()->make(['id' => 1]);
        
        $this->repositoryMock->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($expectedItem);
        
        $result = $this->service->getInvoiceLineItemById(1);
        
        $this->assertTrue($result['success']);
        $this->assertEquals($expectedItem, $result['data']['line_item']);
    }

    /** @test */
    public function it_returns_error_when_invoice_line_item_not_found_by_id()
    {
        $this->repositoryMock->shouldReceive('findById')
            ->with(999)
            ->once()
            ->andReturn(null);
        
        $result = $this->service->getInvoiceLineItemById(999);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Invoice line item not found', $result['message']);
    }

    /** @test */
    public function it_can_create_invoice_line_item()
    {
        $data = [
            'billing_cycle_id' => 1,
            'visit_id' => 1,
            'service_version_id' => 1,
            'service_code' => 'SVC001',
            'service_description' => 'Test Service',
            'unit_price_at_time' => 100.00,
            'service_performed_at' => now()->toDateTimeString(),
        ];
        
        $expectedItem = InvoiceLineItem::factory()->make(array_merge($data, ['id' => 1]));
        
        $this->repositoryMock->shouldReceive('create')
            ->with(Mockery::on(function ($arg) {
                return isset($arg['line_item_uuid']) && isset($arg['net_amount']);
            }))
            ->once()
            ->andReturn($expectedItem);
        
        $result = $this->service->createInvoiceLineItem($data);
        
        $this->assertTrue($result['success']);
        $this->assertEquals($expectedItem, $result['data']['line_item']);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_invoice_line_item()
    {
        $data = [
            'service_code' => 'SVC001',
            // Missing required fields
        ];
        
        $result = $this->service->createInvoiceLineItem($data);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Validation failed', $result['message']);
        $this->assertArrayHasKey('validation_errors', $result);
    }

    /** @test */
    public function it_can_update_invoice_line_item()
    {
        $existingItem = InvoiceLineItem::factory()->make(['id' => 1, 'line_item_status' => 'pending']);
        
        $data = [
            'service_description' => 'Updated Description',
            'unit_price_at_time' => 150.00,
        ];
        
        $this->repositoryMock->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($existingItem);
        
        $this->repositoryMock->shouldReceive('update')
            ->with(1, Mockery::on(function ($arg) {
                return isset($arg['net_amount']);
            }))
            ->once()
            ->andReturn(true);
        
        $this->repositoryMock->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($existingItem);
        
        $result = $this->service->updateInvoiceLineItem(1, $data);
        
        $this->assertTrue($result['success']);
    }

    /** @test */
    public function it_can_delete_invoice_line_item()
    {
        $existingItem = InvoiceLineItem::factory()->make([
            'id' => 1,
            'line_item_status' => 'pending'
        ]);
        
        $this->repositoryMock->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($existingItem);
        
        $this->repositoryMock->shouldReceive('delete')
            ->with(1)
            ->once()
            ->andReturn(true);
        
        $result = $this->service->deleteInvoiceLineItem(1);
        
        $this->assertTrue($result['success']);
    }

    /** @test */
    public function it_cannot_delete_billed_or_paid_invoice_line_item()
    {
        $existingItem = InvoiceLineItem::factory()->make([
            'id' => 1,
            'line_item_status' => 'billed'
        ]);
        
        $this->repositoryMock->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($existingItem);
        
        $result = $this->service->deleteInvoiceLineItem(1);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Cannot delete', $result['message']);
    }

    /** @test */
    public function it_can_get_line_items_by_billing_cycle()
    {
        $items = InvoiceLineItem::factory()->count(3)->make();
        
        $this->repositoryMock->shouldReceive('findByBillingCycle')
            ->with(1)
            ->once()
            ->andReturn($items);
        
        $result = $this->service->getLineItemsByBillingCycle(1);
        
        $this->assertTrue($result['success']);
        $this->assertCount(3, $result['data']['line_items']);
    }

    /** @test */
    public function it_can_update_line_item_status()
    {
        $existingItem = InvoiceLineItem::factory()->make([
            'id' => 1,
            'line_item_status' => 'pending'
        ]);
        
        $this->repositoryMock->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($existingItem);
        
        $this->repositoryMock->shouldReceive('updateStatus')
            ->with(1, 'approved', null)
            ->once()
            ->andReturn(true);
        
        $this->repositoryMock->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($existingItem);
        
        $result = $this->service->updateLineItemStatus(1, 'approved');
        
        $this->assertTrue($result['success']);
    }

    /** @test */
    public function it_validates_status_transitions()
    {
        $existingItem = InvoiceLineItem::factory()->make([
            'id' => 1,
            'line_item_status' => 'billed'
        ]);
        
        $this->repositoryMock->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($existingItem);
        
        $result = $this->service->updateLineItemStatus(1, 'pending');
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid status transition', $result['message']);
    }

    /** @test */
    public function it_can_calculate_billing_cycle_totals()
    {
        $totals = [
            'total_items' => 5,
            'total_quantity' => 15.00,
            'total_line_amount' => 1500.00,
            'total_discount' => 150.00,
            'total_adjustment' => 50.00,
            'total_net_amount' => 1300.00,
            'avg_discount_percentage' => 10.00,
        ];
        
        $this->repositoryMock->shouldReceive('calculateTotalsForBillingCycle')
            ->with(1)
            ->once()
            ->andReturn($totals);
        
        $result = $this->service->calculateBillingCycleTotals(1);
        
        $this->assertTrue($result['success']);
        $this->assertEquals($totals, $result['data']['totals']);
    }

    /** @test */
    public function it_can_verify_audit_trail()
    {
        $existingItem = InvoiceLineItem::factory()->make(['id' => 1]);
        
        $this->repositoryMock->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($existingItem);
        
        $this->repositoryMock->shouldReceive('verifyAuditTrail')
            ->with(1)
            ->once()
            ->andReturn(true);
        
        $result = $this->service->verifyAuditTrail(1);
        
        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['audit_trail_valid']);
    }

    /** @test */
    public function it_can_validate_line_item_for_billing()
    {
        $existingItem = InvoiceLineItem::factory()->make([
            'id' => 1,
            'line_item_status' => 'approved',
            'requires_review' => false,
            'procedure_code' => 'CPT001',
            'diagnosis_codes' => ['ICD10-001'],
            'net_amount' => 100.00,
            'service_performed_at' => now(),
        ]);
        
        $this->repositoryMock->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($existingItem);
        
        $result = $this->service->validateLineItemForBilling(1);
        
        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['is_valid']);
        $this->assertEmpty($result['data']['validation_errors']);
    }

    /** @test */
    public function it_validates_line_item_for_billing_with_errors()
    {
        $existingItem = InvoiceLineItem::factory()->make([
            'id' => 1,
            'line_item_status' => 'pending',
            'requires_review' => true,
            'coding_reviewed' => false,
            'procedure_code' => null,
            'diagnosis_codes' => null,
            'net_amount' => 0,
            'service_performed_at' => null,
        ]);
        
        $this->repositoryMock->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($existingItem);
        
        $result = $this->service->validateLineItemForBilling(1);
        
        $this->assertTrue($result['success']);
        $this->assertFalse($result['data']['is_valid']);
        $this->assertNotEmpty($result['data']['validation_errors']);
    }

    /** @test */
    public function it_can_batch_update_status()
    {
        $ids = [1, 2, 3];
        
        $this->repositoryMock->shouldReceive('findById')
            ->andReturn(InvoiceLineItem::factory()->make(['line_item_status' => 'pending']));
        
        $this->repositoryMock->shouldReceive('updateStatus')
            ->andReturn(true);
        
        $result = $this->service->batchUpdateStatus($ids, 'approved');
        
        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['summary']['total']);
    }
}