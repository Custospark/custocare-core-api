<?php

namespace App\Services\DepartmentQueueView;

use App\Models\DepartmentQueueView;
use App\Repositories\Contracts\DepartmentQueueViewRepositoryInterface;
use App\Services\Contracts\DepartmentQueueViewServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DepartmentQueueViewService implements DepartmentQueueViewServiceInterface
{
    /**
     * Constructor with dependency injection
     */
    public function __construct(
        protected DepartmentQueueViewRepositoryInterface $repository
    ) {}

    /**
     * Get department queue view by ID
     */
    public function getQueueViewById(int $id): ?DepartmentQueueView
    {
        try {
            return $this->repository->findById($id);
        } catch (\Exception $e) {
            Log::error('Service: Failed to get queue view by ID', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get queue view by department and type
     */
    public function getQueueViewByDepartmentAndType(int $departmentId, string $queueType): ?DepartmentQueueView
    {
        try {
            return $this->repository->findByDepartmentAndType($departmentId, $queueType);
        } catch (\Exception $e) {
            Log::error('Service: Failed to get queue view by department and type', [
                'department_id' => $departmentId,
                'queue_type' => $queueType,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get all queue views for a facility
     */
    public function getFacilityQueueViews(int $facilityId, array $filters = []): Collection
    {
        try {
            // Validate filters
            $validatedFilters = $this->validateFilters($filters);
            
            return $this->repository->getByFacilityId($facilityId, $validatedFilters);
        } catch (\Exception $e) {
            Log::error('Service: Failed to get facility queue views', [
                'facility_id' => $facilityId,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return collect();
        }
    }

    /**
     * Get critical queues for alerting
     */
    public function getCriticalQueues(int $facilityId): Collection
    {
        try {
            $criticalQueues = $this->repository->getCriticalQueues($facilityId);
            
            // Add alert level based on severity
            return $criticalQueues->map(function ($queue) {
                $queue->alert_level = $this->calculateAlertLevel($queue);
                return $queue;
            });
        } catch (\Exception $e) {
            Log::error('Service: Failed to get critical queues', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            return collect();
        }
    }

    /**
     * Get current dashboard statistics
     */
    public function getDashboardStatistics(int $facilityId): array
    {
        try {
            $statistics = $this->repository->getDashboardStatistics($facilityId);
            
            // Add derived metrics
            $statistics['overall_capacity_status'] = $this->calculateOverallCapacityStatus($statistics);
            $statistics['recommended_actions'] = $this->generateRecommendedActions($statistics);
            
            return $statistics;
        } catch (\Exception $e) {
            Log::error('Service: Failed to get dashboard statistics', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            return [
                'error' => 'Failed to load dashboard statistics',
                'total_patients_waiting' => 0,
                'total_patients_in_treatment' => 0,
                'critical_departments_count' => 0,
                'average_wait_time' => 0,
                'by_queue_type' => [],
                'overall_capacity_status' => 'unknown'
            ];
        }
    }

    /**
     * Create a new queue view snapshot
     */
    public function createQueueView(array $data): array
    {
        DB::beginTransaction();
        
        try {
            // Validate data
            $validation = $this->validateQueueData($data);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation['errors']
                ];
            }

            // Add snapshot timestamp if not provided
            if (!isset($data['snapshot_at'])) {
                $data['snapshot_at'] = now();
            }

            // Calculate derived fields
            $data = $this->calculateDerivedFields($data);

            // Create the queue view
            $queueView = $this->repository->create($data);

            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Queue view created successfully',
                'data' => $queueView
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Service: Failed to create queue view', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to create queue view',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update queue view metrics
     */
    public function updateQueueView(int $id, array $data): array
    {
        DB::beginTransaction();
        
        try {
            $queueView = $this->repository->findById($id);
            
            if (!$queueView) {
                return [
                    'success' => false,
                    'message' => 'Queue view not found',
                    'code' => 404
                ];
            }

            // Validate update data
            $validation = $this->validateQueueData($data, true);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation['errors']
                ];
            }

            // Calculate derived fields
            $data = $this->calculateDerivedFields($data);

            // Update the queue view
            $success = $this->repository->update($queueView, $data);

            if (!$success) {
                throw new \RuntimeException('Failed to update queue view');
            }

            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Queue view updated successfully',
                'data' => $queueView->refresh()
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Service: Failed to update queue view', [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update queue view',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Batch update queue views (for 30-second refresh)
     */
    public function batchUpdateQueueViews(array $queueData): array
    {
        DB::beginTransaction();
        
        try {
            // Validate all queue data
            $validUpdates = [];
            $errors = [];

            foreach ($queueData as $index => $data) {
                $validation = $this->validateQueueData($data, true);
                
                if (!$validation['valid']) {
                    $errors[$index] = $validation['errors'];
                    continue;
                }

                // Calculate derived fields
                $data = $this->calculateDerivedFields($data);
                $data['snapshot_at'] = $data['snapshot_at'] ?? now();
                
                $validUpdates[] = $data;
            }

            if (!empty($errors)) {
                return [
                    'success' => false,
                    'message' => 'Some queue data validation failed',
                    'errors' => $errors,
                    'valid_updates' => count($validUpdates)
                ];
            }

            // Perform batch update
            $success = $this->repository->batchUpdate($validUpdates);

            if (!$success) {
                throw new \RuntimeException('Batch update failed');
            }

            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Queue views updated successfully',
                'updated_count' => count($validUpdates)
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Service: Failed to batch update queue views', [
                'queue_count' => count($queueData),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to batch update queue views',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete a queue view
     */
    public function deleteQueueView(int $id): array
    {
        DB::beginTransaction();
        
        try {
            $queueView = $this->repository->findById($id);
            
            if (!$queueView) {
                return [
                    'success' => false,
                    'message' => 'Queue view not found',
                    'code' => 404
                ];
            }

            $success = $this->repository->delete($queueView);

            if (!$success) {
                throw new \RuntimeException('Failed to delete queue view');
            }

            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Queue view deleted successfully'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Service: Failed to delete queue view', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to delete queue view',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get wait time analysis for a department
     */
    public function analyzeWaitTimes(int $departmentId, string $queueType): array
    {
        try {
            $queueView = $this->repository->findByDepartmentAndType($departmentId, $queueType);
            
            if (!$queueView) {
                return [
                    'success' => false,
                    'message' => 'Queue view not found',
                    'code' => 404
                ];
            }

            $trends = $this->repository->getWaitTimeTrends($departmentId, $queueType, 6);
            
            $analysis = [
                'current_wait_time' => $queueView->average_wait_minutes,
                'trend' => $this->calculateWaitTimeTrend($trends),
                'severity' => $this->assessWaitTimeSeverity($queueView),
                'recommendations' => $this->generateWaitTimeRecommendations($queueView),
                'historical_trends' => $trends
            ];

            return [
                'success' => true,
                'data' => $analysis
            ];
        } catch (\Exception $e) {
            Log::error('Service: Failed to analyze wait times', [
                'department_id' => $departmentId,
                'queue_type' => $queueType,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to analyze wait times',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get capacity analysis across facility
     */
    public function analyzeCapacity(int $facilityId): array
    {
        try {
            $queueViews = $this->repository->getByFacilityId($facilityId, ['current' => true]);
            
            $analysis = [
                'total_departments' => $queueViews->count(),
                'critical_departments' => $queueViews->whereIn('capacity_status', ['critical', 'at_capacity'])->count(),
                'overall_capacity_percentage' => $queueViews->avg('capacity_percentage') ?? 0,
                'bottlenecks' => $this->identifyBottlenecks($queueViews),
                'recommendations' => $this->generateCapacityRecommendations($queueViews)
            ];

            return [
                'success' => true,
                'data' => $analysis
            ];
        } catch (\Exception $e) {
            Log::error('Service: Failed to analyze capacity', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to analyze capacity',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate queue predictions for next hour
     */
    public function generatePredictions(int $departmentId, string $queueType): array
    {
        try {
            // In production, this would integrate with ML service
            // For now, return mock predictions based on current data
            
            $queueView = $this->repository->findByDepartmentAndType($departmentId, $queueType);
            
            if (!$queueView) {
                return [
                    'success' => false,
                    'message' => 'Queue view not found',
                    'code' => 404
                ];
            }

            $predictions = [
                'next_30_minutes' => [
                    'estimated_wait_time' => $queueView->average_wait_minutes * 1.1,
                    'predicted_patient_count' => $queueView->patients_waiting_count + 3,
                    'confidence_score' => 0.75
                ],
                'next_hour' => [
                    'estimated_wait_time' => $queueView->average_wait_minutes * 1.3,
                    'predicted_patient_count' => $queueView->patients_waiting_count + 7,
                    'confidence_score' => 0.65
                ],
                'recommended_staffing' => $this->calculateRecommendedStaffing($queueView),
                'peak_time_prediction' => now()->addHours(2)->format('H:i')
            ];

            return [
                'success' => true,
                'data' => $predictions
            ];
        } catch (\Exception $e) {
            Log::error('Service: Failed to generate predictions', [
                'department_id' => $departmentId,
                'queue_type' => $queueType,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to generate predictions',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate queue view data
     */
    public function validateQueueData(array $data, bool $isUpdate = false): array
    {
        $rules = [
            'facility_id' => 'required|integer|exists:facilities,id',
            'department_id' => 'required|integer|exists:departments,id',
            'queue_type' => 'required|in:' . implode(',', DepartmentQueueView::QUEUE_TYPES),
            'patients_waiting_count' => 'integer|min:0|max:1000',
            'patients_in_treatment_count' => 'integer|min:0|max:500',
            'average_wait_minutes' => 'integer|min:0|max:480',
            'median_wait_minutes' => 'integer|min:0|max:480',
            'longest_wait_minutes' => 'integer|min:0|max:1440',
            'staff_available_count' => 'integer|min:0|max:100',
            'staff_total_count' => 'integer|min:0|max:100',
            'capacity_percentage' => 'integer|min:0|max:100',
            'bed_utilization_percentage' => 'integer|min:0|max:100',
            'capacity_status' => 'in:' . implode(',', DepartmentQueueView::CAPACITY_STATUSES),
            'snapshot_at' => 'date',
            'predicted_next_available_at' => 'date|nullable',
        ];

        // For updates, make fields optional
        if ($isUpdate) {
            foreach ($rules as $field => $rule) {
                if ($field !== 'facility_id' && $field !== 'department_id') {
                    $rules[$field] = str_replace('required|', '', $rule);
                }
            }
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return [
                'valid' => false,
                'errors' => $validator->errors()->toArray()
            ];
        }

        // Additional business logic validation
        $customErrors = [];

        if (isset($data['staff_available_count']) && isset($data['staff_total_count'])) {
            if ($data['staff_available_count'] > $data['staff_total_count']) {
                $customErrors['staff_available_count'] = ['Available staff cannot exceed total staff'];
            }
        }

        if (isset($data['patients_waiting_count']) && isset($data['patients_in_treatment_count'])) {
            $total = $data['patients_waiting_count'] + $data['patients_in_treatment_count'];
            if ($total > 1500) {
                $customErrors['total_active_patients'] = ['Total patients exceeds system limits'];
            }
        }

        if (!empty($customErrors)) {
            return [
                'valid' => false,
                'errors' => $customErrors
            ];
        }

        return ['valid' => true, 'errors' => []];
    }

    /**
     * Calculate derived fields based on input data
     */
    private function calculateDerivedFields(array $data): array
    {
        // Calculate total active patients
        if (isset($data['patients_waiting_count']) || isset($data['patients_in_treatment_count'])) {
            $waiting = $data['patients_waiting_count'] ?? 0;
            $inTreatment = $data['patients_in_treatment_count'] ?? 0;
            $data['total_active_patients'] = $waiting + $inTreatment;
        }

        // Determine capacity status if not provided
        if (!isset($data['capacity_status']) && isset($data['capacity_percentage'])) {
            $data['capacity_status'] = $this->determineCapacityStatus($data['capacity_percentage']);
        }

        // Calculate capacity percentage if not provided but bed utilization is
        if (!isset($data['capacity_percentage']) && isset($data['bed_utilization_percentage'])) {
            $data['capacity_percentage'] = $data['bed_utilization_percentage'];
        }

        return $data;
    }

    /**
     * Determine capacity status based on percentage
     */
    private function determineCapacityStatus(int $percentage): string
    {
        if ($percentage >= 95) return 'at_capacity';
        if ($percentage >= 85) return 'critical';
        if ($percentage >= 70) return 'busy';
        return 'normal';
    }

    /**
     * Calculate alert level for critical queue
     */
    private function calculateAlertLevel(DepartmentQueueView $queue): string
    {
        if ($queue->capacity_status === 'at_capacity') return 'severe';
        if ($queue->longest_wait_minutes > 180) return 'high';
        if ($queue->capacity_status === 'critical') return 'medium';
        return 'low';
    }

    /**
     * Calculate overall capacity status
     */
    private function calculateOverallCapacityStatus(array $statistics): string
    {
        $criticalPercentage = $statistics['critical_departments_count'] > 0 ? 
            ($statistics['critical_departments_count'] / $statistics['total_departments'] ?? 1) * 100 : 0;

        if ($criticalPercentage > 30) return 'critical';
        if ($criticalPercentage > 15) return 'busy';
        if ($statistics['average_wait_time'] > 60) return 'busy';
        return 'normal';
    }

    /**
     * Calculate wait time trend
     */
    private function calculateWaitTimeTrend(Collection $trends): string
    {
        if ($trends->count() < 2) return 'stable';
        
        $first = $trends->first()->average_wait_minutes ?? 0;
        $last = $trends->last()->average_wait_minutes ?? 0;
        
        if ($last > $first * 1.2) return 'increasing';
        if ($last < $first * 0.8) return 'decreasing';
        return 'stable';
    }

    /**
     * Assess wait time severity
     */
    private function assessWaitTimeSeverity(DepartmentQueueView $queue): string
    {
        if ($queue->average_wait_minutes > 120) return 'severe';
        if ($queue->average_wait_minutes > 60) return 'high';
        if ($queue->average_wait_minutes > 30) return 'medium';
        return 'low';
    }

    /**
     * Generate wait time recommendations
     */
    private function generateWaitTimeRecommendations(DepartmentQueueView $queue): array
    {
        $recommendations = [];
        
        if ($queue->average_wait_minutes > 60) {
            $recommendations[] = 'Consider deploying additional staff';
            $recommendations[] = 'Review patient prioritization process';
        }
        
        if ($queue->patients_waiting_count > 20) {
            $recommendations[] = 'Open additional treatment rooms if available';
        }
        
        if ($queue->capacity_percentage > 90) {
            $recommendations[] = 'Redirect non-urgent cases to other departments';
        }
        
        return $recommendations;
    }

    /**
     * Identify bottlenecks
     */
    private function identifyBottlenecks(Collection $queueViews): array
    {
        return $queueViews
            ->filter(function ($queue) {
                return $queue->capacity_status === 'critical' || 
                       $queue->capacity_status === 'at_capacity' ||
                       $queue->average_wait_minutes > 90;
            })
            ->map(function ($queue) {
                return [
                    'department_id' => $queue->department_id,
                    'queue_type' => $queue->queue_type,
                    'issue' => $this->identifyBottleneckIssue($queue),
                    'severity' => $queue->capacity_status === 'at_capacity' ? 'high' : 'medium'
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Identify specific bottleneck issue
     */
    private function identifyBottleneckIssue(DepartmentQueueView $queue): string
    {
        if ($queue->capacity_status === 'at_capacity') return 'Maximum capacity reached';
        if ($queue->average_wait_minutes > 120) return 'Excessive wait times';
        if ($queue->staff_available_count < 2 && $queue->patients_waiting_count > 10) return 'Insufficient staffing';
        return 'High utilization';
    }

    /**
     * Generate capacity recommendations
     */
    private function generateCapacityRecommendations(Collection $queueViews): array
    {
        $recommendations = [];
        $criticalCount = $queueViews->whereIn('capacity_status', ['critical', 'at_capacity'])->count();
        
        if ($criticalCount > 3) {
            $recommendations[] = 'Activate facility-wide capacity protocol';
            $recommendations[] = 'Notify hospital administration';
        }
        
        if ($queueViews->sum('patients_waiting_count') > 100) {
            $recommendations[] = 'Consider opening overflow areas';
        }
        
        $understaffed = $queueViews->filter(function ($queue) {
            return $queue->staff_available_count < ($queue->staff_total_count * 0.5);
        });
        
        if ($understaffed->count() > 0) {
            $recommendations[] = 'Review staffing allocation across departments';
        }
        
        return $recommendations;
    }

    /**
     * Calculate recommended staffing
     */
    private function calculateRecommendedStaffing(DepartmentQueueView $queue): int
    {
        $baseStaff = 2; // Minimum staff
        $patientsPerStaff = 3; // Industry standard
        
        $recommended = $baseStaff + ceil($queue->patients_waiting_count / $patientsPerStaff);
        
        return min($recommended, 10); // Cap at 10 staff
    }

    /**
     * Generate recommended actions
     */
    private function generateRecommendedActions(array $statistics): array
    {
        $actions = [];
        
        if ($statistics['critical_departments_count'] > 0) {
            $actions[] = 'Review critical department allocations';
        }
        
        if ($statistics['average_wait_time'] > 45) {
            $actions[] = 'Implement wait time reduction strategies';
        }
        
        if ($statistics['total_patients_waiting'] > 50) {
            $actions[] = 'Consider opening additional triage stations';
        }
        
        return $actions;
    }

    /**
     * Validate filters
     */
    private function validateFilters(array $filters): array
    {
        $validFilters = [];
        
        if (isset($filters['queue_type'])) {
            if (in_array($filters['queue_type'], DepartmentQueueView::QUEUE_TYPES)) {
                $validFilters['queue_type'] = $filters['queue_type'];
            }
        }
        
        if (isset($filters['capacity_status'])) {
            if (in_array($filters['capacity_status'], DepartmentQueueView::CAPACITY_STATUSES)) {
                $validFilters['capacity_status'] = $filters['capacity_status'];
            }
        }
        
        if (isset($filters['current']) && $filters['current'] === true) {
            $validFilters['current'] = true;
        }
        
        // Date validation
        if (isset($filters['date_from'])) {
            try {
                $date = \Carbon\Carbon::parse($filters['date_from']);
                $validFilters['date_from'] = $date->toDateTimeString();
            } catch (\Exception $e) {
                // Invalid date, skip filter
            }
        }
        
        if (isset($filters['date_to'])) {
            try {
                $date = \Carbon\Carbon::parse($filters['date_to']);
                $validFilters['date_to'] = $date->toDateTimeString();
            } catch (\Exception $e) {
                // Invalid date, skip filter
            }
        }
        
        return $validFilters;
    }
}