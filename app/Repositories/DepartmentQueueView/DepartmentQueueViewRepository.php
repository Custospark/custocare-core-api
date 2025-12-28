<?php

namespace App\Repositories\DepartmentQueueView;

use App\Models\DepartmentQueueView;
use App\Repositories\Contracts\DepartmentQueueViewRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DepartmentQueueViewRepository implements DepartmentQueueViewRepositoryInterface
{
    /**
     * Constructor with dependency injection
     */
    public function __construct(
        protected DepartmentQueueView $model
    ) {}

    /**
     * Find department queue view by ID
     */
    public function findById(int $id): ?DepartmentQueueView
    {
        try {
            return $this->model->with(['facility', 'department'])->find($id);
        } catch (\Exception $e) {
            Log::error('Failed to find department queue view by ID', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Find by department and queue type
     */
    public function findByDepartmentAndType(int $departmentId, string $queueType): ?DepartmentQueueView
    {
        try {
            return $this->model
                ->where('department_id', $departmentId)
                ->where('queue_type', $queueType)
                ->with(['facility', 'department'])
                ->first();
        } catch (\Exception $e) {
            Log::error('Failed to find department queue view by department and type', [
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
    public function getByFacilityId(int $facilityId, array $filters = []): Collection
    {
        try {
            $query = $this->model
                ->where('facility_id', $facilityId)
                ->with(['facility', 'department']);

            // Apply filters
            if (!empty($filters['queue_type'])) {
                $query->where('queue_type', $filters['queue_type']);
            }

            if (!empty($filters['capacity_status'])) {
                $query->where('capacity_status', $filters['capacity_status']);
            }

            if (!empty($filters['current'])) {
                $query->current();
            }

            if (!empty($filters['date_from'])) {
                $query->where('snapshot_at', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->where('snapshot_at', '<=', $filters['date_to']);
            }

            return $query->orderBy('snapshot_at', 'desc')->get();
        } catch (\Exception $e) {
            Log::error('Failed to get queue views by facility ID', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            return collect();
        }
    }

    /**
     * Get paginated queue views for a department
     */
    public function getPaginatedByDepartment(int $departmentId, int $perPage = 20): LengthAwarePaginator
    {
        try {
            return $this->model
                ->where('department_id', $departmentId)
                ->with(['facility', 'department'])
                ->orderBy('snapshot_at', 'desc')
                ->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to get paginated queue views by department', [
                'department_id' => $departmentId,
                'error' => $e->getMessage()
            ]);
            return new LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * Get critical queue views across facility
     */
    public function getCriticalQueues(int $facilityId): Collection
    {
        try {
            return $this->model
                ->where('facility_id', $facilityId)
                ->whereIn('capacity_status', ['critical', 'at_capacity'])
                ->orWhere('longest_wait_minutes', '>', 120)
                ->with(['facility', 'department'])
                ->current()
                ->orderBy('capacity_percentage', 'desc')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get critical queues', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            return collect();
        }
    }

    /**
     * Get current snapshots (last 30 seconds)
     */
    public function getCurrentSnapshots(array $departmentIds = []): Collection
    {
        try {
            $query = $this->model
                ->with(['facility', 'department'])
                ->current();

            if (!empty($departmentIds)) {
                $query->whereIn('department_id', $departmentIds);
            }

            return $query->orderBy('snapshot_at', 'desc')->get();
        } catch (\Exception $e) {
            Log::error('Failed to get current snapshots', [
                'department_ids' => $departmentIds,
                'error' => $e->getMessage()
            ]);
            return collect();
        }
    }

    /**
     * Create a new department queue view
     */
    public function create(array $data): DepartmentQueueView
    {
        try {
            return $this->model->create($data);
        } catch (\Exception $e) {
            Log::error('Failed to create department queue view', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to create department queue view', 0, $e);
        }
    }

    /**
     * Update department queue view
     */
    public function update(DepartmentQueueView $queueView, array $data): bool
    {
        try {
            return $queueView->update($data);
        } catch (\Exception $e) {
            Log::error('Failed to update department queue view', [
                'queue_view_id' => $queueView->id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Delete department queue view
     */
    public function delete(DepartmentQueueView $queueView): bool
    {
        try {
            return $queueView->delete();
        } catch (\Exception $e) {
            Log::error('Failed to delete department queue view', [
                'queue_view_id' => $queueView->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Batch update multiple queue views
     */
    public function batchUpdate(array $updates): bool
    {
        DB::beginTransaction();
        
        try {
            foreach ($updates as $update) {
                if (!isset($update['department_id']) || !isset($update['queue_type'])) {
                    throw new \InvalidArgumentException('Missing required fields for batch update');
                }

                $this->model->updateOrCreate(
                    [
                        'department_id' => $update['department_id'],
                        'queue_type' => $update['queue_type']
                    ],
                    $update
                );
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to batch update department queue views', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Get queue statistics for dashboard
     */
    public function getDashboardStatistics(int $facilityId): array
    {
        try {
            return [
                'total_patients_waiting' => $this->model
                    ->where('facility_id', $facilityId)
                    ->current()
                    ->sum('patients_waiting_count'),
                
                'total_patients_in_treatment' => $this->model
                    ->where('facility_id', $facilityId)
                    ->current()
                    ->sum('patients_in_treatment_count'),
                
                'critical_departments_count' => $this->model
                    ->where('facility_id', $facilityId)
                    ->whereIn('capacity_status', ['critical', 'at_capacity'])
                    ->current()
                    ->count(),
                
                'average_wait_time' => $this->model
                    ->where('facility_id', $facilityId)
                    ->current()
                    ->average('average_wait_minutes'),
                
                'by_queue_type' => $this->model
                    ->where('facility_id', $facilityId)
                    ->current()
                    ->select('queue_type', DB::raw('SUM(patients_waiting_count) as total_waiting'))
                    ->groupBy('queue_type')
                    ->pluck('total_waiting', 'queue_type')
                    ->toArray(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get dashboard statistics', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get wait time trends for a department
     */
    public function getWaitTimeTrends(int $departmentId, string $queueType, int $hours = 24): Collection
    {
        try {
            return $this->model
                ->where('department_id', $departmentId)
                ->where('queue_type', $queueType)
                ->where('snapshot_at', '>=', now()->subHours($hours))
                ->select([
                    'snapshot_at',
                    'average_wait_minutes',
                    'patients_waiting_count',
                    'capacity_percentage'
                ])
                ->orderBy('snapshot_at', 'asc')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get wait time trends', [
                'department_id' => $departmentId,
                'queue_type' => $queueType,
                'error' => $e->getMessage()
            ]);
            return collect();
        }
    }
}