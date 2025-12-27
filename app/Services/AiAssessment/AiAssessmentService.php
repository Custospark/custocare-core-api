<?php

namespace App\Services\AiAssessment;

use App\Services\Contracts\AiAssessmentServiceInterface;
use App\Repositories\Contracts\AiAssessmentRepositoryInterface;
use App\Models\AiAssessment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Enums\HumanReviewStatus;
use App\Enums\ModelType;

class AiAssessmentService implements AiAssessmentServiceInterface
{
    /**
     * Repository instance
     *
     * @var AiAssessmentRepositoryInterface
     */
    private AiAssessmentRepositoryInterface $repository;

    /**
     * Constructor
     *
     * @param AiAssessmentRepositoryInterface $repository
     */
    public function __construct(AiAssessmentRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * {@inheritDoc}
     */
    public function getAllAssessments(array $filters = [], int $perPage = 20): array
    {
        try {
            $assessments = $this->repository->getAllPaginated($filters, $perPage);
            
            return [
                'success' => true,
                'data' => $assessments,
                'message' => 'AI assessments retrieved successfully.',
                'meta' => [
                    'total' => $assessments->total(),
                    'per_page' => $assessments->perPage(),
                    'current_page' => $assessments->currentPage(),
                    'last_page' => $assessments->lastPage(),
                    'filters_applied' => $filters
                ]
            ];
        } catch (\RuntimeException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
                'error_code' => 'ASSESSMENT_RETRIEVAL_FAILED'
            ];
        } catch (\Exception $e) {
         Log::error('Unexpected error retrieving AI assessments', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving AI assessments.',
                'data' => [],
                'error_code' => 'INTERNAL_SERVER_ERROR'
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getAssessmentByUuid(string $uuid): array
    {
        try {
            $assessment = $this->repository->findByUuid($uuid);
            
            if (!$assessment) {
                return [
                    'success' => false,
                    'message' => 'AI assessment not found.',
                    'data' => null,
                    'error_code' => 'ASSESSMENT_NOT_FOUND'
                ];
            }
            
            return [
                'success' => true,
                'data' => $assessment,
                'message' => 'AI assessment retrieved successfully.'
            ];
        } catch (\RuntimeException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'error_code' => 'ASSESSMENT_RETRIEVAL_FAILED'
            ];
        } catch (\Exception $e) {
         Log::error('Unexpected error retrieving AI assessment', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving the AI assessment.',
                'data' => null,
                'error_code' => 'INTERNAL_SERVER_ERROR'
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function createAssessment(array $data): array
    {
        try {
            // Validate input features
            $validationResult = $this->validateInputFeatures(
                $data['input_features'] ?? [],
                $data['model_type'] ?? ''
            );
            
            if (!$validationResult['valid']) {
                return [
                    'success' => false,
                    'message' => 'Invalid input features: ' . $validationResult['message'],
                    'data' => null,
                    'error_code' => 'INVALID_INPUT_FEATURES'
                ];
            }
            
            // Generate UUID if not provided
            if (!isset($data['assessment_uuid'])) {
                $data['assessment_uuid'] = (string) Str::uuid();
            }
            
            // Generate hash for input features
            $data['input_features_hash'] = $this->generateInputHash($data['input_features']);
            
            // Set assessed timestamp if not provided
            if (!isset($data['assessed_at'])) {
                $data['assessed_at'] = now();
            }
            
            // Generate explanation if not provided
            if (!isset($data['explanation_text']) && isset($data['output_predictions']) && isset($data['output_confidence_scores'])) {
                $data['explanation_text'] = $this->generateExplanation(
                    $data['output_predictions'],
                    $data['output_confidence_scores']
                );
            }
            
            // Calculate risk level if not provided
            if (!isset($data['risk_scores']) && isset($data['output_predictions'])) {
                $data['risk_scores'] = $this->calculateRiskScores($data['output_predictions']);
            }
            
            DB::beginTransaction();
            
            $assessment = $this->repository->create($data);
            
            DB::commit();
            
         Log::info('AI assessment created successfully', [
                'assessment_uuid' => $assessment->assessment_uuid,
                'model_type' => $assessment->model_type->value
            ]);
            
            return [
                'success' => true,
                'data' => $assessment,
                'message' => 'AI assessment created successfully.'
            ];
        } catch (\RuntimeException $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'error_code' => 'ASSESSMENT_CREATION_FAILED'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
         Log::error('Unexpected error creating AI assessment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'An unexpected error occurred while creating the AI assessment.',
                'data' => null,
                'error_code' => 'INTERNAL_SERVER_ERROR'
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function updateAssessment(string $uuid, array $data): array
    {
        try {
            $assessment = $this->repository->findByUuid($uuid);
            
            if (!$assessment) {
                return [
                    'success' => false,
                    'message' => 'AI assessment not found.',
                    'data' => null,
                    'error_code' => 'ASSESSMENT_NOT_FOUND'
                ];
            }
            
            // Prevent updating certain fields
            $protectedFields = [
                'assessment_uuid',
                'input_features_hash',
                'assessed_at',
                'created_at'
            ];
            
            foreach ($protectedFields as $field) {
                if (isset($data[$field])) {
                    unset($data[$field]);
                }
            }
            
            // If input features are updated, regenerate hash
            if (isset($data['input_features'])) {
                $data['input_features_hash'] = $this->generateInputHash($data['input_features']);
            }
            
            DB::beginTransaction();
            
            $updated = $this->repository->update($assessment, $data);
            
            if (!$updated) {
                DB::rollBack();
                
                return [
                    'success' => false,
                    'message' => 'Failed to update AI assessment.',
                    'data' => null,
                    'error_code' => 'ASSESSMENT_UPDATE_FAILED'
                ];
            }
            
            DB::commit();
            
            // Refresh the model to get updated attributes
            $assessment->refresh();
            
            return [
                'success' => true,
                'data' => $assessment,
                'message' => 'AI assessment updated successfully.'
            ];
        } catch (\RuntimeException $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'error_code' => 'ASSESSMENT_UPDATE_FAILED'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
         Log::error('Unexpected error updating AI assessment', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An unexpected error occurred while updating the AI assessment.',
                'data' => null,
                'error_code' => 'INTERNAL_SERVER_ERROR'
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function deleteAssessment(string $uuid): array
    {
        try {
            $assessment = $this->repository->findByUuid($uuid);
            
            if (!$assessment) {
                return [
                    'success' => false,
                    'message' => 'AI assessment not found.',
                    'data' => null,
                    'error_code' => 'ASSESSMENT_NOT_FOUND'
                ];
            }
            
            // Check if assessment can be deleted
            if ($assessment->clinical_outcome_recorded) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete AI assessment with recorded clinical outcomes.',
                    'data' => null,
                    'error_code' => 'ASSESSMENT_LOCKED'
                ];
            }
            
            $deleted = $this->repository->delete($assessment);
            
            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete AI assessment.',
                    'data' => null,
                    'error_code' => 'ASSESSMENT_DELETION_FAILED'
                ];
            }
            
            return [
                'success' => true,
                'data' => null,
                'message' => 'AI assessment deleted successfully.'
            ];
        } catch (\RuntimeException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'error_code' => 'ASSESSMENT_DELETION_FAILED'
            ];
        } catch (\Exception $e) {
         Log::error('Unexpected error deleting AI assessment', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An unexpected error occurred while deleting the AI assessment.',
                'data' => null,
                'error_code' => 'INTERNAL_SERVER_ERROR'
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function reviewAssessment(string $uuid, array $reviewData): array
    {
        try {
            $assessment = $this->repository->findByUuid($uuid);
            
            if (!$assessment) {
                return [
                    'success' => false,
                    'message' => 'AI assessment not found.',
                    'data' => null,
                    'error_code' => 'ASSESSMENT_NOT_FOUND'
                ];
            }
            
            // Validate review status
            $validStatuses = array_column(HumanReviewStatus::cases(), 'value');
            if (!isset($reviewData['status']) || !in_array($reviewData['status'], $validStatuses)) {
                return [
                    'success' => false,
                    'message' => 'Invalid review status.',
                    'data' => null,
                    'error_code' => 'INVALID_REVIEW_STATUS'
                ];
            }
            
            // Check if already reviewed
            if ($assessment->human_review_status->isCompleted()) {
                return [
                    'success' => false,
                    'message' => 'AI assessment has already been reviewed.',
                    'data' => null,
                    'error_code' => 'ALREADY_REVIEWED'
                ];
            }
            
            // Additional validation for rejection
            if ($reviewData['status'] === HumanReviewStatus::REJECTED->value && empty($reviewData['rejection_reason'])) {
                return [
                    'success' => false,
                    'message' => 'Rejection reason is required when rejecting an assessment.',
                    'data' => null,
                    'error_code' => 'MISSING_REJECTION_REASON'
                ];
            }
            
            DB::beginTransaction();
            
            $updated = $this->repository->updateReviewStatus($assessment, $reviewData['status'], $reviewData);
            
            if (!$updated) {
                DB::rollBack();
                
                return [
                    'success' => false,
                    'message' => 'Failed to update review status.',
                    'data' => null,
                    'error_code' => 'REVIEW_UPDATE_FAILED'
                ];
            }
            
            DB::commit();
            
            $assessment->refresh();
            
         Log::info('AI assessment reviewed', [
                'assessment_uuid' => $assessment->assessment_uuid,
                'status' => $assessment->human_review_status->value,
                'reviewed_by' => auth::id()
            ]);
            
            return [
                'success' => true,
                'data' => $assessment,
                'message' => 'AI assessment reviewed successfully.'
            ];
        } catch (\RuntimeException $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'error_code' => 'REVIEW_UPDATE_FAILED'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
         Log::error('Unexpected error reviewing AI assessment', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An unexpected error occurred while reviewing the AI assessment.',
                'data' => null,
                'error_code' => 'INTERNAL_SERVER_ERROR'
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function recordClinicalOutcome(string $uuid, array $outcomeData): array
    {
        try {
            $assessment = $this->repository->findByUuid($uuid);
            
            if (!$assessment) {
                return [
                    'success' => false,
                    'message' => 'AI assessment not found.',
                    'data' => null,
                    'error_code' => 'ASSESSMENT_NOT_FOUND'
                ];
            }
            
            // Validate outcome data
            if (empty($outcomeData['outcome'])) {
                return [
                    'success' => false,
                    'message' => 'Outcome data is required.',
                    'data' => null,
                    'error_code' => 'MISSING_OUTCOME_DATA'
                ];
            }
            
            // Check if outcome already recorded
            if ($assessment->clinical_outcome_recorded) {
                return [
                    'success' => false,
                    'message' => 'Clinical outcome already recorded for this assessment.',
                    'data' => null,
                    'error_code' => 'OUTCOME_ALREADY_RECORDED'
                ];
            }
            
            // Calculate accuracy if predictions exist
            if (isset($outcomeData['accuracy'])) {
                $outcomeData['accuracy'] = min(1.0, max(0.0, (float) $outcomeData['accuracy']));
            }
            
            DB::beginTransaction();
            
            $updated = $this->repository->recordOutcome($assessment, $outcomeData);
            
            if (!$updated) {
                DB::rollBack();
                
                return [
                    'success' => false,
                    'message' => 'Failed to record clinical outcome.',
                    'data' => null,
                    'error_code' => 'OUTCOME_RECORDING_FAILED'
                ];
            }
            
            DB::commit();
            
            $assessment->refresh();
            
         Log::info('Clinical outcome recorded', [
                'assessment_uuid' => $assessment->assessment_uuid,
                'accuracy' => $assessment->prediction_accuracy
            ]);
            
            return [
                'success' => true,
                'data' => $assessment,
                'message' => 'Clinical outcome recorded successfully.'
            ];
        } catch (\RuntimeException $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'error_code' => 'OUTCOME_RECORDING_FAILED'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
         Log::error('Unexpected error recording clinical outcome', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An unexpected error occurred while recording the clinical outcome.',
                'data' => null,
                'error_code' => 'INTERNAL_SERVER_ERROR'
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function flagAdverseEvent(string $uuid, array $eventData): array
    {
        try {
            $assessment = $this->repository->findByUuid($uuid);
            
            if (!$assessment) {
                return [
                    'success' => false,
                    'message' => 'AI assessment not found.',
                    'data' => null,
                    'error_code' => 'ASSESSMENT_NOT_FOUND'
                ];
            }
            
            // Validate event data
            if (empty($eventData['description'])) {
                return [
                    'success' => false,
                    'message' => 'Adverse event description is required.',
                    'data' => null,
                    'error_code' => 'MISSING_EVENT_DESCRIPTION'
                ];
            }
            
            // Check if already flagged
            if ($assessment->adverse_event_flagged) {
                return [
                    'success' => false,
                    'message' => 'Adverse event already flagged for this assessment.',
                    'data' => null,
                    'error_code' => 'EVENT_ALREADY_FLAGGED'
                ];
            }
            
            DB::beginTransaction();
            
            $updated = $this->repository->flagAdverseEvent($assessment, $eventData);
            
            if (!$updated) {
                DB::rollBack();
                
                return [
                    'success' => false,
                    'message' => 'Failed to flag adverse event.',
                    'data' => null,
                    'error_code' => 'EVENT_FLAGGING_FAILED'
                ];
            }
            
            DB::commit();
            
            $assessment->refresh();
            
         Log::warning('Adverse event flagged', [
                'assessment_uuid' => $assessment->assessment_uuid,
                'description' => substr($eventData['description'], 0, 100)
            ]);
            
            return [
                'success' => true,
                'data' => $assessment,
                'message' => 'Adverse event flagged successfully.'
            ];
        } catch (\RuntimeException $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'error_code' => 'EVENT_FLAGGING_FAILED'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
         Log::error('Unexpected error flagging adverse event', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An unexpected error occurred while flagging the adverse event.',
                'data' => null,
                'error_code' => 'INTERNAL_SERVER_ERROR'
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getPatientAssessments(int $patientId, array $filters = []): array
    {
        try {
            $assessments = $this->repository->getByPatientId($patientId, $filters);
            
            return [
                'success' => true,
                'data' => $assessments,
                'message' => 'Patient AI assessments retrieved successfully.',
                'meta' => [
                    'patient_id' => $patientId,
                    'total_assessments' => $assessments->count(),
                    'filters_applied' => $filters
                ]
            ];
        } catch (\RuntimeException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
                'error_code' => 'PATIENT_ASSESSMENTS_RETRIEVAL_FAILED'
            ];
        } catch (\Exception $e) {
         Log::error('Unexpected error retrieving patient AI assessments', [
                'patient_id' => $patientId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving patient AI assessments.',
                'data' => [],
                'error_code' => 'INTERNAL_SERVER_ERROR'
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getEncounterAssessments(int $encounterId): array
    {
        try {
            $assessments = $this->repository->getByEncounterId($encounterId);
            
            return [
                'success' => true,
                'data' => $assessments,
                'message' => 'Encounter AI assessments retrieved successfully.',
                'meta' => [
                    'encounter_id' => $encounterId,
                    'total_assessments' => $assessments->count()
                ]
            ];
        } catch (\RuntimeException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
                'error_code' => 'ENCOUNTER_ASSESSMENTS_RETRIEVAL_FAILED'
            ];
        } catch (\Exception $e) {
         Log::error('Unexpected error retrieving encounter AI assessments', [
                'encounter_id' => $encounterId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving encounter AI assessments.',
                'data' => [],
                'error_code' => 'INTERNAL_SERVER_ERROR'
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getPendingReviews(int $facilityId): array
    {
        try {
            $assessments = $this->repository->getPendingReviews($facilityId);
            
            return [
                'success' => true,
                'data' => $assessments,
                'message' => 'Pending reviews retrieved successfully.',
                'meta' => [
                    'facility_id' => $facilityId,
                    'pending_count' => $assessments->count()
                ]
            ];
        } catch (\RuntimeException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
                'error_code' => 'PENDING_REVIEWS_RETRIEVAL_FAILED'
            ];
        } catch (\Exception $e) {
         Log::error('Unexpected error retrieving pending reviews', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving pending reviews.',
                'data' => [],
                'error_code' => 'INTERNAL_SERVER_ERROR'
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getModelStatistics(int $facilityId, ?string $timePeriod = null): array
    {
        try {
            $statistics = $this->repository->getModelStatistics($facilityId, $timePeriod);
            
            return [
                'success' => true,
                'data' => $statistics,
                'message' => 'Model statistics retrieved successfully.',
                'meta' => [
                    'facility_id' => $facilityId,
                    'time_period' => $timePeriod ?? 'all_time'
                ]
            ];
        } catch (\RuntimeException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
                'error_code' => 'MODEL_STATISTICS_RETRIEVAL_FAILED'
            ];
        } catch (\Exception $e) {
         Log::error('Unexpected error retrieving model statistics', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving model statistics.',
                'data' => [],
                'error_code' => 'INTERNAL_SERVER_ERROR'
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function validateInputFeatures(array $inputFeatures, string $modelType): array
    {
        try {
            // Define validation rules based on model type
            $validationRules = $this->getValidationRulesForModelType($modelType);
            
            $validator = Validator::make($inputFeatures, $validationRules);
            
            if ($validator->fails()) {
                return [
                    'valid' => false,
                    'message' => implode(' ', $validator->errors()->all()),
                    'errors' => $validator->errors()->toArray()
                ];
            }
            
            // Additional model-specific validation
            if ($modelType === ModelType::RISK_STRATIFICATION->value) {
                if (!$this->validateRiskStratificationInput($inputFeatures)) {
                    return [
                        'valid' => false,
                        'message' => 'Invalid risk stratification input features.',
                        'errors' => []
                    ];
                }
            }
            
            return [
                'valid' => true,
                'message' => 'Input features are valid.',
                'errors' => []
            ];
        } catch (\Exception $e) {
         Log::error('Error validating input features', [
                'model_type' => $modelType,
                'error' => $e->getMessage()
            ]);
            
            return [
                'valid' => false,
                'message' => 'Failed to validate input features.',
                'errors' => []
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
public function generateExplanation(array $predictions, array $confidenceScores): string
{
    // Guard clauses — fast exit, no exceptions
    if (empty($predictions) || empty($confidenceScores)) {
        return 'Insufficient data to generate an explanation.';
    }

    // Ensure both arrays are aligned
    if (count($predictions) !== count($confidenceScores)) {
        Log::warning('Predictions and confidence scores count mismatch', [
            'predictions_count' => count($predictions),
            'confidence_count'  => count($confidenceScores),
        ]);

        return 'Prediction data is inconsistent and cannot be explained reliably.';
    }

    /*
     |--------------------------------------------------------------------------
     | Define TopPrediction
     |--------------------------------------------------------------------------
     | TopPrediction is the prediction with the highest confidence score.
     |--------------------------------------------------------------------------
     */
    $topPredictionIndex = array_keys($confidenceScores, max($confidenceScores))[0] ?? null;

    if ($topPredictionIndex === null || !isset($predictions[$topPredictionIndex])) {
        return 'Unable to determine the most confident prediction.';
    }

    $topPrediction  = $predictions[$topPredictionIndex];
    $topConfidence  = $confidenceScores[$topPredictionIndex];

    // Normalize confidence for presentation
    $confidencePercentage = round($topConfidence * 100, 1);

    /*
     |--------------------------------------------------------------------------
     | Generate Human-Readable Explanation
     |--------------------------------------------------------------------------
     */
    if ($topConfidence >= 0.90) {
        return sprintf(
            'The AI model shows high confidence (%.1f%%) in the prediction: %s. This result is supported by strong and consistent patterns in the input data.',
            $confidencePercentage,
            $topPrediction
        );
    }

    if ($topConfidence >= 0.70) {
        return sprintf(
            'The AI model shows moderate confidence (%.1f%%) in the prediction: %s. Additional clinical context should be considered before making decisions.',
            $confidencePercentage,
            $topPrediction
        );
    }

    return sprintf(
        'The AI model shows low confidence (%.1f%%) in the prediction: %s. This output should be interpreted cautiously and must be clinically validated.',
        $confidencePercentage,
        $topPrediction
    );
}


    /**
     * {@inheritDoc}
     */
    public function calculateRiskLevel(array $riskScores): string
    {
        try {
            if (empty($riskScores)) {
                return 'unknown';
            }
            
            $averageRisk = array_sum($riskScores) / count($riskScores);
            
            if ($averageRisk >= 0.8) {
                return 'high';
            } elseif ($averageRisk >= 0.5) {
                return 'medium';
            } else {
                return 'low';
            }
        } catch (\Exception $e) {
         Log::error('Error calculating risk level', [
                'error' => $e->getMessage()
            ]);
            
            return 'unknown';
        }
    }

    /**
     * {@inheritDoc}
     */
    public function exportToCsv(array $filters): string
    {
        // Implementation for CSV export
        // This would typically generate a CSV file and return the file path
        return 'Export functionality not implemented in this example.';
    }

    /**
     * {@inheritDoc}
     */
    public function importFromFile(UploadedFile $file, int $facilityId): array
    {
        // Implementation for file import
        // This would typically parse the file and create assessments
        return [
            'success' => false,
            'message' => 'Import functionality not implemented in this example.',
            'data' => null,
            'error_code' => 'NOT_IMPLEMENTED'
        ];
    }

    /**
     * Generate hash for input features
     *
     * @param array $inputFeatures
     * @return string
     */
    private function generateInputHash(array $inputFeatures): string
    {
        $json = json_encode($inputFeatures, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return hash('sha256', $json);
    }

    /**
     * Calculate risk scores from predictions
     *
     * @param array $predictions
     * @return array
     */
    private function calculateRiskScores(array $predictions): array
    {
        // Simplified risk score calculation
        // In production, this would be model-specific logic
        $riskScores = [];
        
        foreach ($predictions as $key => $value) {
            if (is_numeric($value)) {
                $riskScores[$key] = min(1.0, max(0.0, abs($value) / 10));
            } else {
                $riskScores[$key] = 0.5; // Default medium risk for non-numeric predictions
            }
        }
        
        return $riskScores;
    }

    /**
     * Get validation rules for model type
     *
     * @param string $modelType
     * @return array
     */
    private function getValidationRulesForModelType(string $modelType): array
    {
        $baseRules = [
            'patient_age' => 'required|integer|min:0|max:120',
            'patient_gender' => 'required|string|in:male,female,other,unknown',
            'timestamp' => 'required|date'
        ];
        
        $modelSpecificRules = match($modelType) {
            ModelType::RISK_STRATIFICATION->value => [
                'vital_signs' => 'required|array',
                'vital_signs.heart_rate' => 'required|numeric|min:20|max:250',
                'vital_signs.blood_pressure' => 'required|string',
                'vital_signs.temperature' => 'required|numeric|min:30|max:45',
                'lab_results' => 'nullable|array',
                'medical_history' => 'nullable|array'
            ],
            ModelType::DIAGNOSTIC_ASSISTANT->value => [
                'symptoms' => 'required|array',
                'symptoms.*' => 'string',
                'duration' => 'required|integer|min:0',
                'severity' => 'required|string|in:mild,moderate,severe',
                'previous_treatments' => 'nullable|array'
            ],
            ModelType::IMAGE_ANALYSIS->value => [
                'image_type' => 'required|string',
                'image_quality' => 'required|string|in:excellent,good,fair,poor',
                'body_part' => 'required|string',
                'contrast_used' => 'nullable|boolean'
            ],
            default => []
        };
        
        return array_merge($baseRules, $modelSpecificRules);
    }

    /**
     * Validate risk stratification input
     *
     * @param array $inputFeatures
     * @return bool
     */
    private function validateRiskStratificationInput(array $inputFeatures): bool
    {
        // Additional validation logic for risk stratification
        if (!isset($inputFeatures['vital_signs'])) {
            return false;
        }
        
        $vitalSigns = $inputFeatures['vital_signs'];
        
        // Check for critical vital signs
        if (isset($vitalSigns['heart_rate']) && ($vitalSigns['heart_rate'] < 40 || $vitalSigns['heart_rate'] > 180)) {
            return false; // Invalid heart rate
        }
        
        return true;
    }
}