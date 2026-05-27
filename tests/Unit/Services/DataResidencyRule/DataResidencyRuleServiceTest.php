<?php

namespace Tests\Unit\Services\DataResidencyRule;

use Tests\TestCase;
use App\Services\DataResidencyRule\DataResidencyRuleService;
use App\Repositories\Contracts\DataResidencyRuleRepositoryInterface;
use App\Models\DataResidencyRule;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Mockery;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DataResidencyRuleServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Mock repository
     *
     * @var Mockery\MockInterface|DataResidencyRuleRepositoryInterface
     */
    protected $repositoryMock;

    /**
     * Service instance
     *
     * @var DataResidencyRuleService
     */
    protected $service;

    /**
     * Set up the test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->repositoryMock = Mockery::mock(DataResidencyRuleRepositoryInterface::class);
        $this->service = new DataResidencyRuleService($this->repositoryMock);
    }

    /**
     * Clean up the test environment
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_get_all_rules_successfully()
    {
        $rules = DataResidencyRule::factory()->count(5)->make();
        $paginator = new LengthAwarePaginator($rules, 5, 20, 1);
        
        $this->repositoryMock->shouldReceive('getAll')
            ->with([], [], 20)
            ->once()
            ->andReturn($paginator);
        
        $result = $this->service->getAllRules();
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Rules retrieved successfully', $result['message']);
        $this->assertCount(5, $result['data']['rules']);
        $this->assertArrayHasKey('pagination', $result['data']);
    }

    /** @test */
    public function it_handles_errors_when_getting_all_rules()
    {
        $this->repositoryMock->shouldReceive('getAll')
            ->with([], [], 20)
            ->once()
            ->andThrow(new \Exception('Database error'));
        
        $result = $this->service->getAllRules();
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Failed to retrieve data residency rules. Please try again later.', $result['message']);
        $this->assertEquals('RULES_FETCH_ERROR', $result['error_code']);
    }

    /** @test */
    public function it_can_get_rule_by_id_successfully()
    {
        $rule = DataResidencyRule::factory()->make(['id' => 1]);
        
        $this->repositoryMock->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($rule);
        
        $result = $this->service->getRuleById(1);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Rule retrieved successfully', $result['message']);
        $this->assertEquals($rule, $result['data']);
    }

    /** @test */
    public function it_returns_error_when_rule_not_found()
    {
        $this->repositoryMock->shouldReceive('findById')
            ->with(999)
            ->once()
            ->andReturn(null);
        
        $result = $this->service->getRuleById(999);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Data residency rule not found', $result['message']);
        $this->assertEquals('RULE_NOT_FOUND', $result['error_code']);
    }

    /** @test */
    public function it_can_create_rule_successfully()
    {
        $data = [
            'region_code' => 'EU-GDPR',
            'region_name' => 'European Union GDPR',
            'data_category' => 'clinical_records',
            'allowed_storage_regions' => ['EU'],
            'allowed_processing_regions' => ['EU'],
            'allowed_backup_regions' => ['EU'],
            'minimum_retention_period_years' => 10,
            'maximum_retention_period_years' => 20,
            'effective_from' => now()->addDay()->toDateString(),
        ];
        
        $rule = DataResidencyRule::factory()->make($data);
        
        $this->repositoryMock->shouldReceive('existsByRegionAndCategory')
            ->with('EU-GDPR', 'clinical_records', null)
            ->once()
            ->andReturn(false);
        
        $this->repositoryMock->shouldReceive('create')
            ->with(Mockery::subset($data))
            ->once()
            ->andReturn($rule);
        
        $result = $this->service->createRule($data);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Data residency rule created successfully', $result['message']);
        $this->assertEquals($rule, $result['data']);
    }

    /** @test */
    public function it_validates_unique_region_and_category_on_create()
    {
        $data = [
            'region_code' => 'EU-GDPR',
            'data_category' => 'clinical_records',
        ];
        
        $this->repositoryMock->shouldReceive('existsByRegionAndCategory')
            ->with('EU-GDPR', 'clinical_records', null)
            ->once()
            ->andReturn(true);
        
        $result = $this->service->createRule($data);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('A rule already exists for this region and data category', $result['message']);
        $this->assertEquals('RULE_ALREADY_EXISTS', $result['error_code']);
    }

    /** @test */
    public function it_validates_effective_dates_on_create()
    {
        $data = [
            'region_code' => 'EU-GDPR',
            'data_category' => 'clinical_records',
            'effective_from' => '2024-01-01',
            'effective_to' => '2023-01-01', // Invalid: before effective_from
        ];
        
        $this->repositoryMock->shouldReceive('existsByRegionAndCategory')
            ->once()
            ->andReturn(false);
        
        $result = $this->service->createRule($data);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Effective from date must be before effective to date', $result['message']);
        $this->assertEquals('INVALID_DATE_RANGE', $result['error_code']);
    }

    /** @test */
    public function it_can_update_rule_successfully()
    {
        $rule = DataResidencyRule::factory()->create(['id' => 1]);
        $data = ['region_name' => 'Updated Region Name'];
        
        $this->repositoryMock->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($rule);
        
        $this->repositoryMock->shouldReceive('existsByRegionAndCategory')
            ->with($rule->region_code, $rule->data_category, 1)
            ->once()
            ->andReturn(false);
        
        $this->repositoryMock->shouldReceive('update')
            ->with($rule, $data)
            ->once()
            ->andReturn($rule->fill($data));
        
        $result = $this->service->updateRule(1, $data);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Data residency rule updated successfully', $result['message']);
    }

    /** @test */
    public function it_can_delete_rule_successfully()
    {
        $rule = DataResidencyRule::factory()->create([
            'id' => 1,
            'status' => 'under_review',
            'effective_from' => now()->subYear()->toDateString(),
            'effective_to' => now()->subMonth()->toDateString(),
        ]);
        
        $this->repositoryMock->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($rule);
        
        $this->repositoryMock->shouldReceive('delete')
            ->with($rule)
            ->once()
            ->andReturn(true);
        
        $result = $this->service->deleteRule(1);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Data residency rule deleted successfully', $result['message']);
    }

    /** @test */
    public function it_prevents_deletion_of_active_effective_rules()
    {
        $rule = DataResidencyRule::factory()->create([
            'id' => 1,
            'status' => 'active',
            'effective_from' => now()->subYear()->toDateString(),
            'effective_to' => now()->addYear()->toDateString(),
        ]);
        
        $this->repositoryMock->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($rule);
        
        $result = $this->service->deleteRule(1);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Cannot delete an active and effective data residency rule', $result['message']);
        $this->assertEquals('RULE_ACTIVE', $result['error_code']);
    }

    /** @test */
    public function it_validates_data_processing_successfully()
    {
        $rules = DataResidencyRule::factory()->count(2)->make([
            'data_category' => 'clinical_records',
            'allowed_processing_regions' => ['EU'],
            'allowed_storage_regions' => ['EU'],
            'prohibited_regions' => [],
            'status' => 'active',
            'effective_from' => now()->subYear(),
            'effective_to' => now()->addYear(),
        ]);
        
        $this->repositoryMock->shouldReceive('findByDataCategory')
            ->with('clinical_records', true)
            ->once()
            ->andReturn(new Collection($rules));
        
        $result = $this->service->validateDataProcessing('clinical_records', 'EU', 'EU');
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Data processing is allowed', $result['message']);
        $this->assertCount(2, $result['data']['applicable_rules']);
    }

    /** @test */
    public function it_validates_cross_border_transfer_successfully()
    {
        $rules = DataResidencyRule::factory()->count(2)->make([
            'data_category' => 'clinical_records',
            'prohibited_regions' => [],
            'cross_border_transfer_approval_required' => false,
            'status' => 'active',
        ]);
        
        $this->repositoryMock->shouldReceive('findByDataCategory')
            ->with('clinical_records', true)
            ->once()
            ->andReturn(new Collection($rules));
        
        $result = $this->service->validateCrossBorderTransfer('EU', 'US', 'clinical_records');
        
        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['allowed']);
    }

    /** @test */
    public function it_gets_applicable_rules_successfully()
    {
        $rules = DataResidencyRule::factory()->count(3)->make([
            'data_category' => 'clinical_records',
            'allowed_storage_regions' => ['EU'],
            'status' => 'active',
        ]);
        
        $this->repositoryMock->shouldReceive('findByDataCategory')
            ->with('clinical_records', true)
            ->once()
            ->andReturn(new Collection($rules));
        
        $result = $this->service->getApplicableRules('clinical_records', 'EU');
        
        $this->assertTrue($result['success']);
        $this->assertCount(3, $result['data']['rules']);
        $this->assertEquals(3, $result['data']['count']);
    }

    /** @test */
    public function it_gets_rules_summary_successfully()
    {
        $rules = DataResidencyRule::factory()->count(5)->make([
            'status' => 'active',
        ]);
        
        $this->repositoryMock->shouldReceive('getAllActive')
            ->once()
            ->andReturn(new Collection($rules));
        
        $result = $this->service->getRulesSummary();
        
        $this->assertTrue($result['success']);
        $this->assertEquals(5, $result['data']['total_rules']);
        $this->assertArrayHasKey('by_region', $result['data']);
        $this->assertArrayHasKey('by_category', $result['data']);
        $this->assertArrayHasKey('by_status', $result['data']);
    }
}