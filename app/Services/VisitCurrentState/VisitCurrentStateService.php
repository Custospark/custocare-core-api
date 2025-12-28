<?php

namespace App\Services\VisitCurrentState;

use App\Repositories\Contracts\VisitCurrentStateRepositoryInterface;
use App\Services\Contracts\VisitCurrentStateServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VisitCurrentStateService implements VisitCurrentStateServiceInterface
{
    /**
     * @var VisitCurrentStateRepositoryInterface
     */
    protected $repository;

    /**
     * VisitCurrentStateService constructor.
     *
     * @param VisitCurrentStateRepositoryInterface $repository
     */
    public function __construct(VisitCurrentStateRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * {@inheritdoc}
     */
    public function getVisitCurrentState(int $id): array
    {
        try {
            $visitCurrentState = $this->repository->findById($id);
            
            if (!$visitCurrentState) {
                return [
                    'success' => false,
                    'message' => 'Visit current state not found.',
                    'data' => null,
                    'status' => 404
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Visit current state retrieved successfully.',
                'data' => $visitCurrentState,
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get visit current state', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while retrieving the visit current state.',
                'data' => null,
                'status' => 500
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getVisitCurrentStateByVisitId(int $visitId): array
    {
        try {
            $visitCurrentState = $this->repository->findByVisitId($visitId);
            
            if (!$visitCurrentState) {
                return [
                    'success' => false,
                    'message' => 'Visit current state not found for the given visit ID.',
                    'data' => null,
                    'status' => 404
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Visit current state retrieved successfully.',
                'data' => $visitCurrentState,
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get visit current state by visit ID', [
                'visit_id' => $visitId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while retrieving the visit current state.',
                'data' => null,
                'status' => 500
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAllVisitCurrentStates(array $filters = []): array
    {
        try {
            $visitCurrentStates = $this->repository->all();
            
            return [
                'success' => true,
                'message' => 'Visit current states retrieved successfully.',
                'data' => $visitCurrentStates,
                'meta' => [
                    'total' => $visitCurrentStates->count(),
                    'filters' => $filters
                ],
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get all visit current states', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while retrieving visit current states.',
                'data' => [],
                'status' => 500
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createVisitCurrentState(array $data): array
    {
        try {
            // Validate required fields
            $requiredFields = ['visit_id', 'facility_id', 'patient_id', 'current_phase'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    return [
                        'success' => false,
                        'message' => "The {$field} field is required.",
                        'data' => null,
                        'status' => 422
                    ];
                }
            }
            
            // Validate phase
            if (!in_array($data['current_phase'], array_keys(\App\Models\VisitCurrentState::PHASES))) {
                return [
                    'success' => false,
                    'message' => 'Invalid current phase provided.',
                    'data' => null,
                    'status' => 422
                ];
            }
            
            // Set default values
            $data['materialized_at'] = now();
            $data['last_event_at'] = now();
            
            $visitCurrentState = $this->repository->create($data);
            
            return [
                'success' => true,
                'message' => 'Visit current state created successfully.',
                'data' => $visitCurrentState,
                'status' => 201
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create visit current state', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while creating the visit current state.',
                'data' => null,
                'status' => 500
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateVisitCurrentState(int $id, array $data): array
    {
        try {
            // Validate phase if provided
            if (isset($data['current_phase']) && !in_array($data['current_phase'], array_keys(\App\Models\VisitCurrentState::PHASES))) {
                return [
                    'success' => false,
                    'message' => 'Invalid current phase provided.',
                    'data' => null,
                    'status' => 422
                ];
            }
            
            $visitCurrentState = $this->repository->update($id, $data);
            
            return [
                'success' => true,
                'message' => 'Visit current state updated successfully.',
                'data' => $visitCurrentState,
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Failed to update visit current state', [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            $errorMessage = 'An error occurred while updating the visit current state.';
            
            if (strpos($e->getMessage(), 'not found') !== false) {
                $errorMessage = 'Visit current state not found.';
                $status = 404;
            } else {
                $status = 500;
            }
            
            return [
                'success' => false,
                'message' => $errorMessage,
                'data' => null,
                'status' => $status
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteVisitCurrentState(int $id): array
    {
        try {
            $deleted = $this->repository->delete($id);
            
            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Visit current state not found or could not be deleted.',
                    'data' => null,
                    'status' => 404
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Visit current state deleted successfully.',
                'data' => null,
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Failed to delete visit current state', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while deleting the visit current state.',
                'data' => null,
                'status' => 500
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getVisitCurrentStatesByFacility(int $facilityId, array $filters = []): array
    {
        try {
            $visitCurrentStates = $this->repository->getByFacility($facilityId, $filters);
            
            return [
                'success' => true,
                'message' => 'Visit current states retrieved successfully.',
                'data' => $visitCurrentStates,
                'meta' => [
                    'facility_id' => $facilityId,
                    'filters' => $filters,
                    'count' => $visitCurrentStates->count()
                ],
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get visit current states by facility', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while retrieving visit current states.',
                'data' => [],
                'status' => 500
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getVisitCurrentStatesByDepartment(int $departmentId): array
    {
        try {
            $visitCurrentStates = $this->repository->getByDepartment($departmentId);
            
            return [
                'success' => true,
                'message' => 'Visit current states retrieved successfully.',
                'data' => $visitCurrentStates,
                'meta' => [
                    'department_id' => $departmentId,
                    'count' => $visitCurrentStates->count()
                ],
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get visit current states by department', [
                'department_id' => $departmentId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while retrieving visit current states.',
                'data' => [],
                'status' => 500
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getVisitsWithCriticalAlerts(int $facilityId): array
    {
        try {
            $visitCurrentStates = $this->repository->getWithCriticalAlerts($facilityId);
            
            return [
                'success' => true,
                'message' => 'Visits with critical alerts retrieved successfully.',
                'data' => $visitCurrentStates,
                'meta' => [
                    'facility_id' => $facilityId,
                    'count' => $visitCurrentStates->count()
                ],
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get visits with critical alerts', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while retrieving visits with critical alerts.',
                'data' => [],
                'status' => 500
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getLongWaitingVisits(int $thresholdMinutes, ?int $facilityId = null): array
    {
        try {
            $visitCurrentStates = $this->repository->getLongWaitingVisits($thresholdMinutes, $facilityId);
            
            return [
                'success' => true,
                'message' => 'Long waiting visits retrieved successfully.',
                'data' => $visitCurrentStates,
                'meta' => [
                    'threshold_minutes' => $thresholdMinutes,
                    'facility_id' => $facilityId,
                    'count' => $visitCurrentStates->count()
                ],
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get long waiting visits', [
                'threshold_minutes' => $thresholdMinutes,
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while retrieving long waiting visits.',
                'data' => [],
                'status' => 500
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function processVisitEvent(int $visitId, array $eventData): array
    {
        try {
            // Validate required event data
            if (!isset($eventData['event_type'])) {
                return [
                    'success' => false,
                    'message' => 'Event type is required.',
                    'data' => null,
                    'status' => 422
                ];
            }
            
            // Process based on event type
            $processedData = $this->processEventData($eventData);
            $processedData['last_event_at'] = now();
            $processedData['last_event_id'] = $eventData['event_id'] ?? null;
            
            $visitCurrentState = $this->repository->updateFromEvent($visitId, $processedData);
            
            return [
                'success' => true,
                'message' => 'Visit event processed successfully.',
                'data' => $visitCurrentState,
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Failed to process visit event', [
                'visit_id' => $visitId,
                'event_data' => $eventData,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while processing the visit event.',
                'data' => null,
                'status' => 500
            ];
        }
    }

    /**
     * Process event data based on event type.
     *
     * @param array $eventData
     * @return array
     */
    private function processEventData(array $eventData): array
    {
        $processedData = [];
        $eventType = $eventData['event_type'];
        
        switch ($eventType) {
            case 'phase_change':
                $processedData['current_phase'] = $eventData['new_phase'];
                $processedData['current_phase_duration_minutes'] = 0;
                $processedData['waiting_since'] = in_array($eventData['new_phase'], ['waiting_triage', 'waiting_provider', 'awaiting_results']) 
                    ? now() 
                    : null;
                break;
                
            case 'department_change':
                $processedData['current_department_id'] = $eventData['department_id'];
                break;
                
            case 'staff_assignment':
                if (isset($eventData['staff_assigned_ids'])) {
                    $processedData['staff_assigned_ids'] = $eventData['staff_assigned_ids'];
                }
                if (isset($eventData['primary_provider_staff_id'])) {
                    $processedData['primary_provider_staff_id'] = $eventData['primary_provider_staff_id'];
                }
                if (isset($eventData['primary_nurse_staff_id'])) {
                    $processedData['primary_nurse_staff_id'] = $eventData['primary_nurse_staff_id'];
                }
                break;
                
            case 'vitals_recorded':
                $processedData['recent_vitals_last_reading'] = $eventData['vitals'];
                $processedData['vitals_last_recorded_at'] = now();
                break;
                
            case 'critical_alert':
                $processedData['critical_alerts'] = $eventData['alerts'];
                $processedData['has_critical_alerts'] = true;
                $processedData['acuity_score'] = $eventData['acuity_score'] ?? 5; // Default high acuity
                break;
                
            case 'order_placed':
                $processedData['active_orders'] = $eventData['orders'];
                $processedData['active_orders_count'] = count($eventData['orders']);
                break;
                
            case 'task_assigned':
                $processedData['pending_tasks'] = $eventData['tasks'];
                $processedData['pending_tasks_count'] = count($eventData['tasks']);
                break;
                
            case 'discharge':
                $processedData['current_phase'] = 'discharged';
                $processedData['waiting_since'] = null;
                $processedData['estimated_completion_time'] = now();
                break;
        }
        
        return $processedData;
    }

    /**
     * {@inheritdoc}
     */
    public function getDashboardStats(int $facilityId): array
    {
        try {
            $stats = $this->repository->getDashboardStats($facilityId);
            
            return [
                'success' => true,
                'message' => 'Dashboard statistics retrieved successfully.',
                'data' => $stats,
                'meta' => [
                    'facility_id' => $facilityId,
                    'generated_at' => now()
                ],
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get dashboard statistics', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while retrieving dashboard statistics.',
                'data' => [],
                'status' => 500
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateWaitTimes(): array
    {
        try {
            DB::beginTransaction();
            
            // Get all active visits (not discharged)
            $activeVisits = $this->repository->all()->filter(function ($visit) {
                return $visit->current_phase !== 'discharged' && $visit->waiting_since;
            });
            
            $updatedCount = 0;
            
            foreach ($activeVisits as $visit) {
                $waitMinutes = $visit->calculateCurrentWaitTime();
                if ($waitMinutes !== null) {
                    $this->repository->update($visit->id, [
                        'total_wait_minutes' => $visit->total_wait_minutes + $waitMinutes,
                        'current_phase_duration_minutes' => $visit->current_phase_duration_minutes + $waitMinutes
                    ]);
                    $updatedCount++;
                }
            }
            
            DB::commit();
            
            Log::info('Wait times updated successfully', [
                'updated_count' => $updatedCount,
                'total_active' => $activeVisits->count()
            ]);
            
            return [
                'success' => true,
                'message' => 'Wait times updated successfully.',
                'data' => [
                    'updated_count' => $updatedCount,
                    'total_active_visits' => $activeVisits->count()
                ],
                'status' => 200
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to update wait times', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while updating wait times.',
                'data' => null,
                'status' => 500
            ];
        }
    }
}