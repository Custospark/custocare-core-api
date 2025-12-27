<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\AiAssessment\AiAssessmentService;
use App\Repositories\Contracts\AiAssessmentRepositoryInterface;
use App\Models\AiAssessment;
use App\Enums\ModelType;
use App\Enums\HumanReviewStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class AiAssessmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private AiAssessmentService $service;
    private $repositoryMock;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->repositoryMock = Mockery::mock(AiAssessmentRepositoryInterface::class);
        $this->service = new AiAssessmentService($this->repositoryMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_get_assessment_by_uuid_successfully()
    {
        $uuid = 'test-uuid-123';
        $assessment = AiAssessment::factory()->create(['assessment_uuid' => $uuid]);
        
        $this->repositoryMock->shouldReceive('findByUuid')
            ->with($uuid)
            ->once()
            ->andReturn($assessment);
        
        $result = $this->service->getAssessmentByUuid($uuid);
        
        $this->assertTrue($result['success']);
        $this->assertEquals($assessment, $result['data']);
        $this->assertEquals('AI assessment retrieved successfully.', $result['message']);
    }

    /** @test */
    public function it_returns_error_when_assessment_not_found_by_uuid()
    {
        $uuid = 'non-existent-uuid';
        
        $this->repositoryMock->shouldReceive('findByUuid')
            ->with($uuid)
            ->once()
            ->andReturn(null);
        
        $result = $this->service->getAssessmentByUuid($uuid);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('ASSESSMENT_NOT_FOUND', $result['error_code']);
        $this->assertEquals('AI assessment not found.', $result['message']);
    }

    /** @test */
    public function it_can_create_assessment_with_valid_data()
    {
        $data = [
            'facility_id' => 1,
            'clinical_encounter_id' => 1,
            'visit_id' => 1,
            'patient_id' => 1,
            'ai_model_name' => 'Test Model',
            'ai_model_version' => '1.0.0',
            'model_type' => ModelType::DIAGNOSTIC_ASSISTANT->value,
            'input_features' => ['age' => 30, 'symptoms' => ['fever', 'cough']],
            'output_predictions' => ['diagnosis' => 'flu'],
            'output_confidence_scores' => ['diagnosis' => 0.85],
            'recommendations' => ['rest', 'hydration'],
            'assessed_at' => now()->toDateTimeString(),
        ];
        
        $assessment = AiAssessment::factory()->make($data);
        
        $this->repositoryMock->shouldReceive('create')
            ->with(Mockery::on(function ($arg) use ($data) {
                return isset($arg['assessment_uuid']) && 
                       isset($arg['input_features_hash']) &&
                       $arg['ai_model_name'] === $data['ai_model_name'];
            }))
            ->once()
            ->andReturn($assessment);
        
        $result = $this->service->createAssessment($data);
        
        $this->assertTrue($result['success']);
        $this->assertEquals($assessment, $result['data']);
        $this->assertEquals('AI assessment created successfully.', $result['message']);
    }

    /** @test */
    public function it_validates_input_features_during_creation()
    {
        $data = [
            'facility_id' => 1,
            'clinical_encounter_id' => 1,
            'patient_id' => 1,
            'ai_model_name' => 'Test Model',
            'model_type' => 'invalid_type',
            'input_features' => [],
            'output_predictions' => [],
            'output_confidence_scores' => [],
            'recommendations' => [],
            'assessed_at' => now(),
        ];
        
        $result = $this->service->createAssessment($data);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('INVALID_INPUT_FEATURES', $result['error_code']);
    }

    /** @test */
    public function it_can_update_assessment_successfully()
    {
        $uuid = 'test-uuid-123';
        $assessment = AiAssessment::factory()->create(['assessment_uuid' => $uuid]);
        $updateData = ['review_notes' => 'Updated notes'];
        
        $this->repositoryMock->shouldReceive('findByUuid')
            ->with($uuid)
            ->once()
            ->andReturn($assessment);
        
        $this->repositoryMock->shouldReceive('update')
            ->with($assessment, $updateData)
            ->once()
            ->andReturn(true);
        
        $result = $this->service->updateAssessment($uuid, $updateData);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('AI assessment updated successfully.', $result['message']);
    }

    /** @test */
    public function it_can_delete_assessment_successfully()
    {
        $uuid = 'test-uuid-123';
        $assessment = AiAssessment::factory()->create([
            'assessment_uuid' => $uuid,
            'clinical_outcome_recorded' => false
        ]);
        
        $this->repositoryMock->shouldReceive('findByUuid')
            ->with($uuid)
            ->once()
            ->andReturn($assessment);
        
        $this->repositoryMock->shouldReceive('delete')
            ->with($assessment)
            ->once()
            ->andReturn(true);
        
        $result = $this->service->deleteAssessment($uuid);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('AI assessment deleted successfully.', $result['message']);
    }

    /** @test */
    public function it_cannot_delete_assessment_with_recorded_outcome()
    {
        $uuid = 'test-uuid-123';
        $assessment = AiAssessment::factory()->create([
            'assessment_uuid' => $uuid,
            'clinical_outcome_recorded' => true
        ]);
        
        $this->repositoryMock->shouldReceive('findByUuid')
            ->with($uuid)
            ->once()
            ->andReturn($assessment);
        
        $result = $this->service->deleteAssessment($uuid);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('ASSESSMENT_LOCKED', $result['error_code']);
    }

    /** @test */
    public function it_can_review_assessment_successfully()
    {
        $uuid = 'test-uuid-123';
        $assessment = AiAssessment::factory()->create([
            'assessment_uuid' => $uuid,
            'human_review_status' => HumanReviewStatus::PENDING_REVIEW
        ]);
        
        $reviewData = [
            'status' => HumanReviewStatus::ACCEPTED->value,
            'review_notes' => 'Looks good'
        ];
        
        $this->repositoryMock->shouldReceive('findByUuid')
            ->with($uuid)
            ->once()
            ->andReturn($assessment);
        
        $this->repositoryMock->shouldReceive('updateReviewStatus')
            ->with($assessment, $reviewData['status'], $reviewData)
            ->once()
            ->andReturn(true);
        
        $result = $this->service->reviewAssessment($uuid, $reviewData);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('AI assessment reviewed successfully.', $result['message']);
    }

    /** @test */
    public function it_validates_input_features_correctly()
    {
        $inputFeatures = [
            'patient_age' => 30,
            'patient_gender' => 'male',
            'timestamp' => now()->toDateTimeString(),
            'symptoms' => ['fever', 'cough'],
            'duration' => 3,
            'severity' => 'moderate'
        ];
        
        $modelType = ModelType::DIAGNOSTIC_ASSISTANT->value;
        
        $result = $this->service->validateInputFeatures($inputFeatures, $modelType);
        
        $this->assertTrue($result['valid']);
        $this->assertEquals('Input features are valid.', $result['message']);
    }

    /** @test */
    public function it_generates_explanation_from_predictions()
    {
        $predictions = ['diagnosis' => 'flu'];
        $confidenceScores = ['diagnosis' => 0.92];
        
        $explanation = $this->service->generateExplanation($predictions, $confidenceScores);
        
        $this->assertStringContainsString('high confidence', $explanation);
        $this->assertStringContainsString('0.92', $explanation);
    }

    /** @test */
    public function it_calculates_risk_level_correctly()
    {
        $highRiskScores = [0.9, 0.85, 0.95];
        $mediumRiskScores = [0.6, 0.55, 0.65];
        $lowRiskScores = [0.3, 0.25, 0.35];
        
        $this->assertEquals('high', $this->service->calculateRiskLevel($highRiskScores));
        $this->assertEquals('medium', $this->service->calculateRiskLevel($mediumRiskScores));
        $this->assertEquals('low', $this->service->calculateRiskLevel($lowRiskScores));
    }
}