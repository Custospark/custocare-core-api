<?php

namespace App\Services\VisitRoute;

use App\Services\Contracts\VisitRouteServiceInterface;
use App\Repositories\Contracts\VisitRouteRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class VisitRouteService implements VisitRouteServiceInterface
{
    /**
     * Repository instance.
     */
    protected VisitRouteRepositoryInterface $repository;

    /**
     * Constructor with dependency injection.
     */
    public function __construct(VisitRouteRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get all visit routes with pagination.
     */
    public function getAllRoutes(array $filters = [], array $with = [], int $perPage = 15): array
    {
        try {
            $routes = $this->repository->all($filters, $with, $perPage);
            
            return [
                'success' => true,
                'data' => $routes,
                'message' => 'Visit routes retrieved successfully.',
                'meta' => [
                    'total' => $routes->total(),
                    'per_page' => $routes->perPage(),
                    'current_page' => $routes->currentPage(),
                    'last_page' => $routes->lastPage(),
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve visit routes', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Unable to retrieve visit routes at this time.',
                'errors' => ['system' => 'System error occurred.'],
                'data' => []
            ];
        }
    }

    /**
     * Get a specific visit route by ID.
     */
    public function getRouteById(int $id, array $with = []): array
    {
        try {
            $route = $this->repository->find($id, $with);
            
            if (!$route) {
                return [
                    'success' => false,
                    'message' => 'Visit route not found.',
                    'errors' => ['id' => 'The specified visit route does not exist.'],
                    'data' => null
                ];
            }
            
            return [
                'success' => true,
                'data' => $route,
                'message' => 'Visit route retrieved successfully.'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve visit route', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Unable to retrieve visit route at this time.',
                'errors' => ['system' => 'System error occurred.'],
                'data' => null
            ];
        }
    }

    /**
     * Create a new visit route.
     */
    public function createRoute(array $data): array
    {
        try {
            // Validate business logic
            $validationResult = $this->validateRouteCreation($data);
            if (!$validationResult['success']) {
                return $validationResult;
            }
            
            DB::beginTransaction();
            
            $route = $this->repository->create($data);
            
            DB::commit();
            
            return [
                'success' => true,
                'data' => $route,
                'message' => 'Visit route created successfully.'
            ];
        } catch (ValidationException $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'message' => 'Validation failed for visit route creation.',
                'errors' => $e->errors(),
                'data' => null
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create visit route', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Unable to create visit route at this time.',
                'errors' => ['system' => 'System error occurred.'],
                'data' => null
            ];
        }
    }

    /**
     * Update an existing visit route.
     */
    public function updateRoute(int $id, array $data): array
    {
        try {
            // Check if route exists
            $existingRoute = $this->repository->find($id);
            if (!$existingRoute) {
                return [
                    'success' => false,
                    'message' => 'Visit route not found.',
                    'errors' => ['id' => 'The specified visit route does not exist.'],
                    'data' => null
                ];
            }
            
            // Prevent updates to completed routes unless explicitly allowed
            if ($existingRoute->isComplete() && !($data['force_update'] ?? false)) {
                return [
                    'success' => false,
                    'message' => 'Cannot update a completed visit route.',
                    'errors' => ['status' => 'This route is already completed.'],
                    'data' => null
                ];
            }
            
            DB::beginTransaction();
            
            $route = $this->repository->update($id, $data);
            
            DB::commit();
            
            return [
                'success' => true,
                'data' => $route,
                'message' => 'Visit route updated successfully.'
            ];
        } catch (ValidationException $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'message' => 'Validation failed for visit route update.',
                'errors' => $e->errors(),
                'data' => null
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to update visit route', [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Unable to update visit route at this time.',
                'errors' => ['system' => 'System error occurred.'],
                'data' => null
            ];
        }
    }

    /**
     * Delete a visit route.
     */
    public function deleteRoute(int $id): array
    {
        try {
            // Check if route exists
            $existingRoute = $this->repository->find($id);
            if (!$existingRoute) {
                return [
                    'success' => false,
                    'message' => 'Visit route not found.',
                    'errors' => ['id' => 'The specified visit route does not exist.']
                ];
            }
            
            // Prevent deletion of active routes
            if ($existingRoute->isActive()) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete an active visit route.',
                    'errors' => ['status' => 'This route is currently active.']
                ];
            }
            
            DB::beginTransaction();
            
            $deleted = $this->repository->delete($id);
            
            DB::commit();
            
            if ($deleted) {
                return [
                    'success' => true,
                    'message' => 'Visit route deleted successfully.',
                    'data' => null
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to delete visit route.',
                'errors' => ['system' => 'Delete operation failed.'],
                'data' => null
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to delete visit route', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Unable to delete visit route at this time.',
                'errors' => ['system' => 'System error occurred.'],
                'data' => null
            ];
        }
    }

    /**
     * Get routes for a specific visit.
     */
    public function getRoutesForVisit(int $visitId, array $filters = []): array
    {
        try {
            $routes = $this->repository->findByVisit($visitId, $filters);
            
            return [
                'success' => true,
                'data' => $routes,
                'message' => 'Visit routes retrieved successfully.',
                'meta' => [
                    'visit_id' => $visitId,
                    'count' => $routes->count()
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve routes for visit', [
                'visit_id' => $visitId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Unable to retrieve visit routes at this time.',
                'errors' => ['system' => 'System error occurred.'],
                'data' => []
            ];
        }
    }

    /**
     * Get active routes for a facility.
     */
    public function getActiveRoutesForFacility(int $facilityId): array
    {
        try {
            $routes = $this->repository->getActiveRoutes($facilityId);
            
            return [
                'success' => true,
                'data' => $routes,
                'message' => 'Active routes retrieved successfully.',
                'meta' => [
                    'facility_id' => $facilityId,
                    'count' => $routes->count()
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve active routes', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Unable to retrieve active routes at this time.',
                'errors' => ['system' => 'System error occurred.'],
                'data' => []
            ];
        }
    }

    /**
     * Get pending handoffs for a facility.
     */
    public function getPendingHandoffsForFacility(int $facilityId): array
    {
        try {
            $routes = $this->repository->getPendingHandoffs($facilityId);
            
            return [
                'success' => true,
                'data' => $routes,
                'message' => 'Pending handoffs retrieved successfully.',
                'meta' => [
                    'facility_id' => $facilityId,
                    'count' => $routes->count()
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve pending handoffs', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Unable to retrieve pending handoffs at this time.',
                'errors' => ['system' => 'System error occurred.'],
                'data' => []
            ];
        }
    }

    /**
     * Acknowledge a handoff.
     */
    public function acknowledgeHandoff(int $routeId, int $staffId): array
    {
        try {
            $route = $this->repository->find($routeId);
            
            if (!$route) {
                return [
                    'success' => false,
                    'message' => 'Visit route not found.',
                    'errors' => ['id' => 'The specified visit route does not exist.'],
                    'data' => null
                ];
            }
            
            if ($route->handoff_acknowledged) {
                return [
                    'success' => false,
                    'message' => 'Handoff already acknowledged.',
                    'errors' => ['handoff' => 'This handoff has already been acknowledged.'],
                    'data' => null
                ];
            }
            
            DB::beginTransaction();
            
            $route->acknowledgeHandoff($staffId);
            
            DB::commit();
            
            return [
                'success' => true,
                'data' => $route->fresh(),
                'message' => 'Handoff acknowledged successfully.'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to acknowledge handoff', [
                'route_id' => $routeId,
                'staff_id' => $staffId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Unable to acknowledge handoff at this time.',
                'errors' => ['system' => 'System error occurred.'],
                'data' => null
            ];
        }
    }

    /**
     * Mark a route as arrived.
     */
    public function markRouteAsArrived(int $routeId): array
    {
        try {
            $route = $this->repository->find($routeId);
            
            if (!$route) {
                return [
                    'success' => false,
                    'message' => 'Visit route not found.',
                    'errors' => ['id' => 'The specified visit route does not exist.'],
                    'data' => null
                ];
            }
            
            if ($route->arrived_at_department) {
                return [
                    'success' => false,
                    'message' => 'Route already marked as arrived.',
                    'errors' => ['status' => 'This route has already been marked as arrived.'],
                    'data' => null
                ];
            }
            
            DB::beginTransaction();
            
            $route->markAsArrived();
            
            DB::commit();
            
            return [
                'success' => true,
                'data' => $route->fresh(),
                'message' => 'Route marked as arrived successfully.'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to mark route as arrived', [
                'route_id' => $routeId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Unable to mark route as arrived at this time.',
                'errors' => ['system' => 'System error occurred.'],
                'data' => null
            ];
        }
    }

    /**
     * Mark a route as departed.
     */
    public function markRouteAsDeparted(int $routeId): array
    {
        try {
            $route = $this->repository->find($routeId);
            
            if (!$route) {
                return [
                    'success' => false,
                    'message' => 'Visit route not found.',
                    'errors' => ['id' => 'The specified visit route does not exist.'],
                    'data' => null
                ];
            }
            
            if (!$route->arrived_at_department) {
                return [
                    'success' => false,
                    'message' => 'Route must be marked as arrived first.',
                    'errors' => ['status' => 'Cannot mark as departed without arrival time.'],
                    'data' => null
                ];
            }
            
            if ($route->departed_department) {
                return [
                    'success' => false,
                    'message' => 'Route already marked as departed.',
                    'errors' => ['status' => 'This route has already been marked as departed.'],
                    'data' => null
                ];
            }
            
            DB::beginTransaction();
            
            $route->markAsDeparted();
            
            DB::commit();
            
            return [
                'success' => true,
                'data' => $route->fresh(),
                'message' => 'Route marked as departed successfully.'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to mark route as departed', [
                'route_id' => $routeId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Unable to mark route as departed at this time.',
                'errors' => ['system' => 'System error occurred.'],
                'data' => null
            ];
        }
    }

    /**
     * Get department throughput statistics.
     */
    public function getDepartmentThroughput(int $departmentId, string $startDate, string $endDate): array
    {
        try {
            $throughput = $this->repository->getDepartmentThroughput($departmentId, $startDate, $endDate);
            
            return [
                'success' => true,
                'data' => $throughput,
                'message' => 'Department throughput statistics retrieved successfully.'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get department throughput', [
                'department_id' => $departmentId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Unable to retrieve department throughput at this time.',
                'errors' => ['system' => 'System error occurred.'],
                'data' => null
            ];
        }
    }

    /**
     * Get wait time analytics.
     */
    public function getWaitTimeAnalytics(int $facilityId, string $startDate, string $endDate): array
    {
        try {
            $analytics = $this->repository->getAverageWaitTimes($facilityId, $startDate, $endDate);
            
            return [
                'success' => true,
                'data' => $analytics,
                'message' => 'Wait time analytics retrieved successfully.'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get wait time analytics', [
                'facility_id' => $facilityId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Unable to retrieve wait time analytics at this time.',
                'errors' => ['system' => 'System error occurred.'],
                'data' => null
            ];
        }
    }

    /**
     * Create multiple routes in bulk.
     */
    public function bulkCreateRoutes(array $routesData): array
    {
        try {
            $validationErrors = [];
            $validRoutes = [];
            
            // Validate each route
            foreach ($routesData as $index => $routeData) {
                $validationResult = $this->validateRouteCreation($routeData);
                if (!$validationResult['success']) {
                    $validationErrors[$index] = $validationResult['errors'];
                } else {
                    $validRoutes[] = $routeData;
                }
            }
            
            if (!empty($validationErrors)) {
                return [
                    'success' => false,
                    'message' => 'Some routes failed validation.',
                    'errors' => $validationErrors,
                    'data' => null
                ];
            }
            
            DB::beginTransaction();
            
            $createdRoutes = $this->repository->bulkCreate($validRoutes);
            
            DB::commit();
            
            return [
                'success' => true,
                'data' => $createdRoutes,
                'message' => 'Routes created successfully in bulk.',
                'meta' => [
                    'created_count' => $createdRoutes->count()
                ]
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to bulk create routes', [
                'route_count' => count($routesData),
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Unable to create routes in bulk at this time.',
                'errors' => ['system' => 'System error occurred.'],
                'data' => null
            ];
        }
    }

    /**
     * Validate routing logic before creation.
     */
    public function validateRouteCreation(array $data): array
    {
        try {
            $validator = Validator::make($data, [
                'facility_id' => 'required|integer|exists:facilities,id',
                'visit_id' => 'required|integer|exists:visits,id',
                'from_department_id' => 'nullable|integer|exists:departments,id',
                'to_department_id' => 'required|integer|exists:departments,id',
                'routing_reason' => 'required|in:initial_assignment,specialist_consultation,diagnostic_imaging,laboratory_tests,surgical_procedure,capacity_management,escalation_of_care,de_escalation_of_care,patient_request,admission_to_inpatient,discharge_processing',
                'routing_notes' => 'nullable|string|max:2000',
                'routing_staff_id' => 'nullable|integer|exists:users,id',
                'queue_position_at_move' => 'nullable|integer|min:1',
                'estimated_wait_minutes' => 'nullable|integer|min:0',
                'actual_wait_minutes' => 'nullable|integer|min:0',
                'routed_at' => 'required|date',
                'arrived_at_department' => 'nullable|date|after_or_equal:routed_at',
                'departed_department' => 'nullable|date|after_or_equal:arrived_at_department',
                'actual_transfer_duration_minutes' => 'nullable|integer|min:0',
                'handoff_summary' => 'nullable|string|max:2000',
                'sending_staff_id' => 'nullable|integer|exists:users,id',
                'receiving_staff_id' => 'nullable|integer|exists:users,id',
                'handoff_acknowledged' => 'boolean',
                'handoff_acknowledged_at' => 'nullable|date|required_if:handoff_acknowledged,true',
                'transport_method' => 'nullable|in:ambulatory,wheelchair,stretcher,bed,ambulance',
                'requires_escort' => 'boolean',
                'metadata' => 'nullable|array'
            ]);
            
            if ($validator->fails()) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors()->toArray(),
                    'data' => null
                ];
            }
            
            // Additional business logic validation
            if (isset($data['from_department_id']) && $data['from_department_id'] == $data['to_department_id']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => [
                        'to_department_id' => 'Destination department must be different from source department.'
                    ],
                    'data' => null
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Validation passed.',
                'errors' => [],
                'data' => null
            ];
        } catch (\Exception $e) {
            Log::error('Route validation error', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            
            return [
                'success' => false,
                'message' => 'Validation error occurred.',
                'errors' => ['system' => 'Validation system error.'],
                'data' => null
            ];
        }
    }
}