<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\BillingCycle;
use App\Models\User;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class BillingCycleControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Facility $facility;
    private Patient $patient;
    private Visit $visit;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->facility = Facility::factory()->create();
        $this->patient = Patient::factory()->create();
        $this->visit = Visit::factory()->create([
            'facility_id' => $this->facility->id,
            'patient_id' => $this->patient->id,
        ]);
        
        $this->user = User::factory()->create([
            'facility_id' => $this->facility->id,
        ]);
        
        // Assign permissions to user
        $this->user->givePermissionTo([
            'billing_cycles.view',
            'billing_cycles.create',
            'billing_cycles.update',
            'billing_cycles.delete',
            'billing_cycles.record_payment',
        ]);
        
        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function it_can_list_billing_cycles()
    {
        BillingCycle::factory()->count(3)->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
        ]);
        
        $response = $this->getJson('/api/v1/billing-cycles');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'billing_cycles' => [
                        '*' => [
                            'id',
                            'billing_cycle_uuid',
                            'facility_id',
                            'visit_id',
                            'patient_id',
                            'cycle_type',
                            'total_amount_charged',
                            'net_amount',
                            'billing_status',
                        ]
                    ],
                    'pagination' => [
                        'total',
                        'per_page',
                        'current_page',
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_can_create_billing_cycle()
    {
        $data = [
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'cycle_type' => 'visit_based',
            'period_start' => now()->toDateTimeString(),
            'total_amount_charged' => 1500.50,
            'total_adjustments' => 100.25,
            'billing_status' => 'draft',
        ];
        
        $response = $this->postJson('/api/v1/billing-cycles', $data);
        
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Billing cycle created successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'billing_cycle' => [
                        'billing_cycle_uuid',
                        'facility_id',
                        'visit_id',
                        'patient_id',
                        'cycle_type',
                        'total_amount_charged',
                        'total_adjustments',
                        'net_amount',
                        'billing_status',
                    ]
                ]
            ]);
        
        $this->assertDatabaseHas('billing_cycles', [
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'cycle_type' => 'visit_based',
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_billing_cycle()
    {
        $response = $this->postJson('/api/v1/billing-cycles', [
            'cycle_type' => 'visit_based',
            // Missing required fields
        ]);
        
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed',
            ])
            ->assertJsonStructure([
                'errors' => [
                    'facility_id',
                    'visit_id',
                    'patient_id',
                ]
            ]);
    }

    /** @test */
    public function it_can_show_billing_cycle()
    {
        $billingCycle = BillingCycle::factory()->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'created_by_staff_id' => $this->user->id,
        ]);
        
        $response = $this->getJson("/api/v1/billing-cycles/{$billingCycle->billing_cycle_uuid}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Billing cycle retrieved successfully',
                'data' => [
                    'billing_cycle' => [
                        'billing_cycle_uuid' => $billingCycle->billing_cycle_uuid,
                        'facility_id' => $billingCycle->facility_id,
                        'visit_id' => $billingCycle->visit_id,
                        'patient_id' => $billingCycle->patient_id,
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_returns_404_when_billing_cycle_not_found()
    {
        $response = $this->getJson('/api/v1/billing-cycles/non-existent-uuid');
        
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Billing cycle not found',
            ]);
    }

    /** @test */
    public function it_can_update_billing_cycle()
    {
        $billingCycle = BillingCycle::factory()->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'created_by_staff_id' => $this->user->id,
            'total_amount_charged' => 1000,
        ]);
        
        $data = [
            'total_amount_charged' => 1500,
            'total_adjustments' => 200,
        ];
        
        $response = $this->putJson("/api/v1/billing-cycles/{$billingCycle->billing_cycle_uuid}", $data);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Billing cycle updated successfully',
                'data' => [
                    'billing_cycle' => [
                        'total_amount_charged' => 1500,
                        'total_adjustments' => 200,
                        'net_amount' => 1300, // 1500 - 200
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_can_update_billing_status()
    {
        $billingCycle = BillingCycle::factory()->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'created_by_staff_id' => $this->user->id,
            'billing_status' => 'draft',
        ]);
        
        $this->user->givePermissionTo('billing_cycles.update');
        
        $response = $this->patchJson("/api/v1/billing-cycles/{$billingCycle->billing_cycle_uuid}/status", [
            'status' => 'pending_review',
        ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Billing status updated successfully',
                'data' => [
                    'billing_cycle' => [
                        'billing_status' => 'pending_review',
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_can_record_payment()
    {
        $billingCycle = BillingCycle::factory()->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'created_by_staff_id' => $this->user->id,
            'billing_status' => 'submitted_to_insurance',
            'net_amount' => 1000,
            'patient_payment_received' => 0,
        ]);
        
        $response = $this->postJson("/api/v1/billing-cycles/{$billingCycle->billing_cycle_uuid}/payments", [
            'amount' => 500,
            'payment_type' => 'patient',
            'payment_method' => 'credit_card',
        ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payment recorded successfully',
            ]);
        
        $this->assertDatabaseHas('billing_cycles', [
            'id' => $billingCycle->id,
            'patient_payment_received' => 500,
            'billing_status' => 'partially_paid',
        ]);
    }

    /** @test */
    public function it_can_delete_billing_cycle()
    {
        $billingCycle = BillingCycle::factory()->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'created_by_staff_id' => $this->user->id,
            'billing_status' => 'draft',
        ]);
        
        $response = $this->deleteJson("/api/v1/billing-cycles/{$billingCycle->billing_cycle_uuid}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Billing cycle deleted successfully',
            ]);
        
        $this->assertSoftDeleted('billing_cycles', ['id' => $billingCycle->id]);
    }

    /** @test */
    public function it_cannot_delete_billing_cycle_with_restricted_status()
    {
        $billingCycle = BillingCycle::factory()->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'created_by_staff_id' => $this->user->id,
            'billing_status' => 'paid_in_full', // Cannot delete
        ]);
        
        $response = $this->deleteJson("/api/v1/billing-cycles/{$billingCycle->billing_cycle_uuid}");
        
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot delete billing cycle',
            ]);
    }

    /** @test */
    public function it_can_get_billing_cycles_by_facility()
    {
        BillingCycle::factory()->count(2)->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
        ]);
        
        // Create billing cycle for different facility
        $otherFacility = Facility::factory()->create();
        BillingCycle::factory()->create(['facility_id' => $otherFacility->id]);
        
        $response = $this->getJson("/api/v1/billing-cycles/facility/{$this->facility->id}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(2, 'data.billing_cycles');
    }

    /** @test */
    public function it_can_get_overdue_billing_cycles()
    {
        // Create overdue billing cycle
        BillingCycle::factory()->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'payment_due_date' => now()->subDays(10),
            'billing_status' => 'submitted_to_insurance',
            'net_amount' => 1000,
            'patient_payment_received' => 0,
        ]);
        
        // Create non-overdue billing cycle
        BillingCycle::factory()->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'payment_due_date' => now()->addDays(10),
            'billing_status' => 'submitted_to_insurance',
        ]);
        
        $response = $this->getJson('/api/v1/billing-cycles/overdue');
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Overdue billing cycles retrieved successfully',
            ])
            ->assertJsonCount(1, 'data.billing_cycles');
    }

    /** @test */
    public function it_can_get_financial_summary()
    {
        BillingCycle::factory()->count(3)->create([
            'facility_id' => $this->facility->id,
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'total_amount_charged' => 1000,
            'net_amount' => 800,
            'insurance_payment_received' => 400,
            'patient_payment_received' => 200,
        ]);
        
        $response = $this->getJson("/api/v1/billing-cycles/facility/{$this->facility->id}/financial-summary");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Financial summary retrieved successfully',
                'data' => [
                    'total_cycles' => 3,
                    'total_amount_charged' => 3000,
                    'net_amount' => 2400,
                    'total_insurance_payments' => 1200,
                    'total_patient_payments' => 600,
                    'total_outstanding' => 600, // 2400 - (1200 + 600)
                ]
            ]);
    }
}