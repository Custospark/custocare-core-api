<?php

namespace App\Repositories\VisitCurrentState;

use App\Models\VisitCurrentState;
use App\Repositories\Contracts\VisitCurrentStateRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VisitCurrentStateRepository implements VisitCurrentStateRepositoryInterface
{
    /**
     * @var VisitCurrentState
     */
    protected $model;

    /**
     * VisitCurrentStateRepository constructor.
     *
     * @param VisitCurrentState $model
     */
    public function __construct(VisitCurrentState $model)
    {
        $this->model = $model;
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?VisitCurrentState
    {
        try {
            return $this->model->find($id);
        } catch (\Exception $e) {
            Log::error('Failed to find visit current state by ID', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findByVisitId(int $visitId): ?VisitCurrentState
    {
        try {
            return $this->model->where('visit_id', $visitId)->first();
        } catch (\Exception $e) {
            Log::error('Failed to find visit current state by visit ID', [
                'visit_id' => $visitId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function all(): Collection
    {
        try {
            return $this->model->all();
        } catch (\Exception $e) {
            Log::error('Failed to retrieve all visit current states', [
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        try {
            return $this->model->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to paginate visit current states', [
                'per_page' => $perPage,
                'error' => $e->getMessage()
            ]);
            return new LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): VisitCurrentState
    {
        try {
            DB::beginTransaction();
            
            $visitCurrentState = $this->model->create($data);
            
            DB::commit();
            
            Log::info('Visit current state created successfully', [
                'id' => $visitCurrentState->id,
                'visit_id' => $visitCurrentState->visit_id
            ]);
            
            return $visitCurrentState;
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create visit current state', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function update(int $id, array $data): VisitCurrentState
    {
        try {
            DB::beginTransaction();
            
            $visitCurrentState = $this->findById($id);
            
            if (!$visitCurrentState) {
                throw new \Exception("Visit current state not found with ID: {$id}");
            }
            
            $visitCurrentState->update($data);
            $visitCurrentState->refresh();
            
            DB::commit();
            
            Log::info('Visit current state updated successfully', [
                'id' => $id,
                'visit_id' => $visitCurrentState->visit_id
            ]);
            
            return $visitCurrentState;
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to update visit current state', [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(int $id): bool
    {
        try {
            DB::beginTransaction();
            
            $visitCurrentState = $this->findById($id);
            
            if (!$visitCurrentState) {
                throw new \Exception("Visit current state not found with ID: {$id}");
            }
            
            $deleted = $visitCurrentState->delete();
            
            DB::commit();
            
            if ($deleted) {
                Log::info('Visit current state deleted successfully', ['id' => $id]);
            }
            
            return $deleted;
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to delete visit current state', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getByFacility(int $facilityId, array $filters = []): Collection
    {
        try {
            $query = $this->model->where('facility_id', $facilityId);
            
            // Apply filters
            if (!empty($filters['phase'])) {
                $query->where('current_phase', $filters['phase']);
            }
            
            if (!empty($filters['department_id'])) {
                $query->where('current_department_id', $filters['department_id']);
            }
            
            if (isset($filters['has_critical_alerts'])) {
                $query->where('has_critical_alerts', (bool)$filters['has_critical_alerts']);
            }
            
            if (!empty($filters['acuity_min'])) {
                $query->where('acuity_score', '>=', $filters['acuity_min']);
            }
            
            // Order by priority (critical alerts first, then acuity score, then wait time)
            $query->orderBy('has_critical_alerts', 'desc')
                  ->orderBy('acuity_score', 'desc')
                  ->orderBy('waiting_since', 'asc');
            
            return $query->get();
        } catch (\Exception $e) {
            Log::error('Failed to get visit current states by facility', [
                'facility_id' => $facilityId,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getByDepartment(int $departmentId): Collection
    {
        try {
            return $this->model->where('current_department_id', $departmentId)
                ->orderBy('acuity_score', 'desc')
                ->orderBy('waiting_since', 'asc')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get visit current states by department', [
                'department_id' => $departmentId,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getByPhase(string $phase): Collection
    {
        try {
            return $this->model->where('current_phase', $phase)
                ->orderBy('has_critical_alerts', 'desc')
                ->orderBy('acuity_score', 'desc')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get visit current states by phase', [
                'phase' => $phase,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getWithCriticalAlerts(int $facilityId): Collection
    {
        try {
            return $this->model->where('facility_id', $facilityId)
                ->where('has_critical_alerts', true)
                ->orderBy('acuity_score', 'desc')
                ->orderBy('waiting_since', 'asc')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get visits with critical alerts', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getLongWaitingVisits(int $thresholdMinutes, ?int $facilityId = null): Collection
    {
        try {
            $query = $this->model->whereNotNull('waiting_since')
                ->where('waiting_since', '<=', now()->subMinutes($thresholdMinutes))
                ->where('current_phase', '!=', 'discharged');
            
            if ($facilityId) {
                $query->where('facility_id', $facilityId);
            }
            
            return $query->orderBy('waiting_since', 'asc')->get();
        } catch (\Exception $e) {
            Log::error('Failed to get long waiting visits', [
                'threshold_minutes' => $thresholdMinutes,
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateFromEvent(int $visitId, array $eventData): VisitCurrentState
    {
        try {
            DB::beginTransaction();
            
            $visitCurrentState = $this->findByVisitId($visitId);
            
            if (!$visitCurrentState) {
                // Create new record if it doesn't exist
                $eventData['visit_id'] = $visitId;
                $eventData['materialized_at'] = now();
                $visitCurrentState = $this->create($eventData);
            } else {
                // Update existing record
                $eventData['materialized_at'] = now();
                $visitCurrentState = $this->update($visitCurrentState->id, $eventData);
            }
            
            DB::commit();
            
            Log::info('Visit current state updated from CDC event', [
                'visit_id' => $visitId,
                'event_type' => $eventData['event_type'] ?? 'unknown'
            ]);
            
            return $visitCurrentState;
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to update visit current state from event', [
                'visit_id' => $visitId,
                'event_data' => $eventData,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDashboardStats(int $facilityId): array
    {
        try {
            $stats = $this->model->where('facility_id', $facilityId)
                ->selectRaw('
                    COUNT(*) as total_visits,
                    SUM(CASE WHEN has_critical_alerts = true THEN 1 ELSE 0 END) as critical_alerts_count,
                    AVG(total_wait_minutes) as avg_wait_time,
                    COUNT(CASE WHEN waiting_since IS NOT NULL THEN 1 END) as currently_waiting,
                    COUNT(CASE WHEN current_phase = "discharged" THEN 1 END) as discharged_today,
                    JSON_OBJECTAGG(
                        current_phase,
                        COUNT(*)
                    ) as phase_distribution
                ')
                ->first()
                ->toArray();
            
            // Calculate average wait time for waiting patients
            $waitingStats = $this->model->where('facility_id', $facilityId)
                ->whereNotNull('waiting_since')
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, waiting_since, NOW())) as current_avg_wait')
                ->first();
            
            $stats['current_avg_wait'] = $waitingStats->current_avg_wait ?? 0;
            
            return $stats;
        } catch (\Exception $e) {
            Log::error('Failed to get dashboard statistics', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'total_visits' => 0,
                'critical_alerts_count' => 0,
                'avg_wait_time' => 0,
                'currently_waiting' => 0,
                'discharged_today' => 0,
                'phase_distribution' => [],
                'current_avg_wait' => 0,
            ];
        }
    }
}