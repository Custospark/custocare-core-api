<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\VisitActor\StoreVisitActorRequest;
use App\Http\Requests\VisitActor\UpdateVisitActorRequest;
use App\Http\Resources\VisitActorResource;
use App\Services\Contracts\VisitActorServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;


class VisitActorController extends Controller
{
    /**
     * @var VisitActorServiceInterface
     */
    protected $visitActorService;

    /**
     * VisitActorController constructor.
     *
     * @param VisitActorServiceInterface $visitActorService
     */
    public function __construct(VisitActorServiceInterface $visitActorService)
    {
        $this->visitActorService = $visitActorService;
    }

    /**
     * Display a listing of visit actors.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'facility_id',
                'visit_id',
                'staff_id',
                'participation_type',
                'date_from',
                'date_to',
                'is_billable_provider',
                'per_page'
            ]);
            
            $visitActors = $this->visitActorService->getAllVisitActors($filters);
            
            return response()->json([
                'success' => true,
                'message' => 'Visit actors retrieved successfully.',
                'data' => VisitActorResource::collection($visitActors),
                'meta' => [
                    'current_page' => $visitActors->currentPage(),
                    'last_page' => $visitActors->lastPage(),
                    'per_page' => $visitActors->perPage(),
                    'total' => $visitActors->total(),
                    'from' => $visitActors->firstItem(),
                    'to' => $visitActors->lastItem(),
                ],
                'links' => [
                    'first' => $visitActors->url(1),
                    'last' => $visitActors->url($visitActors->lastPage()),
                    'prev' => $visitActors->previousPageUrl(),
                    'next' => $visitActors->nextPageUrl(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve visit actors', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve visit actors. Please try again.',
                'errors' => ['server' => 'Internal server error']
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created visit actor in storage.
     *
     * @param StoreVisitActorRequest $request
     * @return JsonResponse
     */
    public function store(StoreVisitActorRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            
            $result = $this->visitActorService->createVisitActor($validatedData);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => $result['errors'],
                    'data' => null
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new VisitActorResource($result['data']),
                'metadata' => $result['metadata'] ?? []
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            Log::error('Failed to store visit actor', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create visit actor. Please try again.',
                'errors' => ['server' => 'Internal server error']
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified visit actor.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $visitActor = $this->visitActorService->getVisitActorById($id);
            
            if (!$visitActor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Visit actor not found.',
                    'errors' => ['id' => 'Visit actor not found with ID: ' . $id]
                ], Response::HTTP_NOT_FOUND);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Visit actor retrieved successfully.',
                'data' => new VisitActorResource($visitActor)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve visit actor', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve visit actor. Please try again.',
                'errors' => ['server' => 'Internal server error']
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified visit actor in storage.
     *
     * @param UpdateVisitActorRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateVisitActorRequest $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            
            $result = $this->visitActorService->updateVisitActor($id, $validatedData);
            
            if (!$result['success']) {
                $statusCode = isset($result['errors']['id']) 
                    ? Response::HTTP_NOT_FOUND 
                    : Response::HTTP_UNPROCESSABLE_ENTITY;
                
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => $result['errors'],
                    'data' => null
                ], $statusCode);
            }
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new VisitActorResource($result['data']),
                'metadata' => $result['metadata'] ?? []
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update visit actor', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update visit actor. Please try again.',
                'errors' => ['server' => 'Internal server error']
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified visit actor from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $result = $this->visitActorService->deleteVisitActor($id);
            
            if (!$result['success']) {
                $statusCode = isset($result['errors']['id']) 
                    ? Response::HTTP_NOT_FOUND 
                    : Response::HTTP_UNPROCESSABLE_ENTITY;
                
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => $result['errors'],
                    'data' => null
                ], $statusCode);
            }
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => null,
                'metadata' => $result['metadata'] ?? []
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete visit actor', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete visit actor. Please try again.',
                'errors' => ['server' => 'Internal server error']
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * End participation for a visit actor.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function endParticipation(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'participation_ended_at' => 'nullable|date',
                'metadata' => 'nullable|array',
            ]);
            
            $result = $this->visitActorService->endParticipation($id, $request->all());
            
            if (!$result['success']) {
                $statusCode = isset($result['errors']['id']) 
                    ? Response::HTTP_NOT_FOUND 
                    : Response::HTTP_UNPROCESSABLE_ENTITY;
                
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => $result['errors'],
                    'data' => null
                ], $statusCode);
            }
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new VisitActorResource($result['data']),
                'metadata' => $result['metadata'] ?? []
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to end participation', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to end participation. Please try again.',
                'errors' => ['server' => 'Internal server error']
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get visit actors by visit ID.
     *
     * @param int $visitId
     * @return JsonResponse
     */
    public function byVisit(int $visitId): JsonResponse
    {
        try {
            $visitActors = $this->visitActorService->getVisitActorsByVisit($visitId);
            
            return response()->json([
                'success' => true,
                'message' => 'Visit actors retrieved successfully.',
                'data' => VisitActorResource::collection($visitActors),
                'meta' => [
                    'count' => $visitActors->count(),
                    'visit_id' => $visitId
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve visit actors by visit', [
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve visit actors. Please try again.',
                'errors' => ['server' => 'Internal server error']
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get active participations for staff.
     *
     * @param int $staffId
     * @return JsonResponse
     */
    public function activeParticipations(int $staffId): JsonResponse
    {
        try {
            $participations = $this->visitActorService->getActiveStaffParticipations($staffId);
            
            return response()->json([
                'success' => true,
                'message' => 'Active participations retrieved successfully.',
                'data' => VisitActorResource::collection($participations),
                'meta' => [
                    'count' => $participations->count(),
                    'staff_id' => $staffId,
                    'is_active' => true
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve active participations', [
                'staff_id' => $staffId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve active participations. Please try again.',
                'errors' => ['server' => 'Internal server error']
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}