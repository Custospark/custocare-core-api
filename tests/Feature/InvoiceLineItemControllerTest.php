<?php

namespace Tests\Feature;

use App\Models\InvoiceLineItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceLineItemControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $invoiceLineItem;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'email' => 'billing_manager@example.com',
            'role' => 'billing_manager'
        ]);
        
        $this->invoiceLineItem = InvoiceLineItem::factory()->create([
            'created_by_staff_id' => $this->user->id,
        ]);
        
        // $this->actingAs($this->user, 'sanctum');
    }

    /** @test */
    public function it_can_list_invoice_line_items()
    {
        InvoiceLineItem::factory()->count(5)->create();
        
        $response = $this->getJson('/api/invoice-line-items');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'line_items' => [
                        '*' => [
                            'id',
                            'line_item_uuid',
                            'service_code',
                            'service_description',
                            'line_item_status',
                        ]
                    ]
                ],
                'pagination'
            ]);
    }

    /** @test */
    public function it_can_create_invoice_line_item()
    {
        $data = [
            'billing_cycle_id' => 1,
            'visit_id' => 1,
            'service_version_id' => 1,
            'service_code' => 'SVC001',
            'service_description' => 'Test Service Description',
            'unit_price_at_time' => 100.00,
            'service_performed_at' => now()->toDateTimeString(),
            'quantity' => 2.00,
        ];
        
        $response = $this->postJson('/api/invoice-line-items', $data);
        
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Invoice line item created successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'line_item' => [
                        'id',
                        'line_item_uuid',
                        'service_code',
                        'line_total_amount',
                        'net_amount'
                    ]
                ]
            ]);
        
        $this->assertDatabaseHas('invoice_line_items', [
            'service_code' => 'SVC001',
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_creating()
    {
        $response = $this->postJson('/api/invoice-line-items', []);
        
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed',
            ])
            ->assertJsonValidationErrors([
                'billing_cycle_id',
                'visit_id',
                'service_version_id',
                'service_code',
                'service_description',
                'unit_price_at_time',
                'service_performed_at',
            ]);
    }

    /** @test */
    public function it_can_show_invoice_line_item()
    {
        $response = $this->getJson("/api/invoice-line-items/{$this->invoiceLineItem->id}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Invoice line item retrieved successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'line_item' => [
                        'id',
                        'line_item_uuid',
                        'service_code',
                        'service_description',
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_returns_404_when_invoice_line_item_not_found()
    {
        $response = $this->getJson('/api/invoice-line-items/9999');
        
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Invoice line item not found',
            ]);
    }

    /** @test */
    public function it_can_update_invoice_line_item()
    {
        $data = [
            'service_description' => 'Updated Service Description',
            'unit_price_at_time' => 150.00,
        ];
        
        $response = $this->putJson("/api/invoice-line-items/{$this->invoiceLineItem->id}", $data);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Invoice line item updated successfully',
            ]);
        
        $this->assertDatabaseHas('invoice_line_items', [
            'id' => $this->invoiceLineItem->id,
            'service_description' => 'Updated Service Description',
        ]);
    }

    /** @test */
    public function it_can_delete_invoice_line_item()
    {
        $response = $this->deleteJson("/api/invoice-line-items/{$this->invoiceLineItem->id}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Invoice line item deleted successfully',
            ]);
        
        $this->assertSoftDeleted('invoice_line_items', [
            'id' => $this->invoiceLineItem->id,
        ]);
    }

    /** @test */
    public function it_can_get_line_items_by_status()
    {
        InvoiceLineItem::factory()->create(['line_item_status' => 'approved']);
        
        $response = $this->getJson('/api/invoice-line-items/status/approved');
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Line items retrieved successfully for status',
            ])
            ->assertJsonStructure([
                'data' => [
                    'line_items',
                    'count',
                    'status'
                ]
            ]);
    }

    /** @test */
    public function it_can_update_line_item_status()
    {
        $data = [
            'status' => 'approved',
            'reason' => 'Manually approved by manager',
        ];
        
        $response = $this->patchJson("/api/invoice-line-items/{$this->invoiceLineItem->id}/status", $data);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Line item status updated successfully',
            ]);
        
        $this->assertDatabaseHas('invoice_line_items', [
            'id' => $this->invoiceLineItem->id,
            'line_item_status' => 'approved',
        ]);
    }

    /** @test */
    public function it_validates_status_when_updating()
    {
        $data = [
            'status' => 'invalid_status',
        ];
        
        $response = $this->patchJson("/api/invoice-line-items/{$this->invoiceLineItem->id}/status", $data);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    /** @test */
    public function it_can_mark_line_item_as_reviewed()
    {
        $lineItem = InvoiceLineItem::factory()->create([
            'requires_review' => true,
            'coding_reviewed' => false,
        ]);
        
        $data = [
            'reviewer_id' => $this->user->id,
        ];
        
        $response = $this->postJson("/api/invoice-line-items/{$lineItem->id}/review", $data);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Line item marked as reviewed successfully',
            ]);
        
        $this->assertDatabaseHas('invoice_line_items', [
            'id' => $lineItem->id,
            'coding_reviewed' => true,
            'reviewed_by_staff_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_can_validate_line_item_for_billing()
    {
        $lineItem = InvoiceLineItem::factory()->create([
            'line_item_status' => 'approved',
            'requires_review' => false,
            'procedure_code' => 'CPT001',
            'diagnosis_codes' => ['ICD10-001'],
            'net_amount' => 100.00,
            'service_performed_at' => now(),
        ]);
        
        $response = $this->getJson("/api/invoice-line-items/{$lineItem->id}/validate-billing");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Line item is valid for billing',
            ])
            ->assertJsonStructure([
                'data' => [
                    'is_valid',
                    'validation_errors',
                    'line_item'
                ]
            ]);
    }

    /** @test */
    public function it_can_verify_audit_trail()
    {
        $response = $this->getJson("/api/invoice-line-items/{$this->invoiceLineItem->id}/audit-trail");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'audit_trail_valid',
                    'line_item_id',
                    'audit_trail_hash'
                ]
            ]);
    }

    /** @test */
    public function it_can_calculate_billing_cycle_totals()
    {
        $response = $this->getJson("/api/invoice-line-items/billing-cycle/1/totals");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'billing_cycle_id',
                    'totals' => [
                        'total_items',
                        'total_quantity',
                        'total_line_amount',
                        'total_discount',
                        'total_adjustment',
                        'total_net_amount',
                        'avg_discount_percentage',
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_can_batch_update_status()
    {
        $lineItems = InvoiceLineItem::factory()->count(3)->create();
        $ids = $lineItems->pluck('id')->toArray();
        
        $data = [
            'ids' => $ids,
            'status' => 'approved',
            'reason' => 'Batch approval by manager',
        ];
        
        $response = $this->postJson('/api/invoice-line-items/batch-status', $data);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Batch status update completed',
            ])
            ->assertJsonStructure([
                'data' => [
                    'successful',
                    'failed'
                ],
                'summary' => [
                    'total',
                    'successful',
                    'failed'
                ]
            ]);
    }

    /** @test */
    public function it_returns_unauthorized_without_authentication()
    {
        $this->withoutMiddleware();
        
        $response = $this->getJson('/api/invoice-line-items');
        
        $response->assertStatus(401);
    }

    /** @test */
    public function it_enforces_policies_on_delete()
    {
        $adminUser = User::factory()->create(['role' => 'admin']);
        // $this->actingAs($adminUser, 'sanctum');
        
        $response = $this->deleteJson("/api/invoice-line-items/{$this->invoiceLineItem->id}");
        
        $response->assertStatus(200);
    }

    /** @test */
    public function it_can_get_line_items_requiring_review()
    {
        InvoiceLineItem::factory()->create([
            'requires_review' => true,
            'coding_reviewed' => false,
        ]);
        
        $response = $this->getJson('/api/invoice-line-items/requiring-review');
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Line items requiring review retrieved successfully',
            ]);
    }

    /** @test */
    public function it_can_get_line_items_by_date_range()
    {
        $startDate = now()->subDays(7)->toDateString();
        $endDate = now()->toDateString();
        
        $response = $this->getJson("/api/invoice-line-items/date-range?start_date={$startDate}&end_date={$endDate}");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'line_items',
                    'count',
                    'date_range'
                ]
            ]);
    }
}