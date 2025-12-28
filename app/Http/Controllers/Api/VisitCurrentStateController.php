<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\VisitCurrentState\StoreVisitCurrentStateRequest;
use App\Http\Requests\VisitCurrentState\UpdateVisitCurrentStateRequest;
use App\Http\Resources\VisitCurrentStateResource;
use App\Services\Contracts\VisitCurrentStateServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VisitCurrentStateController extends Controller
{
    /**
     * @var VisitCurrentStateServiceInterface
     */
    protected $visitCurrentStateService;

    /**
     * VisitCurrentStateController constructor.
     *
     * @param VisitCurrentStateServiceInterface $visitCurrentStateService
     */
    public function __construct(VisitCurrentStateServiceInterface $visitCurrentStateService)
    {
        $this->visitCurrentStateService = $visitCurrentStateService;
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $result = $this->visitCurrentStateService->getAllVisitCurrentStates($request->all());
            
            if (!$result['success']) {
                return response()->json($result, $result['status']);
            }
            
            $visitCurrentStates = $result['data'];
            $meta = $result['meta'] ?? [];
            
            return response()->json([
                'success' => true,
                'data' => VisitCurrentStateResource::collection($visitCurrentStates),
                'meta' => $meta,
                'message' => $result['message'],
                'status' => $result['status']
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve visit current states', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving visit current states.',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreVisitCurrentStateRequest $request
     * @return JsonResponse
     */
    public function store(StoreVisitCurrentStateRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $result = $this->visitCurrentStateService->createVisitCurrentState($validatedData);
            
            if (!$result['success']) {
                return response()->json($result, $result['status']);
            }
            
            return response()->json([
                'success' => true,
                'data' => new VisitCurrentStateResource($result['data']),
                'message' => $result['message'],
                'status' => $result['status']
            ], $result['status']);
        } catch (\Exception $e) {
            Log::error('Failed to create visit current state', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while creating the visit current state.',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $result = $this->visitCurrentStateService->getVisitCurrentState($id);
            
            if (!$result['success']) {
                return response()->json($result, $result['status']);
            }
            
            return response()->json([
                'success' => true,
                'data' => new VisitCurrentStateResource($result['data']),
                'message' => $result['message'],
                'status' => $result['status']
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve visit current state', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving the visit current state.',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateVisitCurrentStateRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateVisitCurrentStateRequest $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $result = $this->visitCurrentStateService->updateVisitCurrentState($id, $validatedData);
            
            if (!$result['success']) {
                return response()->json($result, $result['status']);
            }
            
            return response()->json([
                'success' => true,
                'data' => new VisitCurrentStateResource($result['data']),
                'message' => $result['message'],
                'status' => $result['status']
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update visit current state', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while updating the visit current state.',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $result = $this->visitCurrentStateService->deleteVisitCurrentState($id);
            
            return response()->json($result, $result['status']);
        } catch (\Exception $e) {
            Log::error('Failed to delete visit current state', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while deleting the visit current state.',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Get visit current state by visit ID.
     *
     * @param int $visitId
     * @return JsonResponse
     */
    public function getByVisitId(int $visitId): JsonResponse
    {
        try {
            $result = $this->visitCurrentStateService->getVisitCurrentStateByVisitId($visitId);
            
            if (!$result['success']) {
                return response()->json($result, $result['status']);
            }
            
            return response()->json([
                'success' => true,
                'data' => new VisitCurrentStateResource($result['data']),
                'message' => $result['message'],
                'status' => $result['status']
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve visit current state by visit ID', [
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving the visit current state.',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Get visit current states by facility.
     *
     * @param Request $request
     * @param int $facilityId
     * @return JsonResponse
     */
    public function getByFacility(Request $request, int $facilityId): JsonResponse
    {
        try {
            $filters = $request->only(['phase', 'department_id', 'has_critical_alerts', 'acuity_min']);
            $result = $this->visitCurrentStateService->getVisitCurrentStatesByFacility($facilityId, $filters);
            
            if (!$result['success']) {
                return response()->json($result, $result['status']);
            }
            
            return response()->json([
                'success' => true,
                'data' => VisitCurrentStateResource::collection($result['data']),
                'meta' => $result['meta'],
                'message' => $result['message'],
                'status' => $result['status']
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve visit current states by facility', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving visit current states.',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Get visits with critical alerts.
     *
     * @param int $facilityId
     * @return JsonResponse
     */
    public function getCriticalAlerts(int $facilityId): JsonResponse
    {
        try {
            $result = $this->visitCurrentStateService->getVisitsWithCriticalAlerts($facilityId);
            
            if (!$result['success']) {
                return response()->json($result, $result['status']);
            }
            
            return response()->json([
                'success' => true,
                'data' => VisitCurrentStateResource::collection($result['data']),
                'meta' => $result['meta'],
                'message' => $result['message'],
                'status' => $result['status']
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve visits with critical alerts', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving critical alerts.',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Get dashboard statistics.
     *
     * @param int $facilityId
     * @return JsonResponse
     */
    public function getDashboardStats(int $facilityId): JsonResponse
    {
        try {
            $result = $this->visitCurrentStateService->getDashboardStats($facilityId);
            
            return response()->json($result, $result['status']);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve dashboard statistics', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving dashboard statistics.',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Process visit event (CDC endpoint).
     *
     * @param Request $request
     * @param int $visitId
     * @return JsonResponse
     */
    public function processEvent(Request $request, int $visitId): JsonResponse
    {
        try {
            $request->validate([
                'event_type' => 'required|string',
                'event_id' => 'nullable|integer',
                'data' => 'nullable|array'
            ]);
            
            $eventData = array_merge(
                ['event_type' => $request->event_type, 'event_id' => $request->event_id],
                $request->data ?? []
            );
            
            $result = $this->visitCurrentStateService->processVisitEvent($visitId, $eventData);
            
            if (!$result['success']) {
                return response()->json($result, $result['status']);
            }
            
            return response()->json([
                'success' => true,
                'data' => new VisitCurrentStateResource($result['data']),
                'message' => $result['message'],
                'status' => $result['status']
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process visit event', [
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'event_data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the visit event.',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }
}