<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\VisitRoute\StoreVisitRouteRequest;
use App\Http\Requests\VisitRoute\UpdateVisitRouteRequest;
use App\Http\Resources\VisitRouteResource;
use App\Services\Contracts\VisitRouteServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VisitRouteController extends Controller
{
    /**
     * Service instance.
     */
    protected VisitRouteServiceInterface $service;

    /**
     * Constructor with dependency injection.
     */
    public function __construct(VisitRouteServiceInterface $service)
    {
        $this->service = $service;
        
        // Apply middleware
        // $this->middleware('auth:api');
        // $this->middleware('scope:visit-routes-read')->only(['index', 'show']);
        // $this->middleware('scope:visit-routes-write')->only(['store', 'update', 'destroy']);
    }

    /**
     * Display a listing of visit routes.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'facility_id',
                'visit_id',
                'department_id',
                'to_department_id',
                'from_department_id',
                'routing_reason',
                'handoff_acknowledged',
                'requires_escort',
                'transport_method',
                'date_from',
                'date_to',
                'active'
            ]);
            
            $with = $request->get('with', []);
            $perPage = $request->get('per_page', 15);
            
            $result = $this->service->getAllRoutes($filters, $with, $perPage);
            
            if (!$result['success']) {
                return response()->json($result, 500);
            }
            
            return VisitRouteResource::collection($result['data'])
                ->additional([
                    'success' => true,
                    'message' => $result['message'],
                    'meta' => $result['meta'] ?? []
                ])
                ->response()
                ->setStatusCode(200);
                
        } catch (\Exception $e) {
            Log::error('VisitRouteController@index error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'errors' => ['system' => 'Internal server error.'],
                'data' => null
            ], 500);
        }
    }

    /**
     * Store a newly created visit route.
     */
    public function store(StoreVisitRouteRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            
            $result = $this->service->createRoute($validatedData);
            
            if (!$result['success']) {
                return response()->json($result, 422);
            }
            
            return (new VisitRouteResource($result['data']))
                ->additional([
                    'success' => true,
                    'message' => $result['message']
                ])
                ->response()
                ->setStatusCode(201);
                
        } catch (\Exception $e) {
            Log::error('VisitRouteController@store error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while creating visit route.',
                'errors' => ['system' => 'Internal server error.'],
                'data' => null
            ], 500);
        }
    }

    /**
     * Display the specified visit route.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $with = $request->get('with', []);
            
            $result = $this->service->getRouteById($id, $with);
            
            if (!$result['success']) {
                return response()->json($result, 404);
            }
            
            return (new VisitRouteResource($result['data']))
                ->additional([
                    'success' => true,
                    'message' => $result['message']
                ])
                ->response()
                ->setStatusCode(200);
                
        } catch (\Exception $e) {
            Log::error('VisitRouteController@show error', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'errors' => ['system' => 'Internal server error.'],
                'data' => null
            ], 500);
        }
    }

    /**
     * Update the specified visit route.
     */
    public function update(UpdateVisitRouteRequest $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            
            $result = $this->service->updateRoute($id, $validatedData);
            
            if (!$result['success']) {
                $statusCode = isset($result['errors']['id']) ? 404 : 422;
                return response()->json($result, $statusCode);
            }
            
            return (new VisitRouteResource($result['data']))
                ->additional([
                    'success' => true,
                    'message' => $result['message']
                ])
                ->response()
                ->setStatusCode(200);
                
        } catch (\Exception $e) {
            Log::error('VisitRouteController@update error', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while updating visit route.',
                'errors' => ['system' => 'Internal server error.'],
                'data' => null
            ], 500);
        }
    }

    /**
     * Remove the specified visit route.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $result = $this->service->deleteRoute($id);
            
            if (!$result['success']) {
                $statusCode = isset($result['errors']['id']) ? 404 : 422;
                return response()->json($result, $statusCode);
            }
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => null
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('VisitRouteController@destroy error', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while deleting visit route.',
                'errors' => ['system' => 'Internal server error.'],
                'data' => null
            ], 500);
        }
    }

    /**
     * Get routes for a specific visit.
     */
    public function getVisitRoutes(Request $request, int $visitId): JsonResponse
    {
        try {
            $this->authorize('viewAny', \App\Models\VisitRoute::class);
            
            $filters = $request->only([
                'routing_reason',
                'handoff_acknowledged',
                'active'
            ]);
            
            $result = $this->service->getRoutesForVisit($visitId, $filters);
            
            if (!$result['success']) {
                return response()->json($result, 500);
            }
            
            return VisitRouteResource::collection($result['data'])
                ->additional([
                    'success' => true,
                    'message' => $result['message'],
                    'meta' => $result['meta'] ?? []
                ])
                ->response()
                ->setStatusCode(200);
                
        } catch (\Exception $e) {
            Log::error('VisitRouteController@getVisitRoutes error', [
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'errors' => ['system' => 'Internal server error.'],
                'data' => null
            ], 500);
        }
    }

    /**
     * Get active routes for a facility.
     */
    public function getActiveRoutes(Request $request, int $facilityId): JsonResponse
    {
        try {
            $this->authorize('viewAny', \App\Models\VisitRoute::class);
            
            $result = $this->service->getActiveRoutesForFacility($facilityId);
            
            if (!$result['success']) {
                return response()->json($result, 500);
            }
            
            return VisitRouteResource::collection($result['data'])
                ->additional([
                    'success' => true,
                    'message' => $result['message'],
                    'meta' => $result['meta'] ?? []
                ])
                ->response()
                ->setStatusCode(200);
                
        } catch (\Exception $e) {
            Log::error('VisitRouteController@getActiveRoutes error', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'errors' => ['system' => 'Internal server error.'],
                'data' => null
            ], 500);
        }
    }

    /**
     * Acknowledge a handoff.
     */
    public function acknowledgeHandoff(Request $request, int $id): JsonResponse
    {
        try {
            $this->authorize('update', \App\Models\VisitRoute::class);
            
            $request->validate([
                'staff_id' => 'required|integer|exists:users,id'
            ]);
            
            $staffId = $request->input('staff_id');
            
            $result = $this->service->acknowledgeHandoff($id, $staffId);
            
            if (!$result['success']) {
                $statusCode = isset($result['errors']['id']) ? 404 : 422;
                return response()->json($result, $statusCode);
            }
            
            return (new VisitRouteResource($result['data']))
                ->additional([
                    'success' => true,
                    'message' => $result['message']
                ])
                ->response()
                ->setStatusCode(200);
                
        } catch (\Exception $e) {
            Log::error('VisitRouteController@acknowledgeHandoff error', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while acknowledging handoff.',
                'errors' => ['system' => 'Internal server error.'],
                'data' => null
            ], 500);
        }
    }

    /**
     * Mark route as arrived.
     */
    public function markAsArrived(int $id): JsonResponse
    {
        try {
            $this->authorize('update', \App\Models\VisitRoute::class);
            
            $result = $this->service->markRouteAsArrived($id);
            
            if (!$result['success']) {
                $statusCode = isset($result['errors']['id']) ? 404 : 422;
                return response()->json($result, $statusCode);
            }
            
            return (new VisitRouteResource($result['data']))
                ->additional([
                    'success' => true,
                    'message' => $result['message']
                ])
                ->response()
                ->setStatusCode(200);
                
        } catch (\Exception $e) {
            Log::error('VisitRouteController@markAsArrived error', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while marking route as arrived.',
                'errors' => ['system' => 'Internal server error.'],
                'data' => null
            ], 500);
        }
    }

    /**
     * Mark route as departed.
     */
    public function markAsDeparted(int $id): JsonResponse
    {
        try {
            $this->authorize('update', \App\Models\VisitRoute::class);
            
            $result = $this->service->markRouteAsDeparted($id);
            
            if (!$result['success']) {
                $statusCode = isset($result['errors']['id']) ? 404 : 422;
                return response()->json($result, $statusCode);
            }
            
            return (new VisitRouteResource($result['data']))
                ->additional([
                    'success' => true,
                    'message' => $result['message']
                ])
                ->response()
                ->setStatusCode(200);
                
        } catch (\Exception $e) {
            Log::error('VisitRouteController@markAsDeparted error', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while marking route as departed.',
                'errors' => ['system' => 'Internal server error.'],
                'data' => null
            ], 500);
        }
    }

    /**
     * Get department throughput statistics.
     */
    public function getThroughput(Request $request, int $departmentId): JsonResponse
    {
        try {
            $this->authorize('viewAny', \App\Models\VisitRoute::class);
            
            $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date'
            ]);
            
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            
            $result = $this->service->getDepartmentThroughput($departmentId, $startDate, $endDate);
            
            if (!$result['success']) {
                return response()->json($result, 500);
            }
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data']
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('VisitRouteController@getThroughput error', [
                'department_id' => $departmentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'errors' => ['system' => 'Internal server error.'],
                'data' => null
            ], 500);
        }
    }
}