<?php

namespace App\Repositories\VisitRoute;

use App\Models\VisitRoute;
use App\Repositories\Contracts\VisitRouteRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VisitRouteRepository implements VisitRouteRepositoryInterface
{
    /**
     * Base query with common filters.
     */
    protected function baseQuery(array $filters = [], array $with = [])
    {
        $query = VisitRoute::query();

        // Eager load relationships
        if (!empty($with)) {
            $query->with($with);
        }

        // Apply filters
        if (isset($filters['facility_id'])) {
            $query->where('facility_id', $filters['facility_id']);
        }

        if (isset($filters['visit_id'])) {
            $query->where('visit_id', $filters['visit_id']);
        }

        if (isset($filters['department_id'])) {
            $query->where(function($q) use ($filters) {
                $q->where('from_department_id', $filters['department_id'])
                  ->orWhere('to_department_id', $filters['department_id']);
            });
        }

        if (isset($filters['to_department_id'])) {
            $query->where('to_department_id', $filters['to_department_id']);
        }

        if (isset($filters['from_department_id'])) {
            $query->where('from_department_id', $filters['from_department_id']);
        }

        if (isset($filters['routing_reason'])) {
            $query->where('routing_reason', $filters['routing_reason']);
        }

        if (isset($filters['handoff_acknowledged'])) {
            $query->where('handoff_acknowledged', $filters['handoff_acknowledged']);
        }

        if (isset($filters['requires_escort'])) {
            $query->where('requires_escort', $filters['requires_escort']);
        }

        if (isset($filters['transport_method'])) {
            $query->where('transport_method', $filters['transport_method']);
        }

        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            $query->whereBetween('routed_at', [
                $filters['date_from'],
                $filters['date_to']
            ]);
        }

        if (isset($filters['active'])) {
            $query->where(function($q) {
                $q->whereNull('arrived_at_department')
                  ->orWhereNull('departed_department');
            });
        }

        // Order by routed_at by default
        $query->orderBy('routed_at', 'desc');

        return $query;
    }

    /**
     * Get all visit routes with pagination.
     */
    public function all(array $filters = [], array $with = [], int $perPage = 15): LengthAwarePaginator
    {
        try {
            return $this->baseQuery($filters, $with)->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve visit routes', [
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);
            
            // Return empty paginator instead of throwing exception
            return new LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * Find a visit route by ID.
     */
    public function find(int $id, array $with = []): ?VisitRoute
    {
        try {
            $query = VisitRoute::query();
            
            if (!empty($with)) {
                $query->with($with);
            }
            
            return $query->find($id);
        } catch (\Exception $e) {
            Log::error('Failed to find visit route', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Find a visit route or fail.
     */
    public function findOrFail(int $id, array $with = []): VisitRoute
    {
        $route = $this->find($id, $with);
        
        if (!$route) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                "Visit route not found with ID: {$id}"
            );
        }
        
        return $route;
    }

    /**
     * Create a new visit route.
     */
    public function create(array $data): VisitRoute
    {
        try {
            DB::beginTransaction();
            
            $route = VisitRoute::create($data);
            
            DB::commit();
            
            return $route;
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create visit route', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Update an existing visit route.
     */
    public function update(int $id, array $data): VisitRoute
    {
        try {
            DB::beginTransaction();
            
            $route = $this->findOrFail($id);
            $route->update($data);
            
            DB::commit();
            
            return $route->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to update visit route', [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Delete a visit route.
     */
    public function delete(int $id): bool
    {
        try {
            DB::beginTransaction();
            
            $route = $this->findOrFail($id);
            $deleted = $route->delete();
            
            DB::commit();
            
            return $deleted;
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to delete visit route', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Get visit routes by visit ID.
     */
    public function findByVisit(int $visitId, array $filters = []): Collection
    {
        try {
            $filters['visit_id'] = $visitId;
            return $this->baseQuery($filters)->get();
        } catch (\Exception $e) {
            Log::error('Failed to find visit routes by visit', [
                'visit_id' => $visitId,
                'error' => $e->getMessage()
            ]);
            
            return new Collection();
        }
    }

    /**
     * Get visit routes by department ID.
     */
    public function findByDepartment(int $departmentId, array $filters = []): Collection
    {
        try {
            $filters['department_id'] = $departmentId;
            return $this->baseQuery($filters)->get();
        } catch (\Exception $e) {
            Log::error('Failed to find visit routes by department', [
                'department_id' => $departmentId,
                'error' => $e->getMessage()
            ]);
            
            return new Collection();
        }
    }

    /**
     * Get active routes for a facility.
     */
    public function getActiveRoutes(int $facilityId): Collection
    {
        try {
            return VisitRoute::where('facility_id', $facilityId)
                ->active()
                ->with(['visit', 'fromDepartment', 'toDepartment'])
                ->orderBy('routed_at')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get active routes', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            
            return new Collection();
        }
    }

    /**
     * Get pending handoff routes for a facility.
     */
    public function getPendingHandoffs(int $facilityId): Collection
    {
        try {
            return VisitRoute::where('facility_id', $facilityId)
                ->pendingHandoff()
                ->with(['visit', 'fromDepartment', 'toDepartment', 'sendingStaff'])
                ->orderBy('routed_at')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get pending handoffs', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            
            return new Collection();
        }
    }

    /**
     * Get routes within a date range.
     */
    public function getRoutesBetweenDates(int $facilityId, string $startDate, string $endDate): Collection
    {
        try {
            return VisitRoute::where('facility_id', $facilityId)
                ->betweenDates($startDate, $endDate)
                ->with(['visit', 'fromDepartment', 'toDepartment'])
                ->orderBy('routed_at')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get routes between dates', [
                'facility_id' => $facilityId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage()
            ]);
            
            return new Collection();
        }
    }

    /**
     * Get department throughput statistics.
     */
    public function getDepartmentThroughput(int $departmentId, string $startDate, string $endDate): array
    {
        try {
            $stats = VisitRoute::where('to_department_id', $departmentId)
                ->whereBetween('routed_at', [$startDate, $endDate])
                ->select([
                    DB::raw('COUNT(*) as total_routes'),
                    DB::raw('AVG(actual_wait_minutes) as avg_wait_time'),
                    DB::raw('AVG(actual_transfer_duration_minutes) as avg_transfer_duration'),
                    DB::raw('SUM(CASE WHEN handoff_acknowledged = 1 THEN 1 ELSE 0 END) as acknowledged_handoffs'),
                    DB::raw('routing_reason'),
                    DB::raw('DATE(routed_at) as route_date')
                ])
                ->groupBy('routing_reason', 'route_date')
                ->orderBy('route_date')
                ->get()
                ->toArray();

            return [
                'department_id' => $departmentId,
                'period' => ['start' => $startDate, 'end' => $endDate],
                'statistics' => $stats
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get department throughput', [
                'department_id' => $departmentId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage()
            ]);
            
            return [
                'department_id' => $departmentId,
                'period' => ['start' => $startDate, 'end' => $endDate],
                'statistics' => []
            ];
        }
    }

    /**
     * Get average wait times by routing reason.
     */
    public function getAverageWaitTimes(int $facilityId, string $startDate, string $endDate): array
    {
        try {
            $waitTimes = VisitRoute::where('facility_id', $facilityId)
                ->whereBetween('routed_at', [$startDate, $endDate])
                ->whereNotNull('actual_wait_minutes')
                ->select([
                    'routing_reason',
                    DB::raw('AVG(actual_wait_minutes) as avg_wait_time'),
                    DB::raw('MIN(actual_wait_minutes) as min_wait_time'),
                    DB::raw('MAX(actual_wait_minutes) as max_wait_time'),
                    DB::raw('COUNT(*) as total_routes')
                ])
                ->groupBy('routing_reason')
                ->get()
                ->toArray();

            return [
                'facility_id' => $facilityId,
                'period' => ['start' => $startDate, 'end' => $endDate],
                'wait_times' => $waitTimes
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get average wait times', [
                'facility_id' => $facilityId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage()
            ]);
            
            return [
                'facility_id' => $facilityId,
                'period' => ['start' => $startDate, 'end' => $endDate],
                'wait_times' => []
            ];
        }
    }

    /**
     * Bulk create visit routes.
     */
    public function bulkCreate(array $routes): Collection
    {
        try {
            DB::beginTransaction();
            
            $createdRoutes = new Collection();
            
            foreach ($routes as $routeData) {
                $route = VisitRoute::create($routeData);
                $createdRoutes->push($route);
            }
            
            DB::commit();
            
            return $createdRoutes;
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to bulk create visit routes', [
                'route_count' => count($routes),
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
}