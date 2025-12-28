<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\DataResidencyRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

class DataResidencyRuleControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user with compliance officer role
     *
     * @var User
     */
    protected $complianceOfficer;

    /**
     * Test user with administrator role
     *
     * @var User
     */
    protected $administrator;

    /**
     * Test user with regular role
     *
     * @var User
     */
    protected $regularUser;

    /**
     * Set up the test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users with different roles
        $this->complianceOfficer = User::factory()->create();
        $this->complianceOfficer->assignRole('compliance_officer');
        $this->complianceOfficer->givePermissionTo([
            'view data residency rules',
            'create data residency rules',
            'update data residency rules',
            'validate data processing',
            'validate cross border transfers'
        ]);
        
        $this->administrator = User::factory()->create();
        $this->administrator->assignRole('administrator');
        $this->administrator->givePermissionTo([
            'view data residency rules',
            'create data residency rules',
            'update data residency rules',
            'delete data residency rules',
            'validate data processing',
            'validate cross border transfers'
        ]);
        
        $this->regularUser = User::factory()->create();
        $this->regularUser->givePermissionTo('view data residency rules');
        
        // Create some test data residency rules
        DataResidencyRule::factory()->count(15)->create([
            'created_by_staff_id' => $this->administrator->id,
        ]);
    }

    /** @test */
    public function it_can_list_data_residency_rules()
    {
        Sanctum::actingAs($this->regularUser);
        
        $response = $this->getJson('/api/data-residency-rules');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'region_code',
                        'region_name',
                        'data_category',
                        'status',
                        'created_at',
                        '_links'
                    ]
                ],
                'meta' => [
                    'pagination' => [
                        'total',
                        'per_page',
                        'current_page'
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_can_filter_rules_by_region_code()
    {
        Sanctum::actingAs($this->regularUser);
        
        $rule = DataResidencyRule::first();
        
        $response = $this->getJson("/api/data-residency-rules?region_code={$rule->region_code}");
        
        $response->assertStatus(200)
            ->assertJsonFragment(['region_code' => $rule->region_code])
            ->assertJsonPath('data.0.region_code', $rule->region_code);
    }

    /** @test */
    public function it_can_show_a_specific_data_residency_rule()
    {
        Sanctum::actingAs($this->regularUser);
        
        $rule = DataResidencyRule::first();
        
        $response = $this->getJson("/api/data-residency-rules/{$rule->id}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $rule->id,
                    'region_code' => $rule->region_code,
                    'region_name' => $rule->region_name,
                ]
            ]);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_rule()
    {
        Sanctum::actingAs($this->regularUser);
        
        $response = $this->getJson('/api/data-residency-rules/99999');
        
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error_code' => 'RULE_NOT_FOUND'
            ]);
    }

    /** @test */
    public function compliance_officer_can_create_data_residency_rule()
    {
        Sanctum::actingAs($this->complianceOfficer);
        
        $data = [
            'region_code' => 'EU-GDPR',
            'region_name' => 'European Union GDPR',
            'data_category' => 'clinical_records',
            'allowed_storage_regions' => ['EU'],
            'allowed_processing_regions' => ['EU'],
            'allowed_backup_regions' => ['EU'],
            'encryption_requirements' => [
                'algorithm' => 'AES-256',
                'key_length' => 256
            ],
            'encryption_at_rest_required' => true,
            'encryption_in_transit_required' => true,
            'encryption_in_use_required' => false,
            'cross_border_transfer_approval_required' => true,
            'minimum_retention_period_years' => 10,
            'maximum_retention_period_years' => 20,
            'retention_basis' => 'legal_requirement',
            'right_to_erasure_applicable' => true,
            'erasure_response_time_days' => 30,
            'breach_notification_hours' => 72,
            'applicable_regulations' => ['GDPR'],
            'status' => 'active',
            'effective_from' => now()->addDay()->toDateString(),
        ];
        
        $response = $this->postJson('/api/data-residency-rules', $data);
        
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Data residency rule created successfully'
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'region_code',
                    'region_name',
                    'data_category',
                    'status'
                ]
            ]);
        
        $this->assertDatabaseHas('data_residency_rules', [
            'region_code' => 'EU-GDPR',
            'region_name' => 'European Union GDPR'
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_rule()
    {
        Sanctum::actingAs($this->complianceOfficer);
        
        $response = $this->postJson('/api/data-residency-rules', []);
        
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR'
            ])
            ->assertJsonValidationErrors([
                'region_code',
                'region_name',
                'data_category',
                'allowed_storage_regions',
                'allowed_processing_regions',
                'allowed_backup_regions'
            ]);
    }

    /** @test */
    public function regular_user_cannot_create_data_residency_rule()
    {
        Sanctum::actingAs($this->regularUser);
        
        $data = [
            'region_code' => 'TEST-REGION',
            'region_name' => 'Test Region',
            'data_category' => 'clinical_records',
        ];
        
        $response = $this->postJson('/api/data-residency-rules', $data);
        
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'UNAUTHORIZED'
            ]);
    }

    /** @test */
    public function compliance_officer_can_update_data_residency_rule()
    {
        Sanctum::actingAs($this->complianceOfficer);
        
        $rule = DataResidencyRule::factory()->create([
            'status' => 'under_review',
            'created_by_staff_id' => $this->complianceOfficer->id,
        ]);
        
        $data = [
            'region_name' => 'Updated Region Name',
            'status' => 'active',
        ];
        
        $response = $this->putJson("/api/data-residency-rules/{$rule->id}", $data);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Data residency rule updated successfully'
            ]);
        
        $this->assertDatabaseHas('data_residency_rules', [
            'id' => $rule->id,
            'region_name' => 'Updated Region Name',
            'status' => 'active'
        ]);
    }

    /** @test */
    public function regular_user_cannot_update_data_residency_rule()
    {
        Sanctum::actingAs($this->regularUser);
        
        $rule = DataResidencyRule::first();
        
        $data = ['region_name' => 'Unauthorized Update'];
        
        $response = $this->putJson("/api/data-residency-rules/{$rule->id}", $data);
        
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'UNAUTHORIZED'
            ]);
    }

    /** @test */
    public function administrator_can_delete_data_residency_rule()
    {
        Sanctum::actingAs($this->administrator);
        
        $rule = DataResidencyRule::factory()->create([
            'status' => 'under_review',
            'effective_from' => now()->subYear(),
            'effective_to' => now()->subMonth(),
            'created_by_staff_id' => $this->administrator->id,
        ]);
        
        $response = $this->deleteJson("/api/data-residency-rules/{$rule->id}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Data residency rule deleted successfully'
            ]);
        
        $this->assertSoftDeleted('data_residency_rules', ['id' => $rule->id]);
    }

    /** @test */
    public function compliance_officer_cannot_delete_data_residency_rule()
    {
        Sanctum::actingAs($this->complianceOfficer);
        
        $rule = DataResidencyRule::first();
        
        $response = $this->deleteJson("/api/data-residency-rules/{$rule->id}");
        
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'UNAUTHORIZED'
            ]);
    }

    /** @test */
    public function it_can_validate_data_processing()
    {
        Sanctum::actingAs($this->complianceOfficer);
        
        DataResidencyRule::factory()->create([
            'data_category' => 'clinical_records',
            'allowed_processing_regions' => ['EU'],
            'allowed_storage_regions' => ['EU'],
            'prohibited_regions' => ['US'],
            'status' => 'active',
            'effective_from' => now()->subYear(),
            'effective_to' => now()->addYear(),
        ]);
        
        $data = [
            'data_category' => 'clinical_records',
            'processing_region' => 'EU',
            'storage_region' => 'EU',
        ];
        
        $response = $this->postJson('/api/data-residency-rules/validate-processing', $data);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Data processing is allowed'
            ]);
    }

    /** @test */
    public function it_can_validate_cross_border_transfer()
    {
        Sanctum::actingAs($this->complianceOfficer);
        
        DataResidencyRule::factory()->create([
            'data_category' => 'clinical_records',
            'prohibited_regions' => [],
            'cross_border_transfer_approval_required' => false,
            'status' => 'active',
        ]);
        
        $data = [
            'source_region' => 'EU',
            'target_region' => 'US',
            'data_category' => 'clinical_records',
        ];
        
        $response = $this->postJson('/api/data-residency-rules/validate-cross-border-transfer', $data);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);
    }

    /** @test */
    public function it_can_get_applicable_rules()
    {
        Sanctum::actingAs($this->regularUser);
        
        DataResidencyRule::factory()->create([
            'data_category' => 'clinical_records',
            'allowed_storage_regions' => ['EU'],
            'status' => 'active',
        ]);
        
        $response = $this->getJson('/api/data-residency-rules/applicable-rules?data_category=clinical_records&region_code=EU');
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'data_category',
                        'allowed_storage_regions'
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_can_get_rules_summary()
    {
        Sanctum::actingAs($this->regularUser);
        
        $response = $this->getJson('/api/data-residency-rules/summary');
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Rules summary retrieved successfully'
            ])
            ->assertJsonStructure([
                'data' => [
                    'total_rules',
                    'by_region',
                    'by_category',
                    'by_status'
                ]
            ]);
    }

    /** @test */
    public function it_handles_internal_server_errors_gracefully()
    {
        Sanctum::actingAs($this->regularUser);
        
        // Mock a server error by causing an exception
        $this->mock(\App\Services\DataResidencyRule\DataResidencyRuleService::class, function ($mock) {
            $mock->shouldReceive('getAllRules')->andThrow(new \Exception('Server error'));
        });
        
        $response = $this->getJson('/api/data-residency-rules');
        
        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'error_code' => 'SERVER_ERROR'
            ]);
    }
}