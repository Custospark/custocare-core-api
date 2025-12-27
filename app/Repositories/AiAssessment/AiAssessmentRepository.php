<?php

namespace App\Repositories\AiAssessment;

use App\Models\AiAssessment;
use App\Repositories\Contracts\AiAssessmentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AiAssessmentRepository implements AiAssessmentRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function findByUuid(string $uuid): ?AiAssessment
    {
        try {
            return AiAssessment::with([
                'facility:id,name,code',
                'clinicalEncounter:id,encounter_uuid,encounter_type',
                'visit:id,visit_date,visit_type',
                'patient:id,patient_uuid,first_name,last_name,date_of_birth',
                'reviewer:id,staff_uuid,first_name,last_name,role'
            ])->where('assessment_uuid', $uuid)->first();
        } catch (\Exception $e) {
            // Log database errors but don't expose them
            Log::error('Failed to find AI assessment by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?AiAssessment
    {
        try {
            return AiAssessment::with([
                'facility:id,name,code',
                'clinicalEncounter:id,encounter_uuid,encounter_type',
                'visit:id,visit_date,visit_type',
                'patient:id,patient_uuid,first_name,last_name,date_of_birth',
                'reviewer:id,staff_uuid,first_name,last_name,role'
            ])->find($id);
        } catch (\Exception $e) {
            Log::error('Failed to find AI assessment by ID', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getAllPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        try {
            $query = AiAssessment::with([
                'facility:id,name,code',
                'patient:id,patient_uuid,first_name,last_name'
            ]);

            // Apply filters
            $this->applyFilters($query, $filters);

            // Apply sorting
            $query->orderBy('assessed_at', 'desc');

            return $query->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to get paginated AI assessments', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to retrieve AI assessments. Please try again.');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getByPatientId(int $patientId, array $filters = []): Collection
    {
        try {
            $query = AiAssessment::with([
                'facility:id,name',
                'clinicalEncounter:id,encounter_type',
                'visit:id,visit_date'
            ])->where('patient_id', $patientId);

            $this->applyFilters($query, $filters);

            return $query->orderBy('assessed_at', 'desc')->get();
        } catch (\Exception $e) {
            Log::error('Failed to get AI assessments by patient ID', [
                'patient_id' => $patientId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to retrieve patient AI assessments.');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getByEncounterId(int $encounterId): Collection
    {
        try {
            return AiAssessment::where('clinical_encounter_id', $encounterId)
                ->with(['facility:id,name', 'patient:id,patient_uuid'])
                ->orderBy('assessed_at', 'desc')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get AI assessments by encounter ID', [
                'encounter_id' => $encounterId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to retrieve encounter AI assessments.');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getByModelType(string $modelType, array $filters = []): Collection
    {
        try {
            $query = AiAssessment::where('model_type', $modelType)
                ->with(['facility:id,name', 'patient:id,patient_uuid']);

            $this->applyFilters($query, $filters);

            return $query->orderBy('assessed_at', 'desc')->get();
        } catch (\Exception $e) {
            Log::error('Failed to get AI assessments by model type', [
                'model_type' => $modelType,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to retrieve model type AI assessments.');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getPendingReviews(int $facilityId): Collection
    {
        try {
            return AiAssessment::where('facility_id', $facilityId)
                ->where('human_review_status', 'pending_review')
                ->with(['patient:id,patient_uuid,first_name,last_name'])
                ->orderBy('assessed_at', 'asc')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get pending reviews', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to retrieve pending reviews.');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $data): AiAssessment
    {
        try {
            return DB::transaction(function () use ($data) {
                $assessment = AiAssessment::create($data);
                
                // Log creation for audit trail
                Log::info('AI assessment created', [
                    'assessment_uuid' => $assessment->assessment_uuid,
                    'model_type' => $assessment->model_type,
                    'facility_id' => $assessment->facility_id,
                    'patient_id' => $assessment->patient_id
                ]);
                
                return $assessment;
            });
        } catch (\Exception $e) {
            Log::error('Failed to create AI assessment', [
                'data' => $this->sanitizeData($data),
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to create AI assessment. Please check the input data.');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function update(AiAssessment $assessment, array $data): bool
    {
        try {
            return DB::transaction(function () use ($assessment, $data) {
                $originalData = $assessment->toArray();
                $result = $assessment->update($data);
                
                if ($result) {
                    Log::info('AI assessment updated', [
                        'assessment_uuid' => $assessment->assessment_uuid,
                        'changes' => array_diff_assoc($assessment->toArray(), $originalData)
                    ]);
                }
                
                return $result;
            });
        } catch (\Exception $e) {
            Log::error('Failed to update AI assessment', [
                'assessment_uuid' => $assessment->assessment_uuid,
                'data' => $this->sanitizeData($data),
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to update AI assessment.');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function delete(AiAssessment $assessment): ?bool
    {
        try {
            Log::warning('AI assessment soft deleted', [
                'assessment_uuid' => $assessment->assessment_uuid,
                'deleted_by' => auth::d() ?? 'system'
            ]);
            
            return $assessment->delete();
        } catch (\Exception $e) {
            Log::error('Failed to delete AI assessment', [
                'assessment_uuid' => $assessment->assessment_uuid,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to delete AI assessment.');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function restore(AiAssessment $assessment): bool
    {
        try {
            $result = $assessment->restore();
            
            if ($result) {
                Log::info('AI assessment restored', [
                    'assessment_uuid' => $assessment->assessment_uuid,
                    'restored_by' => auth::d() ?? 'system'
                ]);
            }
            
            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to restore AI assessment', [
                'assessment_uuid' => $assessment->assessment_uuid,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to restore AI assessment.');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function updateReviewStatus(AiAssessment $assessment, string $status, array $reviewData = []): bool
    {
        try {
            return DB::transaction(function () use ($assessment, $status, $reviewData) {
                $data = array_merge([
                    'human_review_status' => $status,
                    'reviewed_at' => now(),
                    'reviewed_by_staff_id' => auth::d()
                ], $reviewData);
                
                $result = $assessment->update($data);
                
                if ($result) {
                    Log::info('AI assessment review status updated', [
                        'assessment_uuid' => $assessment->assessment_uuid,
                        'status' => $status,
                        'reviewed_by' => auth::d()
                    ]);
                }
                
                return $result;
            });
        } catch (\Exception $e) {
            Log::error('Failed to update review status', [
                'assessment_uuid' => $assessment->assessment_uuid,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to update review status.');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function recordOutcome(AiAssessment $assessment, array $outcomeData): bool
    {
        try {
            $data = array_merge([
                'clinical_outcome_recorded' => true,
                'actual_outcome' => $outcomeData['outcome'] ?? null
            ], $outcomeData);
            
            if (isset($outcomeData['accuracy'])) {
                $data['prediction_accuracy'] = $outcomeData['accuracy'];
            }
            
            $result = $assessment->update($data);
            
            if ($result) {
                Log::info('Clinical outcome recorded for AI assessment', [
                    'assessment_uuid' => $assessment->assessment_uuid,
                    'accuracy' => $outcomeData['accuracy'] ?? null
                ]);
            }
            
            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to record clinical outcome', [
                'assessment_uuid' => $assessment->assessment_uuid,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to record clinical outcome.');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function flagAdverseEvent(AiAssessment $assessment, array $eventData): bool
    {
        try {
            $data = array_merge([
                'adverse_event_flagged' => true,
                'adverse_event_description' => $eventData['description'] ?? null,
                'safety_alerts' => $eventData['alerts'] ?? null
            ], $eventData);
            
            $result = $assessment->update($data);
            
            if ($result) {
                Log::warning('Adverse event flagged for AI assessment', [
                    'assessment_uuid' => $assessment->assessment_uuid,
                    'event_data' => $this->sanitizeData($eventData)
                ]);
            }
            
            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to flag adverse event', [
                'assessment_uuid' => $assessment->assessment_uuid,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to flag adverse event.');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getModelStatistics(int $facilityId, ?string $timePeriod = null): array
    {
        try {
            $query = AiAssessment::where('facility_id', $facilityId);
            
            if ($timePeriod) {
                $dateRange = $this->getDateRange($timePeriod);
                $query->whereBetween('assessed_at', $dateRange);
            }
            
            return [
                'total_assessments' => $query->count(),
                'by_model_type' => $query->groupBy('model_type')
                    ->selectRaw('model_type, COUNT(*) as count')
                    ->pluck('count', 'model_type')
                    ->toArray(),
                'review_stats' => $query->groupBy('human_review_status')
                    ->selectRaw('human_review_status, COUNT(*) as count')
                    ->pluck('count', 'human_review_status')
                    ->toArray(),
                'average_processing_time' => $query->average('processing_time_ms'),
                'adverse_events' => $query->where('adverse_event_flagged', true)->count(),
                'outcome_recorded' => $query->where('clinical_outcome_recorded', true)->count()
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get model statistics', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to retrieve model statistics.');
        }
    }

    /**
     * Apply filters to query
     *
     * @param Builder $query
     * @param array $filters
     * @return void
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (isset($filters['facility_id'])) {
            $query->where('facility_id', $filters['facility_id']);
        }
        
        if (isset($filters['model_type'])) {
            $query->where('model_type', $filters['model_type']);
        }
        
        if (isset($filters['human_review_status'])) {
            $query->where('human_review_status', $filters['human_review_status']);
        }
        
        if (isset($filters['is_fda_cleared'])) {
            $query->where('is_fda_cleared', (bool) $filters['is_fda_cleared']);
        }
        
        if (isset($filters['start_date'])) {
            $query->whereDate('assessed_at', '>=', $filters['start_date']);
        }
        
        if (isset($filters['end_date'])) {
            $query->whereDate('assessed_at', '<=', $filters['end_date']);
        }
        
        if (isset($filters['patient_id'])) {
            $query->where('patient_id', $filters['patient_id']);
        }
        
        if (isset($filters['clinical_encounter_id'])) {
            $query->where('clinical_encounter_id', $filters['clinical_encounter_id']);
        }
    }

    /**
     * Get date range based on time period
     *
     * @param string $timePeriod
     * @return array
     */
    private function getDateRange(string $timePeriod): array
    {
        return match($timePeriod) {
            'today' => [Carbon::today(), Carbon::tomorrow()],
            'week' => [Carbon::now()->subWeek(), Carbon::now()],
            'month' => [Carbon::now()->subMonth(), Carbon::now()],
            'quarter' => [Carbon::now()->subQuarter(), Carbon::now()],
            'year' => [Carbon::now()->subYear(), Carbon::now()],
            default => [Carbon::now()->subMonth(), Carbon::now()],
        };
    }

    /**
     * Sanitize sensitive data for logging
     *
     * @param array $data
     * @return array
     */
    private function sanitizeData(array $data): array
    {
        $sensitiveFields = [
            'input_features',
            'output_predictions',
            'output_confidence_scores',
            'recommendations',
            'risk_scores',
            'feature_importance',
            'actual_outcome',
            'metadata'
        ];
        
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }
        
        return $data;
    }
}